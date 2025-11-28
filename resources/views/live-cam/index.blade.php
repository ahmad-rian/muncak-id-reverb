<x-layout.app>
    <x-slot:title>Live Monitoring Jalur Pendakian - Muncak.id</x-slot:title>

    <main class="min-h-screen bg-base-200 pt-24 pb-8">
        <div class="container mx-auto px-4">
            <!-- Hero Section -->
            <div class="mb-8 text-center">
                <h1 class="mb-2 font-merriweather text-4xl font-bold text-base-content">
                    Live Monitoring Jalur Pendakian
                </h1>
                <p class="text-lg text-base-content/70">
                    Pantau kondisi jalur pendakian secara real-time dengan AI
                </p>
            </div>

            <!-- ==================== SECTION 1: LIVE STREAMS ==================== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-base-content">Live Cam</h2>
                    @if ($liveStreams->count() > 0)
                        <span class="badge badge-error badge-lg gap-2">
                            <span class="relative flex h-3 w-3">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
                            </span>
                            {{ $liveStreams->count() }} Live Now
                        </span>
                    @endif
                </div>

                @if ($liveStreams->count() > 0)
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($liveStreams as $stream)
                            <a href="{{ route('live-cam.show', $stream->id) }}"
                                class="card bg-white shadow-md hover:shadow-lg transition-all hover:-translate-y-1">
                                <!-- Thumbnail -->
                                {{-- DEBUG: thumbnail_url = {{ $stream->thumbnail_url ?? 'NULL' }} --}}
                                <figure class="relative aspect-video bg-black overflow-hidden">
                                    @if($stream->thumbnail_url)
                                        <img src="{{ asset('storage/' . $stream->thumbnail_url) }}?v={{ time() }}"
                                            alt="{{ $stream->title }}" class="h-full w-full object-cover">
                                        <div class="hidden h-full w-full items-center justify-center text-white/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-white/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <!-- LIVE Badge -->
                                    @if($stream->isLive())
                                        <div class="absolute top-3 left-3">
                                            <span class="badge badge-error gap-1 font-semibold">
                                                <span class="relative flex h-2 w-2">
                                                    <span
                                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                                                </span>
                                                LIVE
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Viewer Count -->
                                    <div class="absolute top-3 right-3">
                                        <span class="badge badge-neutral gap-1 text-xs">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {{ $stream->viewer_count }}
                                        </span>
                                    </div>

                                    <!-- Quality Badge -->
                                    <div class="absolute bottom-3 right-3">
                                        <span
                                            class="badge badge-sm bg-black/50 text-white border-0">{{ $stream->quality }}</span>
                                    </div>
                                </figure>

                                <!-- Card Body -->
                                <div class="card-body p-4">
                                    <h3 class="card-title text-sm line-clamp-1">
                                        {{ $stream->jalur?->nama ?? $stream->title }}
                                    </h3>
                                    @if($stream->mountain)
                                        <p class="text-xs text-base-content/60">{{ $stream->mountain->nama }}</p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 bg-base-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-base-content/30" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-4 text-base-content/60">Belum ada live stream aktif</p>
                    </div>
                @endif
            </section>

            <!-- ==================== SECTION 2: AI CLASSIFICATION ==================== -->
            <section>
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-base-content mb-2">Kondisi Jalur Pendakian</h2>
                    <p class="text-sm text-base-content/60">Hasil klasifikasi AI berdasarkan live stream</p>
                </div>

                <!-- Search & Filter -->
                <div class="mb-6">
                    <form method="GET" action="{{ route('live-cam.index') }}" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <div class="join w-full">
                                <input type="text" name="search" value="{{ request('search') }}"
                                    placeholder="Cari jalur pendakian..."
                                    class="input input-bordered join-item w-full" />
                                <button type="submit" class="btn btn-primary join-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="w-full md:w-64">
                            <select name="jalur_id" class="select select-bordered w-full" onchange="this.form.submit()">
                                <option value="">Semua Jalur</option>
                                @foreach ($jalurs as $jalur)
                                    <option value="{{ $jalur->id }}" {{ request('jalur_id') == $jalur->id ? 'selected' : '' }}>
                                        {{ $jalur->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if(request('search') || request('jalur_id'))
                            <a href="{{ route('live-cam.index') }}" class="btn btn-ghost">Reset</a>
                        @endif
                    </form>
                </div>

                <!-- Classification Cards -->
                @if ($streams->count() > 0)
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($streams as $stream)
                            <div class="card bg-white shadow-md">
                                <!-- Image from classification -->
                                @if($stream->latestClassification && $stream->latestClassification->image_path)
                                    <figure class="aspect-video">
                                        <img src="{{ Storage::disk('public')->url($stream->latestClassification->image_path) }}"
                                            alt="Klasifikasi {{ $stream->jalur?->nama ?? $stream->title }}"
                                            class="h-full w-full object-cover">
                                    </figure>
                                @elseif($stream->thumbnail_url)
                                    <figure class="aspect-video">
                                        <img src="{{ Storage::disk('public')->url($stream->thumbnail_url) }}"
                                            alt="{{ $stream->title }}" class="h-full w-full object-cover">
                                    </figure>
                                @endif

                                <div class="card-body">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="card-title text-base">
                                                {{ $stream->jalur?->nama ?? $stream->title }}
                                            </h3>
                                            @if($stream->mountain)
                                                <p class="text-sm text-base-content/60">{{ $stream->mountain->nama }}</p>
                                            @endif
                                        </div>
                                        @if($stream->isLive())
                                            <span class="badge badge-error badge-sm gap-1">
                                                <span class="relative flex h-1.5 w-1.5">
                                                    <span
                                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span>
                                                </span>
                                                LIVE
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Classification Results -->
                                    @if($stream->latestClassification)
                                        <div class="mt-4 space-y-2">
                                            <p class="text-xs text-base-content/60">
                                                Update: {{ $stream->latestClassification->classified_at_wib }}
                                            </p>

                                            <div class="flex items-center gap-2">
                                                <span>{{ $stream->latestClassification->weather_icon }}</span>
                                                <span class="text-sm font-medium">Cuaca:</span>
                                                <span class="text-sm">{{ $stream->latestClassification->weather_label }}</span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span>👥</span>
                                                <span class="text-sm font-medium">Kepadatan:</span>
                                                <span class="text-sm">{{ $stream->latestClassification->crowd_label }}</span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span>👁️</span>
                                                <span class="text-sm font-medium">Visibilitas:</span>
                                                <span class="text-sm">{{ $stream->latestClassification->visibility_label }}</span>
                                            </div>

                                            <div class="mt-3 p-3 rounded-lg bg-base-200">
                                                <p class="text-sm">
                                                    <span
                                                        class="mr-1">{{ $stream->latestClassification->recommendation_icon }}</span>
                                                    "{{ $stream->latestClassification->recommendation }}"
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="mt-4 p-4 rounded-lg bg-base-200 text-center">
                                            <p class="text-sm text-base-content/60">Menunggu data klasifikasi...</p>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8">
                        {{ $streams->withQueryString()->links() }}
                    </div>
                @else
                    <div class="text-center py-12 bg-base-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-base-content/30" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="mt-4 text-base-content/60">
                            @if(request('search') || request('jalur_id'))
                                Tidak ditemukan data dengan filter tersebut
                            @else
                                Belum ada data klasifikasi
                            @endif
                        </p>
                        @if(request('search') || request('jalur_id'))
                            <a href="{{ route('live-cam.index') }}" class="btn btn-primary btn-sm mt-4">Reset Filter</a>
                        @endif
                    </div>
                @endif
            </section>

            <!-- Auto-refresh indicator -->
            <div class="mt-8 text-center">
                <span class="text-sm text-base-content/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Auto-refresh setiap 5 menit
                </span>
            </div>
        </div>
    </main>

    @push('scripts')
        <script>
            // Auto-refresh every 30 seconds for live streams, 5 minutes for classifications
            @if($liveStreams->count() > 0)
                setInterval(() => {
                    window.location.reload();
                }, 30 * 1000); // 30 seconds if there are live streams
            @else
                setInterval(() => {
                    window.location.reload();
                }, 5 * 60 * 1000); // 5 minutes otherwise
            @endif
        </script>
    @endpush
</x-layout.app>