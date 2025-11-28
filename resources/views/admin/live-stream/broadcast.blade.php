<x-layout.admin>
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('admin.live-stream.index') }}" class="btn btn-ghost btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Streams
            </a>
            <h1 class="mt-4 text-3xl font-bold text-gray-800">Broadcast Dashboard</h1>
            <p class="mt-2 text-gray-600">{{ $stream->title }}</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Video Preview & Controls -->
            <div class="lg:col-span-2">
                <!-- Video Preview Card -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <h2 class="card-title">Camera Preview</h2>
                            <div id="stream-status">
                                <span class="badge badge-neutral">OFFLINE</span>
                            </div>
                        </div>

                        <!-- Video Element -->
                        <div class="relative mt-4 aspect-video overflow-hidden rounded-lg bg-black">
                            <video id="camera-preview" autoplay muted playsinline
                                class="h-full w-full object-cover"></video>

                            <!-- Permission Warning -->
                            <div id="permission-warning"
                                class="absolute inset-0 flex items-center justify-center bg-black/90">
                                <div class="text-center text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-gray-400"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-4 text-lg">Camera access required</p>
                                    <p class="mt-2 text-sm text-gray-400">Please allow camera and microphone permissions
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Device Selection -->
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Camera</span>
                                </label>
                                <select id="cameraSelect" class="select select-bordered select-sm">
                                    <option>Loading devices...</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Microphone</span>
                                </label>
                                <select id="micSelect" class="select select-bordered select-sm">
                                    <option>Loading devices...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Mirror Toggle -->
                        <div class="mt-4">
                            <button id="mirror-camera" class="btn btn-ghost btn-sm gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Mirror Camera
                            </button>
                        </div>

                        <!-- Permission Alert -->
                        <div id="permissionAlert" class="alert alert-warning mt-4" style="display: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <h3 class="font-bold">Camera/Microphone Access Required</h3>
                                <div class="text-xs">Please allow access to start broadcasting</div>
                            </div>
                        </div>

                        <!-- Success Alert -->
                        <div id="permissionSuccess" class="alert alert-success mt-4" style="display: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-bold">Camera Access Granted</h3>
                                <div class="text-xs">You can now start streaming</div>
                            </div>
                        </div>

                        <!-- Stream Controls -->
                        <div class="mt-6 flex flex-col gap-4">
                            <button id="requestPermissionBtn" class="btn btn-primary gap-2" style="display: none;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Request Camera Access
                            </button>

                            <!-- Stream Control Buttons -->
                            <button id="start-button" class="btn btn-success w-full gap-2" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Start Streaming
                            </button>

                            <button id="stop-button" class="btn btn-error w-full gap-2 hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                </svg>
                                Stop Streaming
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stream Info Card -->
                <div class="card bg-base-100 shadow-lg mt-6">
                    <div class="card-body">
                        <h2 class="card-title">Stream Information</h2>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-600">Stream Key</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <code class="rounded bg-base-200 px-3 py-1 text-sm">{{ $stream->stream_key }}</code>
                                    <button onclick="navigator.clipboard.writeText('{{ $stream->stream_key }}')"
                                        class="btn btn-ghost btn-xs" title="Copy to clipboard">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600">Mountain</p>
                                <p class="mt-1 font-semibold">{{ $stream->mountain->nama ?? 'Not specified' }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600">Quality</p>
                                <p class="mt-1 font-semibold">{{ $stream->quality }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600">Created</p>
                                <p class="mt-1 font-semibold">{{ $stream->created_at->diffForHumans() }}</p>
                            </div>
                        </div>

                        @if ($stream->description)
                            <div class="mt-4">
                                <p class="text-sm text-gray-600">Description</p>
                                <p class="mt-1">{{ $stream->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Stats & Chat -->
            <div class="space-y-6">
                <!-- Stats Card -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title">Live Statistics</h2>

                        <div class="space-y-4">
                            <div class="stat rounded-lg bg-base-200 p-4">
                                <div class="stat-title">Current Viewers</div>
                                <div class="stat-value text-3xl" id="viewer-count">0</div>
                                <div class="stat-desc">Watching now</div>
                            </div>

                            <div class="stat rounded-lg bg-base-200 p-4">
                                <div class="stat-title">Stream Duration</div>
                                <div class="stat-value text-3xl" id="stream-duration">00:00:00</div>
                                <div class="stat-desc">Time elapsed</div>
                            </div>

                            <div class="stat rounded-lg bg-base-200 p-4">
                                <div class="stat-title">Stream Status</div>
                                <div class="stat-value text-2xl" id="streamStatus">OFFLINE</div>
                                <div class="stat-desc" id="streamStatusDesc">Not broadcasting</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Monitor Card -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title">Chat Monitor</h2>
                        <p class="text-sm text-gray-600">Recent viewer messages</p>

                        <div class="mt-4 h-96 overflow-y-auto rounded-lg bg-base-200 p-4" id="chat-monitor">
                            <p class="text-center text-sm text-gray-500">No messages yet</p>
                        </div>
                    </div>
                </div>

                <!-- Share Link Card -->
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title">Share Stream</h2>
                        <p class="text-sm text-gray-600">Share this link with viewers</p>

                        <div class="mt-4">
                            <div class="flex items-center gap-2">
                                <input type="text" value="{{ route('live-cam.show', $stream->id) }}" readonly
                                    class="input input-bordered input-sm w-full" id="shareLink" />
                                <button
                                    onclick="navigator.clipboard.writeText(document.getElementById('shareLink').value)"
                                    class="btn btn-primary btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot:js>
        <script>
            window.streamId = {{ $stream->id }};
            window.streamSlug = '{{ $stream->slug }}';
        </script>
        @vite('resources/js/live-cam/broadcaster-reverb.js')
    </x-slot:js>
</x-layout.admin>