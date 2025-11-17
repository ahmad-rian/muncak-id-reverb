<?php

namespace App\Http\Controllers;

use App\Models\Stream;
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
    public function index()
    {
        $streams = Stream::with('mountain')
            ->where('status', 'live')
            
            ->latest('started_at')
            ->paginate(12);

        return view('live-cam.index', compact('streams'));
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

        // Store chat message
        $message = [
            'username' => $validated['username'],
            'message' => $validated['message'],
            'created_at' => now()->toISOString(),
        ];

        // Broadcast via Reverb
        event(new \App\Events\ChatMessageSent($stream->id, $message));

        return response()->json(['success' => true]);
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
        $mountains = \App\Models\Gunung::orderBy('nama')->get();
        return view('admin.live-stream.create', compact('mountains'));
    }

    /**
     * Admin: Store stream
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mountain_id' => 'nullable|exists:gunung,id',
            'location' => 'nullable|string|max:255',
        ]);

        $stream = Stream::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'mountain_id' => $validated['mountain_id'] ?? null,
            'location' => $validated['location'] ?? null,
            'user_id' => auth()->id(),
            'status' => 'offline',
            'quality' => '720p',
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

        // Broadcast stream started event
        event(new \App\Events\StreamStarted($stream->id));

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

        // Broadcast stream stopped event
        event(new \App\Events\StreamStopped($stream->id));

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

    // Backward compatibility - WebRTC methods (deprecated)
    public function viewerReady(Request $request, int $id)
    {
        return response()->json(['success' => true, 'message' => 'WebRTC is deprecated, using MSE instead']);
    }

    public function sendSignal(Request $request, int $id)
    {
        return response()->json(['success' => true, 'message' => 'WebRTC is deprecated, using MSE instead']);
    }
}
