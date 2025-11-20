<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Stream;
use App\Models\TrailClassification;
use App\Services\GeminiClassifier;
use App\Services\StreamService;
use Illuminate\Http\Request;
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
        // Section 1: Live streams only
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

        // Get only jalurs that have streams with classifications
        $jalurs = \App\Models\Rute::whereHas('streams', function ($q) {
            $q->whereHas('latestClassification');
        })->orderBy('nama')->get();

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
        $validated = $request->validate([
            'username' => 'required|string|max:50',
            'message' => 'required|string|max:200',
        ]);

        $stream = Stream::findOrFail($id);

        // Save chat message to database
        $chatMessage = ChatMessage::create([
            'stream_id' => $stream->id,
            'username' => $validated['username'],
            'message' => $validated['message'],
        ]);

        // Prepare message data for broadcast
        $message = [
            'username' => $chatMessage->username,
            'message' => $chatMessage->message,
            'created_at' => $chatMessage->created_at->toISOString(),
        ];

        // Broadcast via Reverb
        event(new \App\Events\ChatMessageSent($stream->id, $message));

        return response()->json(['success' => true]);
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

        // Get chat messages for this stream session (since stream started)
        $messages = ChatMessage::where('stream_id', $id)
            ->where('created_at', '>=', $stream->started_at)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'username' => $msg->username,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at->toISOString(),
                ];
            });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Update viewer count (public API)
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

        if ($validated['action'] === 'join') {
            $stream->increment('viewer_count');
        } else {
            $stream->decrement('viewer_count', 1, ['viewer_count' => 0]);
        }

        $stream->refresh();

        // Broadcast viewer count update
        event(new \App\Events\ViewerCountUpdated($stream->id, $stream->viewer_count));

        return response()->json([
            'success' => true,
            'viewer_count' => $stream->viewer_count,
        ]);
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
            // Decode and save image
            $imageData = base64_decode($validated['image']);
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
}
