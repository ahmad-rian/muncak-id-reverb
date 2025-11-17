<?php

namespace App\Http\Controllers\LiveCam;

use App\Http\Controllers\Controller;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BroadcastController extends Controller implements HasMiddleware
{
    public function __construct(
        protected StreamService $streamService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    /**
     * Quick access to create/find broadcaster's stream
     */
    public function myBroadcast()
    {
        $stream = $this->streamService->findOrCreateStream(auth()->user());

        return redirect()->route('live-cam.broadcast.dashboard', $stream->id);
    }

    /**
     * Broadcaster dashboard
     */
    public function dashboard(int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream) {
            abort(404, 'Stream not found');
        }

        // Check if user owns this stream
        if ($stream->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this stream');
        }

        return view('live-cam.broadcast', compact('stream'));
    }

    /**
     * Start streaming
     */
    public function start(Request $request, int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream || $stream->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        $stream = $this->streamService->startStream($stream, $validated['quality']);

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
     * Stop streaming
     */
    public function stop(int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream || $stream->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stream = $this->streamService->stopStream($stream);

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
     * Update stream quality
     */
    public function updateQuality(Request $request, int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream || $stream->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        $stream = $this->streamService->updateQuality($stream, $validated['quality']);

        return response()->json([
            'success' => true,
            'quality' => $stream->quality,
        ]);
    }

    /**
     * Upload video chunk
     */
    public function uploadChunk(Request $request, int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream || $stream->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$stream->isLive()) {
            return response()->json(['error' => 'Stream is not live'], 400);
        }

        $validated = $request->validate([
            'chunk' => 'required|file',
            'index' => 'required|integer',
            'timestamp' => 'required|integer',
        ]);

        // Store chunk in stream-specific directory
        $chunkPath = "live-streams/{$id}/chunks/" . $validated['index'] . '.webm';
        $request->file('chunk')->storeAs('', $chunkPath, 'public');

        return response()->json([
            'success' => true,
            'index' => $validated['index'],
            'path' => $chunkPath,
        ]);
    }

    /**
     * Get video chunk
     */
    public function getChunk(int $id, int $index)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }

        $chunkPath = "live-streams/{$id}/chunks/{$index}.webm";

        if (!Storage::disk('public')->exists($chunkPath)) {
            return response()->json(['error' => 'Chunk not found'], 404);
        }

        return Storage::disk('public')->response($chunkPath, null, [
            'Content-Type' => 'video/webm',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get stream status
     */
    public function status(int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }

        return response()->json([
            'is_live' => $stream->isLive(),
            'status' => $stream->status,
            'quality' => $stream->quality,
            'viewer_count' => $stream->viewer_count,
            'started_at' => $stream->started_at?->toISOString(),
        ]);
    }
}
