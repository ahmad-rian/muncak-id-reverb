<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Stream;
use App\Models\TrailClassification;
use App\Services\GeminiClassifier;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveCamController extends Controller
{
    public function __construct(
        protected StreamService $streamService
    ) {}

    /**
     * Display list of live streams (public viewer page)
     */
    public function index(Request $request)
    {
        // Section 1: Live streams only (no cache for real-time data)
        $liveStreams = Stream::with(['mountain', 'jalur'])
            ->where('status', 'live')
            ->latest('started_at')
            ->get();

        // Section 2: Classifications (from all streams, not just live)
        $classificationQuery = Stream::with(['mountain', 'jalur', 'latestClassification'])
            ->whereHas('latestClassification')
            ->latest('updated_at');

        // Search by trail name or title
        if ($request->filled('search')) {
            $search = $request->search;
            $classificationQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('jalur', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%");
                    })
                    ->orWhereHas('mountain', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by jalur
        if ($request->filled('jalur_id')) {
            $classificationQuery->where('jalur_id', $request->jalur_id);
        }

        $streams = $classificationQuery->paginate(12);

        // ✅ OPTIMIZATION: Cache jalurs list for 5 minutes (300 seconds)
        // Only jalurs with classifications are needed for filter dropdown
        $jalurs = Cache::remember('jalurs_with_classifications', 300, function () {
            return \App\Models\Rute::whereHas('streams', function ($q) {
                $q->whereHas('latestClassification');
            })->orderBy('nama')->get();
        });

        return view('live-cam.index', compact('liveStreams', 'streams', 'jalurs'));
    }

    /**
     * Show stream viewer page (public)
     */
    public function show(int $id)
    {
        $stream = Stream::with('mountain')->findOrFail($id);

        // Generate guest username for chat
        $guestUsername = session('guest_username');
        if (!$guestUsername) {
            $guestUsername = 'Guest' . rand(1000, 9999);
            session(['guest_username' => $guestUsername]);
        }

        // ✅ OPTIMIZATION: Increment total views asynchronously to avoid database lock contention
        // Under high load, synchronous increment causes row-level locking and timeouts
        // Process after response is sent to user
        dispatch(function () use ($id) {
            try {
                Stream::where('id', $id)->increment('total_views');
            } catch (\Exception $e) {
                \Log::error('Failed to increment total views', [
                    'stream_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }
        })->afterResponse();

        return view('live-cam.watch', compact('stream', 'guestUsername'));
    }

    /**
     * Get stream status (public API)
     */
    public function getStatus(int $id)
    {
        $stream = Stream::find($id);

        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }

        return response()->json([
            'is_live' => $stream->status === 'live',
            'status' => $stream->status,
            'quality' => $stream->quality,
            'viewer_count' => $stream->viewer_count,
            'started_at' => $stream->started_at?->toISOString(),
        ]);
    }

    /**
     * Get chunk (public API)
     */
    public function getChunk(int $id, int $index)
    {
        $chunkPath = "live-streams/{$id}/chunks/{$index}.webm";

        if (!Storage::disk('public')->exists($chunkPath)) {
            return response()->json(['error' => 'Chunk not found'], 404);
        }

        return response()->file(
            Storage::disk('public')->path($chunkPath),
            ['Content-Type' => 'video/webm']
        );
    }

    /**
     * Send chat message (public)
     */
    public function sendChat(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|max:50',
                'message' => 'required|string|max:200',
            ]);

            $stream = Stream::findOrFail($id);

            if ($stream->status !== 'live') {
                return response()->json(['success' => false, 'error' => 'Stream offline'], 409);
            }

            // ✅ OPTIMIZATION: Rate limiting - 5 messages per 10 seconds per IP
            $ip = $request->ip();
            $rateLimitKey = 'chat:ratelimit:' . $id . ':' . $ip;
            $messageCount = Cache::get($rateLimitKey, 0);

            if ($messageCount >= 5) {
                $ttl = Cache::get($rateLimitKey . ':ttl', 0);
                $waitTime = max(0, 10 - (time() - $ttl));
                return response()->json([
                    'success' => false,
                    'error' => 'Too many messages. Please wait.',
                    'wait' => $waitTime
                ], 429);
            }

            // Sanitize message
            $message = strip_tags($validated['message']);
            $username = strip_tags($validated['username']);

            $chatMessage = ChatMessage::create([
                'stream_id' => $stream->id,
                'username' => $username,
                'message' => $message,
            ]);

            // Update rate limit counter
            if ($messageCount === 0) {
                Cache::put($rateLimitKey . ':ttl', time(), 10);
            }
            Cache::put($rateLimitKey, $messageCount + 1, 10);

            $messageData = [
                'username' => $chatMessage->username,
                'message' => $chatMessage->message,
                'created_at' => $chatMessage->created_at->toISOString(),
            ];

            try {
                event(new \App\Events\ChatMessageSent($stream->id, $messageData));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast ChatMessageSent failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $messageData,
            ]);
        } catch (\Throwable $e) {
            \Log::error('sendChat failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to send message'], 500);
        }
    }

    /**
     * Get chat history (public API)
     */
    public function getChatHistory(int $id)
    {
        $stream = Stream::findOrFail($id);

        // Only return chat history if stream is live
        if ($stream->status !== 'live') {
            return response()->json(['messages' => []]);
        }

        // ✅ OPTIMIZATION: Add pagination and limit - only get last 100 messages
        // This prevents slow queries when there are thousands of chat messages
        $messages = ChatMessage::where('stream_id', $id)
            ->where('created_at', '>=', $stream->started_at)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'username' => $msg->username,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at->toISOString(),
                ];
            })
            ->values();

        return response()->json(['messages' => $messages]);
    }

    /**
     * Update viewer count (public API)
     * ✅ OPTIMIZATION: Use Redis atomic operations to prevent race conditions
     */
    public function updateViewerCount(Request $request, int $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:join,leave',
        ]);

        $stream = Stream::find($id);

        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }

        // ✅ FIX: Use Redis atomic increment/decrement to prevent race conditions
        // Redis operations are atomic and much faster than database row locking
        $redisKey = "stream:{$id}:viewer_count";
        $newCount = 0;

        try {
            if ($validated['action'] === 'join') {
                $newCount = Cache::increment($redisKey);

                // Initialize Redis counter if first viewer
                if ($newCount === 1) {
                    Cache::put($redisKey, max(1, $stream->viewer_count + 1), 3600);
                    $newCount = Cache::get($redisKey);
                }
            } else {
                $newCount = Cache::decrement($redisKey);

                // Prevent negative counts
                if ($newCount < 0) {
                    Cache::put($redisKey, 0, 3600);
                    $newCount = 0;
                }
            }

            // Sync to database asynchronously (non-blocking, low priority)
            dispatch(function () use ($id, $newCount) {
                try {
                    Stream::where('id', $id)->update(['viewer_count' => $newCount]);
                } catch (\Throwable $e) {
                    \Log::warning('Viewer count DB sync failed: ' . $e->getMessage());
                }
            })->afterResponse();

            // Broadcast viewer count update immediately from Redis value
            event(new \App\Events\ViewerCountUpdated($stream->id, $newCount));

            return response()->json([
                'success' => true,
                'viewer_count' => $newCount,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Viewer count update failed: ' . $e->getMessage());

            // Fallback to optimistic response
            if ($validated['action'] === 'join') {
                $newCount = $stream->viewer_count + 1;
            } else {
                $newCount = max(0, $stream->viewer_count - 1);
            }

            return response()->json([
                'success' => true,
                'viewer_count' => $newCount,
            ]);
        }
    }

    /**
     * Get current quality (public API)
     */
    public function getQuality(int $id)
    {
        $stream = Stream::findOrFail($id);

        return response()->json([
            'quality' => $stream->quality ?? '720p',
        ]);
    }

    // ==================== ADMIN METHODS ====================

    /**
     * Admin: Create stream
     */
    public function create()
    {
        $jalurs = \App\Models\Rute::with('gunung')->orderBy('nama')->get();
        return view('admin.live-stream.create', compact('jalurs'));
    }

    /**
     * Admin: Store stream
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'jalur_id' => 'required|exists:rute,id',
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        // Get rute to extract mountain_id
        $rute = \App\Models\Rute::findOrFail($validated['jalur_id']);

        $stream = Stream::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'jalur_id' => $validated['jalur_id'],
            'mountain_id' => $rute->gunung_id,
            'user_id' => auth()->id(),
            'status' => 'offline',
            'quality' => $validated['quality'],
            'stream_key' => \Str::random(32),
        ]);

        return redirect()->route('admin.live-stream.broadcast', $stream->id)
            ->with('success', 'Stream created successfully. You can now start broadcasting.');
    }

    /**
     * Admin: Broadcast dashboard
     */
    public function broadcast(int $id)
    {
        $stream = Stream::with('mountain')->findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        return view('admin.live-stream.broadcast', compact('stream'));
    }

    /**
     * Admin: Start stream
     */
    public function startStream(Request $request, int $id)
    {
        $validated = $request->validate([
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stream = $this->streamService->startStream($stream, $validated['quality']);

        // Broadcast stream started event (non-blocking)
        try {
            event(new \App\Events\StreamStarted($stream->id));
        } catch (\Exception $e) {
            \Log::warning('Failed to broadcast StreamStarted event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'stream' => [
                'id' => $stream->id,
                'status' => $stream->status,
                'quality' => $stream->quality,
                'started_at' => $stream->started_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Admin: Stop stream
     */
    public function stopStream(int $id)
    {
        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stream = $this->streamService->stopStream($stream);

        // Broadcast stream stopped event (non-blocking)
        try {
            event(new \App\Events\StreamStopped($stream->id));
        } catch (\Exception $e) {
            \Log::warning('Failed to broadcast StreamStopped event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'stream' => [
                'id' => $stream->id,
                'status' => $stream->status,
                'stopped_at' => $stream->stopped_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Admin: Upload chunk
     */
    public function uploadChunk(Request $request, int $id)
    {
        $validated = $request->validate([
            'chunk' => 'required|file',
            'index' => 'required|integer|min:0',
            'timestamp' => 'required|integer',
        ]);

        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Store chunk
        $chunkPath = "live-streams/{$id}/chunks/" . $validated['index'] . '.webm';
        $request->file('chunk')->storeAs('', $chunkPath, 'public');

        // Broadcast new chunk event
        event(new \App\Events\NewChunkAvailable($stream->id, $validated['index']));

        return response()->json([
            'success' => true,
            'index' => $validated['index'],
            'path' => $chunkPath,
        ]);
    }

    /**
     * Admin: Change quality
     */
    public function changeQuality(Request $request, int $id)
    {
        $validated = $request->validate([
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stream->update(['quality' => $validated['quality']]);

        return response()->json([
            'success' => true,
            'quality' => $stream->quality,
        ]);
    }

    /**
     * Admin: Delete stream
     */
    public function destroy(int $id)
    {
        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete chunks
        Storage::disk('public')->deleteDirectory("live-streams/{$id}");

        $stream->delete();

        return redirect()->route('admin.live-stream.index')
            ->with('success', 'Stream deleted successfully.');
    }

    /**
     * Admin: Save thumbnail
     */
    public function saveThumbnail(Request $request, int $id)
    {
        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Remove data URL prefix if present
            $image = $validated['image'];
            if (preg_match('/^data:image\/\w+;base64,/', $image)) {
                $image = substr($image, strpos($image, ',') + 1);
            }

            // Decode and save image
            $imageData = base64_decode($image);
            $filename = "thumbnails/stream_{$id}.jpg";

            // Delete old thumbnail if exists
            if (Storage::disk('public')->exists($filename)) {
                Storage::disk('public')->delete($filename);
            }

            Storage::disk('public')->put($filename, $imageData);

            // Update stream with thumbnail URL
            $stream->update(['thumbnail_url' => $filename]);

            return response()->json([
                'success' => true,
                'thumbnail_url' => Storage::disk('public')->url($filename),
            ]);
        } catch (\Exception $e) {
            \Log::error('Thumbnail save error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin: Classify frame using Gemini AI
     */
    public function classifyFrame(Request $request, int $id)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'timestamp' => 'required|integer',
        ]);

        $stream = Stream::with('jalur')->findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $classifier = new GeminiClassifier();

            // Save frame temporarily
            $imagePath = $classifier->saveFrame($validated['image'], $id);

            // Classify with Gemini AI
            $result = $classifier->classifyTrailImage($validated['image']);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Classification failed',
                ]);
            }

            // Get trail name from jalur relation
            $trailName = $stream->jalur?->nama ?? $stream->title;

            // Update or create classification (replace old one)
            $classification = TrailClassification::updateOrCreate(
                ['stream_id' => $stream->id],
                [
                    'trail_name' => $trailName,
                    'classified_at' => now(),
                    'weather' => $result['weather'],
                    'crowd_density' => $result['crowd_density'],
                    'visibility' => $result['visibility'],
                    'confidence_weather' => $result['confidence_weather'],
                    'confidence_crowd' => $result['confidence_crowd'],
                    'confidence_visibility' => $result['confidence_visibility'],
                    'image_path' => $imagePath,
                ]
            );

            return response()->json([
                'success' => true,
                'classification' => [
                    'weather' => $classification->weather,
                    'crowd_density' => $classification->crowd_density,
                    'visibility' => $classification->visibility,
                    'classified_at' => $classification->classified_at_wib,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Classification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Backward compatibility - WebRTC methods (deprecated)
    public function viewerReady(Request $request, int $id)
    {
        return response()->json(['success' => true, 'message' => 'WebRTC is deprecated, using MSE instead']);
    }

    public function sendSignal(Request $request, int $id)
    {
        return response()->json(['success' => true, 'message' => 'WebRTC is deprecated, using MSE instead']);
    }

    /**
     * Admin: Test classification page
     */
    public function testClassification()
    {
        $streams = Stream::with(['jalur', 'mountain'])->get();
        return view('admin.live-stream.test-classification', compact('streams'));
    }

    /**
     * Get LiveKit token for broadcaster/viewer
     */
    public function getLiveKitToken(int $id)
    {
        $stream = Stream::findOrFail($id);

        // Check authorization for broadcaster
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $livekitUrl = config('services.livekit.url');
            $apiKey = config('services.livekit.api_key');
            $apiSecret = config('services.livekit.api_secret');

            if (!$livekitUrl || !$apiKey || !$apiSecret) {
                return response()->json([
                    'success' => false,
                    'error' => 'LiveKit not configured'
                ], 500);
            }

            $identity = 'broadcaster-' . auth()->id();
            $roomName = 'stream-' . $stream->id;

            // Use LiveKit SDK (correct API based on tests)
            $options = (new \Agence104\LiveKit\AccessTokenOptions())
                ->setIdentity($identity)
                ->setName(auth()->user()->name ?? 'Broadcaster');

            $token = new \Agence104\LiveKit\AccessToken($apiKey, $apiSecret, $options);

            $videoGrant = new \Agence104\LiveKit\VideoGrant();
            $videoGrant->setRoomName($roomName);
            $videoGrant->setRoomJoin(true);
            $videoGrant->setCanPublish(true);
            $videoGrant->setCanSubscribe(true);

            $token->setGrant($videoGrant);

            return response()->json([
                'success' => true,
                'token' => $token->toJwt(),
                'url' => $livekitUrl,
                'room' => $roomName,
            ]);
        } catch (\Exception $e) {
            \Log::error('LiveKit token generation failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get LiveKit token for viewer (public - no auth required)
     */
    public function getLiveKitViewerToken(int $id)
    {
        $stream = Stream::findOrFail($id);

        try {
            $livekitUrl = config('services.livekit.url');
            $apiKey = config('services.livekit.api_key');
            $apiSecret = config('services.livekit.api_secret');

            if (!$livekitUrl || !$apiKey || !$apiSecret) {
                return response()->json([
                    'success' => false,
                    'error' => 'LiveKit not configured'
                ], 500);
            }

            $identity = auth()->check()
                ? 'viewer-' . auth()->id()
                : 'guest-' . uniqid();

            $roomName = 'stream-' . $stream->id;

            $options = (new \Agence104\LiveKit\AccessTokenOptions())
                ->setIdentity($identity)
                ->setName(auth()->check() ? auth()->user()->name : 'Guest Viewer');

            $token = new \Agence104\LiveKit\AccessToken($apiKey, $apiSecret, $options);

            $videoGrant = new \Agence104\LiveKit\VideoGrant();
            $videoGrant->setRoomName($roomName);
            $videoGrant->setRoomJoin(true);
            $videoGrant->setCanPublish(false);
            $videoGrant->setCanSubscribe(true);

            $token->setGrant($videoGrant);

            return response()->json([
                'success' => true,
                'token' => $token->toJwt(),
                'url' => $livekitUrl,
                'room' => $roomName,
            ]);
        } catch (\Exception $e) {
            \Log::error('LiveKit viewer token generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update mirror state
     */
    public function updateMirrorState(Request $request, int $id)
    {
        $validated = $request->validate([
            'is_mirrored' => 'required|boolean',
        ]);

        $stream = Stream::findOrFail($id);

        // Check authorization
        if ($stream->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Broadcast mirror state change event
            event(new \App\Events\MirrorStateChanged($stream->id, $validated['is_mirrored']));

            return response()->json([
                'success' => true,
                'is_mirrored' => $validated['is_mirrored'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Mirror state update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
