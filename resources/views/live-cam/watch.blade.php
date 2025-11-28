<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $stream->title }} - Live Stream</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200" data-stream-id="{{ $stream->id }}">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="navbar bg-base-100 shadow-lg">
            <div class="flex-1">
                <a href="{{ route('live-cam.index') }}" class="btn btn-ghost normal-case text-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Streams
                </a>
            </div>
            <div class="flex-none">
                <span class="badge badge-error gap-2 mr-4">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                    </span>
                    LIVE
                </span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto p-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

                <!-- Video Section (70%) -->
                <div class="lg:col-span-8">
                    <div class="card bg-white shadow-xl">
                        <div class="card-body p-0">
                            <!-- Video Player -->
                            <div class="relative aspect-video bg-black rounded-t-2xl overflow-hidden">
                                <video id="video-player" class="w-full h-full object-contain" autoplay playsinline
                                    muted></video>

                                <!-- Loading Indicator -->
                                <div id="loading-indicator"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50">
                                    <span class="loading loading-spinner loading-lg text-white"></span>
                                </div>

                                <!-- Offline Placeholder -->
                                <div id="offline-placeholder"
                                    class="absolute inset-0 flex flex-col items-center justify-center bg-black text-white hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mb-4 opacity-50"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <h3 class="text-2xl font-bold mb-2">Stream has ended</h3>
                                    <p class="text-base-content/70">Thank you for watching!</p>
                                </div>

                                <!-- Viewer Count -->
                                <div class="absolute top-4 left-4">
                                    <div class="badge badge-lg gap-2 bg-black/70 text-white border-0">
                                        <span class="relative flex h-3 w-3">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                                            <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span id="viewer-count">{{ $stream->viewer_count }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stream Info -->
                            <div class="p-6">
                                <h1 class="text-2xl font-bold mb-2">{{ $stream->title }}</h1>
                                @if ($stream->description)
                                    <p class="text-base-content/70 mb-4">{{ $stream->description }}</p>
                                @endif

                                <div class="flex flex-wrap gap-4">
                                    @if ($stream->mountain)
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                            </svg>
                                            <span>{{ $stream->mountain->nama }}</span>
                                        </div>
                                    @endif
                                    @if ($stream->location)
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>{{ $stream->location }}</span>
                                        </div>
                                    @endif
                                    @if ($stream->started_at)
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Started {{ $stream->started_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Section (30%) -->
                <div class="lg:col-span-4">
                    <div class="card bg-white shadow-xl h-[600px] flex flex-col">
                        <div class="card-body p-0 flex flex-col h-full">
                            <!-- Chat Header -->
                            <div class="p-4 border-b border-base-300">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-bold text-lg flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        Live Chat
                                    </h3>
                                    <div class="badge badge-sm gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <span id="chat-viewer-count">{{ $stream->viewer_count }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages Container -->
                            <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3"
                                style="scroll-behavior: smooth;">
                                <div class="text-center text-sm text-base-content/50 py-4">
                                    <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-base-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Joined as <strong>{{ $guestUsername }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Chat Input -->
                            <div class="p-4 border-t border-base-300">
                                <form id="chat-form" class="space-y-2">
                                    <div class="flex gap-2">
                                        <input type="text" id="chat-input" placeholder="Type your message..."
                                            class="input input-bordered flex-1 input-sm" maxlength="200"
                                            autocomplete="off" />
                                        <button type="submit" class="btn btn-primary btn-sm" id="send-button">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex justify-between items-center text-xs text-base-content/50">
                                        <span>Chatting as <strong>{{ $guestUsername }}</strong></span>
                                        <span id="char-counter">0/200</span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Stream ID and Slug
        window.streamId = {{ $stream->id }};
        window.streamSlug = '{{ $stream->slug }}';

        // Chat username
        window.chatUsername = "{{ $guestUsername }}";
    </script>

    @vite(['resources/js/live-cam/viewer-reverb.js'])
</body>

</html>