<x-layout.app>
    <x-slot:title>Live Streaming - Muncak.id</x-slot:title>

    <main class="min-h-screen bg-base-200 pt-24 pb-8">
        <div class="container mx-auto px-4">
            <!-- Hero Section -->
            <div class="mb-8 text-center">
                <h1 class="mb-2 font-merriweather text-4xl font-bold text-base-content">
                    Live Streams
                </h1>
                <p class="text-lg text-base-content/70 mb-4">
                    Watch live mountain climbing experiences in real-time
                </p>

                @php
                    $totalLive = $streams->count();
                @endphp

                @if ($totalLive > 0)
                    <div class="mt-4">
                        <span class="badge badge-error badge-lg gap-2">
                            <span class="relative flex h-3 w-3">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
                            </span>
                            {{ $totalLive }} Live Now
                        </span>
                    </div>
                @endif
            </div>

            <!-- Live Streams Grid -->
            @if ($streams->count() > 0)
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($streams as $stream)
                        <a href="{{ route('live-cam.show', $stream->id) }}"
                            class="card bg-white shadow-md hover:shadow-lg transition-shadow">
                            <!-- Thumbnail -->
                            <figure class="relative aspect-video bg-black">
                                <div class="flex h-full w-full items-center justify-center text-white">
                                    <x-gmdi-videocam-r class="h-16 w-16" />
                                </div>

                                <!-- LIVE Badge -->
                                @if($stream->isLive())
                                <div class="absolute top-3 left-3">
                                    <span class="badge badge-error gap-2 font-semibold">
                                        <span class="relative flex h-2 w-2">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                                        </span>
                                        LIVE
                                    </span>
                                </div>
                                @endif

                                <!-- Viewer Count -->
                                <div class="absolute top-3 right-3">
                                    <span class="badge badge-neutral gap-1">
                                        <x-gmdi-visibility-r class="h-4 w-4" />
                                        {{ $stream->viewer_count }}
                                    </span>
                                </div>

                                <!-- Quality Badge -->
                                <div class="absolute bottom-3 right-3">
                                    <span class="badge badge-sm">{{ $stream->quality }}</span>
                                </div>
                            </figure>

                            <!-- Card Body -->
                            <div class="card-body">
                                <h2 class="card-title line-clamp-1 text-base-content">
                                    {{ $stream->title }}
                                </h2>

                                <div class="card-actions justify-between items-center mt-2">
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $stream->user->getAvatarUrl() }}" alt="{{ $stream->user->name }}" class="w-6 h-6 rounded-full">
                                        <span class="text-sm">{{ $stream->user->name }}</span>
                                    </div>
                                    <span class="text-xs text-base-content/60">
                                        {{ $stream->started_at ? $stream->started_at->diffForHumans() : $stream->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center py-20">
                    <x-gmdi-videocam-off-r class="mb-4 h-24 w-24 text-base-content/30" />
                    <h3 class="mb-2 text-2xl font-bold text-base-content/70">No Live Streams</h3>
                    <p class="mb-6 text-base-content/50">There are no active streams at the moment. Check back later!</p>
                </div>
            @endif
        </div>
    </main>

    @push('scripts')
        <script>
            // Auto-refresh every 30 seconds
            setInterval(() => {
                window.location.reload();
            }, 30000);
        </script>
    @endpush
</x-layout.app>
