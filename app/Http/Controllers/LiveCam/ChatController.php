<?php

namespace App\Http\Controllers\LiveCam;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Stream;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Send chat message (public - no auth required for guests)
     */
    public function store(Request $request, int $streamId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'username' => 'required|string|max:50',
        ]);

        $stream = Stream::find($streamId);

        if (!$stream || !$stream->isLive()) {
            return response()->json(['error' => 'Stream not available'], 404);
        }

        $chatMessage = ChatMessage::create([
            'stream_id' => $streamId,
            'user_id' => auth()->id(), // null for guests
            'username' => $validated['username'],
            'message' => $validated['message'],
        ]);

        broadcast(new ChatMessageSent($chatMessage))->toOthers();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $chatMessage->id,
                'username' => $chatMessage->username,
                'message' => $chatMessage->message,
                'created_at' => $chatMessage->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get chat history for a stream
     */
    public function index(int $streamId)
    {
        $messages = ChatMessage::where('stream_id', $streamId)
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }
}
