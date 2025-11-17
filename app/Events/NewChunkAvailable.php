<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChunkAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $streamId,
        public int $index
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('stream.' . $this->streamId);
    }

    public function broadcastAs(): string
    {
        return 'new-chunk';
    }

    public function broadcastWith(): array
    {
        return [
            'index' => $this->index,
            'timestamp' => now()->timestamp,
        ];
    }
}
