<x-layout.admin>
    <x-slot:title>Test Classification - Gemini AI</x-slot:title>

    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Test Classification API</h1>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Capture Section -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">Capture & Classify</h2>

                    <!-- Stream Selection -->
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Select Stream</span>
                        </label>
                        <select id="streamSelect" class="select select-bordered">
                            @foreach($streams as $stream)
                                <option value="{{ $stream->id }}">
                                    {{ $stream->jalur?->nama ?? $stream->title }} ({{ $stream->mountain?->nama ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Video Preview -->
                    <div class="relative aspect-video bg-black rounded-lg overflow-hidden mb-4">
                        <video id="videoPreview" autoplay playsinline muted class="w-full h-full object-cover"></video>
                        <div id="noCamera" class="absolute inset-0 flex items-center justify-center text-white/50">
                            <span>Camera not active</span>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="flex gap-2 mb-4">
                        <button id="startCamera" class="btn btn-primary flex-1">Start Camera</button>
                        <button id="captureBtn" class="btn btn-success flex-1" disabled>Capture & Classify</button>
                    </div>

                    <!-- Status -->
                    <div id="status" class="text-sm text-base-content/60"></div>
                </div>
            </div>

            <!-- Result Section -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">Classification Result</h2>

                    <!-- Captured Image -->
                    <div id="capturedImageContainer" class="hidden mb-4">
                        <img id="capturedImage" class="w-full rounded-lg" alt="Captured frame">
                    </div>

                    <!-- Results -->
                    <div id="resultContainer" class="hidden space-y-3">
                        <div class="flex items-center gap-2">
                            <span id="weatherIcon" class="text-2xl"></span>
                            <div>
                                <p class="text-sm font-medium">Cuaca</p>
                                <p id="weatherResult" class="text-lg"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-2xl">👥</span>
                            <div>
                                <p class="text-sm font-medium">Kepadatan</p>
                                <p id="crowdResult" class="text-lg"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-2xl">👁️</span>
                            <div>
                                <p class="text-sm font-medium">Visibilitas</p>
                                <p id="visibilityResult" class="text-lg"></p>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="text-sm text-base-content/60">
                            <p>Classified at: <span id="classifiedAt"></span></p>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div id="loadingIndicator" class="hidden text-center py-8">
                        <span class="loading loading-spinner loading-lg"></span>
                        <p class="mt-2">Classifying with Gemini AI...</p>
                    </div>

                    <!-- Error -->
                    <div id="errorContainer" class="hidden alert alert-error">
                        <span id="errorMessage"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot:js>
        <script>
        let stream = null;

        document.getElementById('startCamera').addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 1280, height: 720 },
                    audio: false
                });

                const video = document.getElementById('videoPreview');
                video.srcObject = stream;
                document.getElementById('noCamera').classList.add('hidden');
                document.getElementById('captureBtn').disabled = false;
                document.getElementById('status').textContent = 'Camera ready';
            } catch (err) {
                document.getElementById('status').textContent = 'Error: ' + err.message;
            }
        });

        document.getElementById('captureBtn').addEventListener('click', async () => {
            const video = document.getElementById('videoPreview');
            const streamId = document.getElementById('streamSelect').value;

            // Capture frame
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            const base64Image = canvas.toDataURL('image/jpeg', 0.8).split(',')[1];

            // Show captured image
            document.getElementById('capturedImage').src = canvas.toDataURL('image/jpeg', 0.8);
            document.getElementById('capturedImageContainer').classList.remove('hidden');

            // Show loading
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('resultContainer').classList.add('hidden');
            document.getElementById('errorContainer').classList.add('hidden');
            document.getElementById('status').textContent = 'Sending to Gemini AI...';

            try {
                const response = await fetch(`/admin/live-stream/${streamId}/classify-frame`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        image: base64Image,
                        timestamp: Date.now()
                    })
                });

                const data = await response.json();

                document.getElementById('loadingIndicator').classList.add('hidden');

                if (data.success) {
                    // Show results
                    document.getElementById('resultContainer').classList.remove('hidden');

                    // Weather
                    const weatherIcons = { 'cerah': '☀️', 'berawan': '⛅', 'hujan': '🌧️' };
                    document.getElementById('weatherIcon').textContent = weatherIcons[data.classification.weather] || '❓';
                    document.getElementById('weatherResult').textContent = data.classification.weather;

                    // Crowd
                    document.getElementById('crowdResult').textContent = data.classification.crowd_density;

                    // Visibility
                    document.getElementById('visibilityResult').textContent = data.classification.visibility;

                    // Time
                    document.getElementById('classifiedAt').textContent = data.classification.classified_at;

                    document.getElementById('status').textContent = 'Classification complete!';
                } else {
                    document.getElementById('errorContainer').classList.remove('hidden');
                    document.getElementById('errorMessage').textContent = data.error || 'Classification failed';
                    document.getElementById('status').textContent = 'Error occurred';
                }
            } catch (err) {
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('errorContainer').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = err.message;
                document.getElementById('status').textContent = 'Error occurred';
            }
        });
        </script>
    </x-slot:js>
</x-layout.admin>
