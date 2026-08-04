<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contrasena creada | Athlete Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased">
    <main class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1400&q=80')] bg-cover bg-center"></div>
        <div class="absolute inset-0 bg-black/80"></div>

        <section class="relative z-10 flex min-h-screen items-center justify-center px-5 py-10">
            <div class="w-full max-w-md rounded-2xl border border-blue-300/15 bg-slate-950/95 p-7 text-center shadow-2xl shadow-black/60 backdrop-blur">
                <img src="{{ asset('images/L3.png') }}" alt="Athlete Core" class="mx-auto h-14 w-14 rounded-xl object-contain">
                <h1 class="mt-5 text-2xl font-bold tracking-[0.14em]">CONTRASENA CREADA</h1>
                <p class="mt-4 text-sm leading-6 text-slate-300">
                    Tu acceso quedo listo para <span class="font-semibold text-white">{{ $email }}</span>.
                    Ahora abre la app Athlete Core e inicia sesion con tu correo y contrasena.
                </p>
                <div class="mt-6 rounded-xl border border-blue-200/15 bg-slate-900/80 px-4 py-3 text-sm text-blue-100">
                    Esta cuenta es de atleta y no tiene acceso al panel web de coach.
                </div>
            </div>
        </section>
    </main>
</body>
</html>
