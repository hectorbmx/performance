<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      
        crossorigin="anonymous" referrerpolicy="no-referrer" />

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
  <body class="font-sans antialiased bg-gray-100">
    <div class="flex">

        {{-- Sidebar --}}
        {{-- @include('layouts.sidebar') --}}
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            @include('layouts.sidebar-admin')
        @elseif(auth()->check() && auth()->user()->hasRole('coach'))
            @include('layouts.sidebar-coach')
        @endif


        {{-- Main --}}
        <div class="flex-1 min-h-screen">
            @include('layouts.navigation')

            <main class="p-6">
                {{ $slot }}
            </main>
        </div>

    </div>
    <div class="fixed right-4 top-4 z-[60] w-full max-w-sm space-y-3">
        @if(session('success'))
            <div data-toast
                 class="rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-800 shadow-lg">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.2a1 1 0 01-1.41 0L3.296 9.19a1 1 0 111.408-1.42l4.045 4.016 6.547-6.497a1 1 0 011.408 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-emerald-900">Operacion completada</p>
                        <p class="mt-0.5 text-emerald-700">{{ session('success') }}</p>
                    </div>
                    <button type="button" data-toast-close class="rounded-md p-1 text-emerald-600 hover:bg-emerald-50" aria-label="Cerrar notificacion">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div data-toast
                 class="rounded-lg border border-red-200 bg-white px-4 py-3 text-sm text-red-800 shadow-lg">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.5a.75.75 0 001.5 0v-4.5zM10 14.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-red-900">No se pudo completar</p>
                        <p class="mt-0.5 text-red-700">{{ session('error') }}</p>
                    </div>
                    <button type="button" data-toast-close class="rounded-md p-1 text-red-600 hover:bg-red-50" aria-label="Cerrar notificacion">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.querySelectorAll('[data-toast]').forEach((toast) => {
            const closeButton = toast.querySelector('[data-toast-close]');
            const dismiss = () => toast.remove();

            closeButton?.addEventListener('click', dismiss);
            window.setTimeout(dismiss, 4500);
        });
    </script>
    @stack('scripts')
</body>

</html>
