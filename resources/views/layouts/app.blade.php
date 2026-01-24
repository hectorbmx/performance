<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4I6C9vY0y5x5c5N1p5uW5t0kw=="
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
</body>

</html>
