<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu correo - Training Flow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900">
    <div class="min-h-screen flex">
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=1920&q=80"
                 alt="Entrenamiento funcional"
                 class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900/75 via-gray-900/50 to-gray-900/90"></div>

            <div class="absolute inset-0 flex items-center justify-center">
                <div class="text-center text-white p-8">
                    <p class="text-blue-300 text-sm tracking-[0.35em] uppercase mb-5">Training Flow</p>
                    <h1 class="text-5xl font-bold mb-4 tracking-tight">Un paso más</h1>
                    <h2 class="text-5xl font-bold mb-6 tracking-tight">para activar tu cuenta</h2>
                    <p class="text-xl text-gray-300 max-w-md mx-auto">
                        Confirma tu correo y entra a tu panel para organizar atletas, planes y entrenamientos.
                    </p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12 bg-gradient-to-br from-gray-900 via-[#1a2332] to-gray-900 relative">
            <div class="absolute top-20 right-1/2 transform translate-x-1/2 w-80 h-80 bg-blue-500/5 rounded-full blur-3xl"></div>
            <div class="absolute top-40 right-1/2 transform translate-x-1/2 w-60 h-60 bg-blue-400/5 rounded-full blur-3xl"></div>

            <div class="w-full max-w-md relative z-10">
                <div class="text-center mb-8">
                    <div class="mb-5">
                        <div class="w-32 h-32 mx-auto bg-white/5 rounded-2xl flex items-center justify-center backdrop-blur-sm border border-blue-500/30">
                            <img src="{{ asset('images/L2.png') }}" alt="Training Flow">
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-2 tracking-tight">REVISA TU CORREO</h1>
                    <p class="text-gray-400 text-sm tracking-widest uppercase">Activación de cuenta</p>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 backdrop-blur-sm">
                        <p class="text-sm text-emerald-300">
                            Te enviamos un nuevo enlace de verificación. Revisa tu bandeja de entrada y spam.
                        </p>
                    </div>
                @elseif (session('status'))
                    <div class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/30 backdrop-blur-sm">
                        <p class="text-sm text-blue-200">{{ session('status') }}</p>
                    </div>
                @endif

                <div class="bg-gray-800/30 backdrop-blur-xl rounded-2xl p-8 border border-gray-700/50 shadow-2xl">
                    <div class="space-y-5">
                        <div>
                            <p class="text-white text-lg font-semibold mb-2">Tu cuenta ya está creada.</p>
                            <p class="text-gray-300 leading-relaxed">
                                Para proteger tu acceso, necesitamos confirmar que este correo te pertenece. Te enviamos un enlace de verificación; ábrelo para activar tu panel.
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-700/70 bg-gray-900/45 p-4">
                            <p class="text-xs uppercase tracking-widest text-gray-500 mb-1">Correo registrado</p>
                            <p class="text-blue-200 font-medium break-all">{{ Auth::user()?->email }}</p>
                        </div>

                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full py-4 bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/30 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]">
                                Reenviar correo de verificación
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="text-center">
                            @csrf
                            <button type="submit" class="text-sm text-gray-400 hover:text-blue-300 transition-colors">
                                Cerrar sesión y usar otra cuenta
                            </button>
                        </form>
                    </div>
                </div>

                <p class="mt-8 text-center text-gray-500 text-sm">
                    Si no lo ves en unos minutos, revisa promociones o spam.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
