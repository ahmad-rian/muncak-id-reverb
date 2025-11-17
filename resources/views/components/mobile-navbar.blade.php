<div class="dropdown dropdown-end dropdown-bottom md:hidden">
  <div class="btn btn-ghost lg:hidden" tabindex="0" role="button">
    <x-gmdi-menu-r class="size-5" />
  </div>

  <ul class="dropdown-content menu menu-sm z-[1] mt-3 w-52 space-y-2 rounded-box bg-base-100 p-2 shadow" tabindex="0">
    <div class="btn btn-ghost btn-sm" tabindex="0" role="button" x-on:click="toggleTheme">
      <x-gmdi-wb-sunny-o class="size-5" x-show="theme === 'winter'" x-cloak />
      <x-gmdi-nights-stay-r class="size-5" x-show="theme === 'dark-winter'" x-cloak />
    </div>
    <li><a class="justify-center" href="{{ route('index') }}">Home</a></li>
    <li><a class="justify-center" href="{{ route('jelajah.index') }}">Jelajah</a></li>
    <li><a class="justify-center" href="{{ route('blog.index') }}">Artikel</a></li>
    <li><a class="justify-center" href="{{ route('live-cam.index') }}">
      <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
      </svg>
      Webcam
    </a></li>
    @auth
      @role('admin')
        <li><a class="justify-center" href="{{ route('index') }}">App</a></li>
        <li><a class="justify-center" href="{{ route('admin.dashboard.index') }}">Admin</a></li>
      @endrole
      <li><a class="justify-center" href="{{ route('profile.index') }}">Profile</a></li>
      <li>
        <form class="flex items-stretch" method="POST" action="{{ route('auth.sign-out') }}">
          @csrf
          <button class="w-full" type="submit">
            Sign Out
          </button>
        </form>
      </li>
    @endauth
    @guest
      <li><a class="btn btn-outline btn-primary btn-sm" href="{{ route('auth.sign-in') }}">Masuk</a></li>
      <li><a class="btn btn-primary btn-sm" href="{{ route('auth.sign-up') }}">Daftar</a></li>
    @endguest
  </ul>
</div>
