<?php

namespace App\Http\Controllers\LiveCam;

use App\Http\Controllers\Controller;
use App\Services\StreamService;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function __construct(
        protected StreamService $streamService
    ) {}

    /**
     * Display list of live streams
     */
    public function index()
    {
        $streams = $this->streamService->getLiveStreams();

        return view('live-cam.index', compact('streams'));
    }

    /**
     * Show stream viewer page
     */
    public function show(int $id)
    {
        $stream = $this->streamService->getStreamWithDetails($id);

        if (!$stream) {
            abort(404, 'Stream not found');
        }

        // Generate guest username for anonymous viewers
        $guestUsername = 'Guest-' . strtoupper(substr(md5(session()->getId()), 0, 6));

        return view('live-cam.watch', compact('stream', 'guestUsername'));
    }
}
