<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gunung;
use App\Models\Stream;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiveStreamController extends Controller
{
    public function __construct(
        protected StreamService $streamService
    ) {}

    /**
     * Display list of all streams (admin only)
     */
    public function index()
    {
        $streams = Stream::with(['user', 'mountain'])
            ->latest()
            ->paginate(12);

        return view('admin.live-stream.index', compact('streams'));
    }

    /**
     * Show create stream form
     */
    public function create()
    {
        $mountains = Gunung::orderBy('nama')->get();

        return view('admin.live-stream.create', compact('mountains'));
    }

    /**
     * Store new stream
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'mountain_id' => 'nullable|exists:gunung,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'quality' => 'required|in:360p,720p,1080p',
        ]);

        // Generate stream key
        $streamKey = Str::random(32);

        $stream = Stream::create([
            'user_id' => auth()->id(),
            'mountain_id' => $validated['mountain_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'stream_key' => $streamKey,
            'quality' => $validated['quality'],
            'status' => 'offline',
        ]);

        return redirect()
            ->route('admin.live-stream.broadcast', $stream->id)
            ->with('success', 'Stream created! You can now start broadcasting.');
    }

    /**
     * Show broadcaster dashboard
     */
    public function broadcast(int $id)
    {
        $stream = Stream::with(['user', 'mountain'])->findOrFail($id);

        // Only owner can access
        if ($stream->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this stream');
        }

        return view('admin.live-stream.broadcast', compact('stream'));
    }

    /**
     * Delete stream
     */
    public function destroy(int $id)
    {
        $stream = Stream::findOrFail($id);

        // Only owner can delete
        if ($stream->user_id !== auth()->id()) {
            abort(403);
        }

        // Stop stream if live
        if ($stream->isLive()) {
            $this->streamService->stopStream($stream);
        }

        $stream->delete();

        return redirect()
            ->route('admin.live-stream.index')
            ->with('success', 'Stream deleted successfully');
    }
}
