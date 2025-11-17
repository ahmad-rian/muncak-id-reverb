<x-layout.admin>
    <div class="container mx-auto max-w-3xl px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('admin.live-stream.index') }}" class="btn btn-ghost btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Streams
            </a>
            <h1 class="mt-4 text-3xl font-bold text-gray-800">Create New Stream</h1>
            <p class="mt-2 text-gray-600">Set up a new live stream broadcast</p>
        </div>

        <!-- Form Card -->
        <div class="card bg-base-100 shadow-lg">
            <div class="card-body">
                <form action="{{ route('admin.live-stream.store') }}" method="POST">
                    @csrf

                    <!-- Title -->
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text font-semibold">Stream Title <span class="text-error">*</span></span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            placeholder="e.g., Summit View - Mount Semeru"
                            class="input input-bordered w-full @error('title') input-error @enderror"
                            value="{{ old('title') }}"
                            required
                        />
                        @error('title')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        <label class="label">
                            <span class="label-text-alt">A descriptive title for your live stream</span>
                        </label>
                    </div>

                    <!-- Mountain -->
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text font-semibold">Mountain (Optional)</span>
                        </label>
                        <select
                            name="mountain_id"
                            class="select select-bordered w-full @error('mountain_id') select-error @enderror"
                        >
                            <option value="">-- Select Mountain --</option>
                            @foreach ($mountains as $mountain)
                                <option value="{{ $mountain->id }}" {{ old('mountain_id') == $mountain->id ? 'selected' : '' }}>
                                    {{ $mountain->nama }}
                                </option>
                            @endforeach
                        </select>
                        @error('mountain_id')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        <label class="label">
                            <span class="label-text-alt">Select the mountain if this stream is location-specific</span>
                        </label>
                    </div>

                    <!-- Description -->
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text font-semibold">Description (Optional)</span>
                        </label>
                        <textarea
                            name="description"
                            placeholder="Describe what viewers will see in this stream..."
                            class="textarea textarea-bordered h-24 @error('description') textarea-error @enderror"
                            maxlength="1000"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        <label class="label">
                            <span class="label-text-alt">Maximum 1000 characters</span>
                        </label>
                    </div>

                    <!-- Quality -->
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text font-semibold">Stream Quality <span class="text-error">*</span></span>
                        </label>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="label cursor-pointer justify-start gap-3 rounded-lg border p-4 hover:border-primary @error('quality') border-error @enderror">
                                <input
                                    type="radio"
                                    name="quality"
                                    value="360p"
                                    class="radio radio-primary"
                                    {{ old('quality', '720p') == '360p' ? 'checked' : '' }}
                                />
                                <div>
                                    <span class="block font-semibold">360p</span>
                                    <span class="block text-xs text-gray-500">Low bandwidth</span>
                                </div>
                            </label>

                            <label class="label cursor-pointer justify-start gap-3 rounded-lg border p-4 hover:border-primary @error('quality') border-error @enderror">
                                <input
                                    type="radio"
                                    name="quality"
                                    value="720p"
                                    class="radio radio-primary"
                                    {{ old('quality', '720p') == '720p' ? 'checked' : '' }}
                                />
                                <div>
                                    <span class="block font-semibold">720p HD</span>
                                    <span class="block text-xs text-gray-500">Recommended</span>
                                </div>
                            </label>

                            <label class="label cursor-pointer justify-start gap-3 rounded-lg border p-4 hover:border-primary @error('quality') border-error @enderror">
                                <input
                                    type="radio"
                                    name="quality"
                                    value="1080p"
                                    class="radio radio-primary"
                                    {{ old('quality', '720p') == '1080p' ? 'checked' : '' }}
                                />
                                <div>
                                    <span class="block font-semibold">1080p FHD</span>
                                    <span class="block text-xs text-gray-500">High quality</span>
                                </div>
                            </label>
                        </div>
                        @error('quality')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        <label class="label">
                            <span class="label-text-alt">Choose the video quality for your stream</span>
                        </label>
                    </div>

                    <!-- Info Box -->
                    <div class="alert alert-info mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="h-6 w-6 shrink-0 stroke-current">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h3 class="font-bold">Stream Key</h3>
                            <div class="text-xs">A unique stream key will be automatically generated for this broadcast. You'll see it on the broadcast dashboard.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card-actions mt-8 justify-end gap-2">
                        <a href="{{ route('admin.live-stream.index') }}" class="btn btn-ghost">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Stream
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card bg-base-100 shadow-lg mt-6">
            <div class="card-body">
                <h2 class="card-title">What happens next?</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>A unique stream key will be generated for security</span>
                    </li>
                    <li class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>You'll be redirected to the broadcast dashboard</span>
                    </li>
                    <li class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Allow camera and microphone permissions</span>
                    </li>
                    <li class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Start broadcasting to your viewers!</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</x-layout.admin>
