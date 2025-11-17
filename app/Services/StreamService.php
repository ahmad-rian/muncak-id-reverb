<?php

namespace App\Services;

use App\Events\StreamStarted;
use App\Events\StreamStopped;
use App\Events\ViewerCountUpdated;
use App\Models\Stream;
use App\Models\StreamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StreamService
{
    public function findOrCreateStream(User $user): Stream
    {
        return Stream::firstOrCreate(
            ['user_id' => $user->id],
            [
                'title' => $user->name . "'s Live Stream",
                'status' => 'offline',
                'quality' => '720p',
            ]
        );
    }

    public function startStream(Stream $stream, string $quality = '720p'): Stream
    {
        DB::transaction(function () use ($stream, $quality) {
            $stream->update([
                'status' => 'live',
                'quality' => $quality,
                'started_at' => now(),
                'stopped_at' => null,
                'viewer_count' => 0,
            ]);

            // Create new session
            StreamSession::create([
                'stream_id' => $stream->id,
                'session_started_at' => now(),
            ]);
        });

        // Note: Event is broadcasted in controller, not here
        return $stream->fresh();
    }

    public function stopStream(Stream $stream): Stream
    {
        DB::transaction(function () use ($stream) {
            $stream->update([
                'status' => 'offline',
                'stopped_at' => now(),
                'viewer_count' => 0,
            ]);

            // Update latest session
            $session = $stream->sessions()->latest()->first();
            if ($session && !$session->session_ended_at) {
                $duration = now()->diffInSeconds($session->session_started_at);
                $session->update([
                    'session_ended_at' => now(),
                    'duration_seconds' => $duration,
                    'peak_viewers' => $stream->viewer_count,
                ]);
            }
        });

        // Note: Event is broadcasted in controller, not here
        return $stream->fresh();
    }

    public function updateQuality(Stream $stream, string $quality): Stream
    {
        $stream->update(['quality' => $quality]);

        return $stream->fresh();
    }

    public function updateViewerCount(Stream $stream, int $count): Stream
    {
        $stream->update(['viewer_count' => $count]);

        // Update peak viewers in current session
        $session = $stream->sessions()->latest()->first();
        if ($session && $count > $session->peak_viewers) {
            $session->update(['peak_viewers' => $count]);
        }

        broadcast(new ViewerCountUpdated($stream->id, $count))->toOthers();

        return $stream->fresh();
    }

    public function getLiveStreams()
    {
        return Stream::with('user')
            ->live()
            ->orderByDesc('viewer_count')
            ->orderByDesc('started_at')
            ->get();
    }

    public function getStreamWithDetails(int $streamId): ?Stream
    {
        return Stream::with(['user', 'chatMessages' => function ($query) {
            $query->latest()->limit(50);
        }])->find($streamId);
    }
}
