<x-guest-layout>
    <div class="container mx-auto px-4 py-8" x-data="streamBroadcaster({{ $stream->id }})">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-heading">Broadcast Dashboard</h1>
            <p class="text-body-secondary">Manage your live stream</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Video Preview & Controls -->
            <div class="lg:col-span-2">
                <!-- Video Preview -->
                <div class="bg-gray-900 rounded-lg overflow-hidden aspect-video relative">
                    <video id="localVideo" autoplay muted playsinline class="w-full h-full object-contain"></video>

                    <!-- Status Badge -->
                    <div class="absolute top-4 left-4">
                        <span x-show="isLive" class="badge badge-error flex items-center gap-2 text-white">
                            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                            LIVE
                        </span>
                        <span x-show="!isLive" class="badge badge-neutral">OFFLINE</span>
                    </div>

                    <!-- Viewer Count -->
                    <div x-show="isLive" class="absolute top-4 right-4">
                        <span class="badge badge-neutral flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                            </svg>
                            <span x-text="viewerCount"></span> viewers
                        </span>
                    </div>

                    <!-- Duration -->
                    <div x-show="isLive" class="absolute bottom-4 left-4">
                        <span class="badge bg-black/50 text-white border-0">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-text="streamDuration"></span>
                        </span>
                    </div>
                </div>

                <!-- Stream Controls -->
                <div class="card bg-base-100 shadow-xl mt-4">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Stream Controls</h3>

                        <!-- Device Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Camera</span>
                                </label>
                                <select x-model="selectedCamera" @change="changeCamera()" class="select select-bordered" :disabled="isLive">
                                    <template x-for="device in cameras" :key="device.deviceId">
                                        <option :value="device.deviceId" x-text="device.label || 'Camera ' + (cameras.indexOf(device) + 1)"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Microphone</span>
                                </label>
                                <select x-model="selectedMicrophone" @change="changeMicrophone()" class="select select-bordered" :disabled="isLive">
                                    <template x-for="device in microphones" :key="device.deviceId">
                                        <option :value="device.deviceId" x-text="device.label || 'Microphone ' + (microphones.indexOf(device) + 1)"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <!-- Quality Selection -->
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text">Stream Quality</span>
                            </label>
                            <select x-model="quality" class="select select-bordered" :disabled="isLive">
                                <option value="360p">360p (Low - 640x360)</option>
                                <option value="720p">720p (HD - 1280x720)</option>
                                <option value="1080p">1080p (Full HD - 1920x1080)</option>
                            </select>
                        </div>

                        <!-- Start/Stop Buttons -->
                        <div class="flex gap-3">
                            <button
                                x-show="!isLive"
                                @click="startStream()"
                                class="btn btn-success flex-1"
                                :disabled="loading"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span x-text="loading ? 'Starting...' : 'Start Streaming'"></span>
                            </button>

                            <button
                                x-show="isLive"
                                @click="stopStream()"
                                class="btn btn-error flex-1"
                                :disabled="loading"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                </svg>
                                <span x-text="loading ? 'Stopping...' : 'Stop Streaming'"></span>
                            </button>
                        </div>

                        <!-- Permission Alert -->
                        <div x-show="permissionError" class="alert alert-error mt-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-text="permissionError"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Monitor & Stats -->
            <div class="lg:col-span-1">
                <!-- Stream Stats -->
                <div class="stats stats-vertical shadow w-full mb-4">
                    <div class="stat">
                        <div class="stat-title">Status</div>
                        <div class="stat-value text-2xl" :class="isLive ? 'text-error' : 'text-gray-400'" x-text="isLive ? 'LIVE' : 'OFFLINE'"></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title">Viewers</div>
                        <div class="stat-value text-2xl" x-text="viewerCount"></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title">Duration</div>
                        <div class="stat-value text-2xl" x-text="streamDuration || '--:--'"></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title">Quality</div>
                        <div class="stat-value text-2xl" x-text="quality"></div>
                    </div>
                </div>

                <!-- Chat Monitor -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg mb-3">Chat Monitor</h3>

                        <div class="h-[300px] overflow-y-auto space-y-2" id="chatMonitor">
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="chat chat-start">
                                    <div class="chat-header text-xs opacity-50" x-text="msg.username"></div>
                                    <div class="chat-bubble chat-bubble-sm" x-text="msg.message"></div>
                                </div>
                            </template>

                            <div x-show="messages.length === 0" class="text-center text-body-secondary py-8">
                                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p class="text-sm">No messages yet</p>
                            </div>
                        </div>

                        <p class="text-xs text-body-secondary mt-3">
                            Chat is read-only for broadcasters
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function streamBroadcaster(streamId) {
            return {
                streamId: streamId,
                isLive: {{ $stream->isLive() ? 'true' : 'false' }},
                quality: '{{ $stream->quality }}',
                viewerCount: {{ $stream->viewer_count }},
                streamDuration: '',
                loading: false,
                permissionError: '',

                cameras: [],
                microphones: [],
                selectedCamera: '',
                selectedMicrophone: '',

                localStream: null,
                messages: [],
                startedAt: null,

                async init() {
                    await this.getDevices();
                    await this.startPreview();
                    this.listenToChat();

                    if (this.isLive) {
                        this.startedAt = new Date('{{ $stream->started_at }}');
                        setInterval(() => this.updateDuration(), 1000);
                    }
                },

                async getDevices() {
                    try {
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        this.cameras = devices.filter(d => d.kind === 'videoinput');
                        this.microphones = devices.filter(d => d.kind === 'audioinput');

                        if (this.cameras.length > 0) this.selectedCamera = this.cameras[0].deviceId;
                        if (this.microphones.length > 0) this.selectedMicrophone = this.microphones[0].deviceId;
                    } catch (error) {
                        console.error('Error getting devices:', error);
                        this.permissionError = 'Failed to access media devices';
                    }
                },

                async startPreview() {
                    try {
                        const constraints = this.getConstraints();
                        this.localStream = await navigator.mediaDevices.getUserMedia(constraints);

                        const video = document.getElementById('localVideo');
                        if (video) {
                            video.srcObject = this.localStream;
                        }

                        this.permissionError = '';
                    } catch (error) {
                        console.error('Error starting preview:', error);
                        this.permissionError = 'Camera/microphone permission denied. Please allow access.';
                    }
                },

                getConstraints() {
                    const qualityMap = {
                        '360p': { width: 640, height: 360 },
                        '720p': { width: 1280, height: 720 },
                        '1080p': { width: 1920, height: 1080 }
                    };

                    return {
                        video: {
                            deviceId: this.selectedCamera ? { exact: this.selectedCamera } : undefined,
                            ...qualityMap[this.quality]
                        },
                        audio: {
                            deviceId: this.selectedMicrophone ? { exact: this.selectedMicrophone } : undefined
                        }
                    };
                },

                async changeCamera() {
                    await this.startPreview();
                },

                async changeMicrophone() {
                    await this.startPreview();
                },

                async startStream() {
                    this.loading = true;

                    try {
                        const response = await axios.post(`/live-cam/${this.streamId}/start`, {
                            quality: this.quality
                        });

                        if (response.data.success) {
                            this.isLive = true;
                            this.startedAt = new Date();
                            setInterval(() => this.updateDuration(), 1000);
                            alert('Stream started successfully!');
                        }
                    } catch (error) {
                        console.error('Failed to start stream:', error);
                        alert('Failed to start stream: ' + (error.response?.data?.message || error.message));
                    } finally {
                        this.loading = false;
                    }
                },

                async stopStream() {
                    if (!confirm('Are you sure you want to stop streaming?')) return;

                    this.loading = true;

                    try {
                        const response = await axios.post(`/live-cam/${this.streamId}/stop`);

                        if (response.data.success) {
                            this.isLive = false;
                            this.streamDuration = '';
                            alert('Stream stopped successfully!');
                        }
                    } catch (error) {
                        console.error('Failed to stop stream:', error);
                        alert('Failed to stop stream: ' + (error.response?.data?.message || error.message));
                    } finally {
                        this.loading = false;
                    }
                },

                listenToChat() {
                    window.Echo.join(`stream.${this.streamId}`)
                        .here((users) => {
                            this.viewerCount = users.length;
                        })
                        .joining((user) => {
                            this.viewerCount++;
                        })
                        .leaving((user) => {
                            this.viewerCount--;
                        })
                        .listen('ChatMessageSent', (e) => {
                            this.messages.push(e);
                            this.$nextTick(() => {
                                const container = document.getElementById('chatMonitor');
                                if (container) container.scrollTop = container.scrollHeight;
                            });
                        });
                },

                updateDuration() {
                    if (!this.startedAt) return;

                    const now = new Date();
                    const diff = Math.floor((now - this.startedAt) / 1000);

                    const hours = Math.floor(diff / 3600);
                    const minutes = Math.floor((diff % 3600) / 60);
                    const seconds = diff % 60;

                    this.streamDuration = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }
            }
        }
    </script>
    @endpush
</x-guest-layout>
