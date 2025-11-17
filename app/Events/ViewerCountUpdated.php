<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewerCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $streamId,
        public int $count
    ) {
        //
    }

    public function broadcastOn(): Channel
    {
        return new Channel('stream.' . $this->streamId);
    }

    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'count' => $this->count,
        ];
    }

    public function broadcastAs(): string
    {
        return 'viewer-count-updated';
    }
}
