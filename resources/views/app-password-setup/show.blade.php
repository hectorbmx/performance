<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crear contrasena | Athlete Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased">
    <main class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1400&q=80')] bg-cover bg-center"></div>
        <div class="absolute inset-0 bg-black/80"></div>

        <section class="relative z-10 flex min-h-screen items-center justify-center px-5 py-10">
            <div class="w-full max-w-md rounded-2xl border border-blue-300/15 bg-slate-950/95 p-7 shadow-2xl shadow-black/60 backdrop-blur">
                <div class="mb-8 text-center">
                    <img src="{{ asset('images/L3.png') }}" alt="Athlete Core" class="mx-auto h-14 w-14 rounded-xl object-contain">
                    <h1 class="mt-5 text-2xl font-bold tracking-[0.18em]">ATHLETE CORE</h1>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.22em] text-blue-200/70">Crear acceso a la app</p>
                </div>

                <form method="POST" action="{{ route('app.password-setup.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-100">Correo electronico</label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email', $email) }}"
                               readonly
                               required
                               autocomplete="username"
                               class="block w-full rounded-xl border border-blue-200/15 bg-slate-900/80 px-4 py-3 text-white shadow-sm outline-none transition focus:border-blue-300/60 focus:ring-4 focus:ring-blue-400/10 read-only:text-slate-300">
                        @error('email')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-100">Contrasena</label>
                        <input id="password"
                               type="password"
                               name="password"
                               required
                               autocomplete="new-password"
                               class="block w-full rounded-xl border border-blue-200/15 bg-slate-900/80 px-4 py-3 text-white shadow-sm outline-none transition focus:border-blue-300/60 focus:ring-4 focus:ring-blue-400/10">
                        @error('password')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-100">Confirmar contrasena</label>
                        <input id="password_confirmation"
                               type="password"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               class="block w-full rounded-xl border border-blue-200/15 bg-slate-900/80 px-4 py-3 text-white shadow-sm outline-none transition focus:border-blue-300/60 focus:ring-4 focus:ring-blue-400/10">
                    </div>

                    <p class="text-sm leading-6 text-slate-300">
                        Este enlace crea tu contrasena de acceso. Despues podras iniciar sesion en la app con este correo.
                    </p>

                    <button type="submit"
                            class="w-full rounded-xl bg-blue-500 px-4 py-3 text-sm font-bold uppercase tracking-[0.14em] text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-400/20">
                        Crear contrasena
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
