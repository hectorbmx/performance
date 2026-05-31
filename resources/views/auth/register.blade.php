<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Athlete Core Coach</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900">
    <div class="min-h-screen flex">
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=1920&q=80"
                 alt="Gym Background"
                 class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900/70 via-gray-900/45 to-gray-900/85"></div>

            <div class="absolute inset-0 flex items-center justify-center">
                <div class="text-center text-white p-8">
                    <h2 class="text-5xl font-bold mb-4 tracking-tight">Start Coaching</h2>
                    <h2 class="text-5xl font-bold mb-6 tracking-tight">Today</h2>
                    <p class="text-xl text-gray-300 max-w-md mx-auto">
                        Create your trial workspace and activate it from your email.
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
                        <div class="w-32 h-32 mx-auto bg-white-500/20 rounded-2xl flex items-center justify-center backdrop-blur-sm border border-blue-500/30">
                            <img src="{{ asset('images/L2.png') }}" alt="Coach">
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-2 tracking-tight">ATHLETE PERFORMANCE COACH</h1>
                    <p class="text-gray-400 text-sm tracking-widest uppercase">Trial Registration</p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 backdrop-blur-sm">
                        <ul class="list-disc list-inside text-sm text-red-300 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-gray-800/30 backdrop-blur-xl rounded-2xl p-8 border border-gray-700/50 shadow-2xl">
                    <form method="POST" action="{{ route('register') }}" autocomplete="off">
                        @csrf

                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off" value="">
                            <label for="company_url">Company URL</label>
                            <input id="company_url" type="text" name="company_url" tabindex="-1" autocomplete="off" value="">
                            <label for="work_email_confirmation">Work email confirmation</label>
                            <input id="work_email_confirmation" type="email" name="work_email_confirmation" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <div class="mb-5">
                            <label for="name" class="block text-white text-sm font-medium mb-2">Nombre completo</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                   class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                   placeholder="Tu nombre">
                        </div>

                        <div class="mb-5">
                            <label for="display_name" class="block text-white text-sm font-medium mb-2">Nombre del negocio</label>
                            <input id="display_name" type="text" name="display_name" value="{{ old('display_name') }}" required autocomplete="organization"
                                   class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                   placeholder="Performance Studio">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="mb-5">
                                <label for="email" class="block text-white text-sm font-medium mb-2">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                                       class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="coach@email.com">
                            </div>

                            <div class="mb-5">
                                <label for="phone" class="block text-white text-sm font-medium mb-2">Telefono</label>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" autocomplete="tel"
                                       class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="Opcional">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="mb-6">
                                <label for="password" class="block text-white text-sm font-medium mb-2">Password</label>
                                <input id="password" type="password" name="password" required autocomplete="new-password"
                                       class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="Minimo 8 caracteres">
                            </div>

                            <div class="mb-6">
                                <label for="password_confirmation" class="block text-white text-sm font-medium mb-2">Confirmar</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                       class="w-full px-4 py-3.5 bg-gray-900/50 border border-gray-600/50 rounded-xl text-gray-300 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="Repite password">
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full py-4 bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/30 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]">
                            Crear cuenta de prueba
                        </button>
                    </form>
                </div>

                <div class="mt-8 text-center">
                    <p class="text-gray-500 text-sm">
                        Ya tienes cuenta?
                        <a href="{{ route('login') }}" class="text-blue-400 hover:text-blue-300 transition-colors font-medium">
                            Inicia sesion
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
