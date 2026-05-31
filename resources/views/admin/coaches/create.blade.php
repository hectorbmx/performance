<x-app-layout>
    {{-- Toast de Éxito --}}
    @if (session('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed top-5 right-5 z-50 max-w-sm w-full bg-white shadow-2xl rounded-2xl border border-emerald-100 p-4 flex items-start gap-3"
             style="display: none;">
            <div class="flex-shrink-0 p-1.5 bg-emerald-100 rounded-xl text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1 pt-0.5">
                <p class="text-sm font-bold text-gray-900">¡Éxito!</p>
                <p class="text-xs text-gray-500 mt-1">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="py-10 bg-gray-50/30 min-h-screen">
        {{-- Contenedor más ancho: max-w-7xl --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Encabezado con diseño --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Dar de alta nuevo Coach</h1>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Crea las credenciales de acceso y perfil para los entrenadores de tu plataforma.
                    </p>
                </div>

                <a href="{{ route('admin.coaches.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition-all focus:ring-2 focus:ring-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Regresar
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-8 bg-red-50 border-l-4 border-red-500 rounded-r-xl p-4 flex gap-3 shadow-sm">
                    <div class="text-red-500">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-red-800 tracking-tight">Hubo un problema con el registro</h4>
                        <ul class="text-xs text-red-700 mt-1 list-disc list-inside opacity-80">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.coaches.store') }}">
                @csrf
                
                {{-- Grid Principal de 2 columnas --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {{-- Columna Izquierda: Datos de Acceso --}}
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </span>
                                Información de Cuenta
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Completo</label>
                                    <input type="text" name="name" value="{{ old('name') }}" required
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                                           placeholder="Ej. Juan Pérez">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Correo Electrónico</label>
                                    <input type="email" name="email" value="{{ old('email') }}" required
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                                           placeholder="coach@ejemplo.com">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Contraseña de acceso</label>
                                    <input type="password" name="password" required
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                                           placeholder="••••••••">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Confirmar Contraseña</label>
                                    <input type="password" name="password_confirmation" required
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                                           placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        {{-- Sección inferior: Botones --}}
                        <div class="flex items-center justify-between p-2">
                            <p class="text-xs text-gray-400 italic max-w-sm">
                                Se enviará un correo de bienvenida al Coach con sus instrucciones de acceso una vez guardado.
                            </p>
                            <div class="flex gap-3">
                                <a href="{{ route('admin.coaches.index') }}" class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
                                    Cancelar
                                </a>
                                <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                    Guardar Coach
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Columna Derecha: Perfil Público y Status --}}
                    <div class="space-y-6">
                        <div class="bg-indigo-900 rounded-2xl shadow-xl p-6 md:p-8 text-white relative overflow-hidden">
                            {{-- Decoración de fondo --}}
                            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-indigo-800 rounded-full opacity-50"></div>
                            
                            <h3 class="text-lg font-bold mb-6 flex items-center gap-2 relative z-10">
                                <span class="w-8 h-8 rounded-lg bg-indigo-500/30 text-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </span>
                                Perfil Público
                            </h3>

                            <div class="space-y-5 relative z-10">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Nombre para mostrar</label>
                                    <input type="text" name="display_name" value="{{ old('display_name') }}" required
                                           class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white placeholder-indigo-300 transition-all"
                                           placeholder="Ej. Coach Juan">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Teléfono de contacto</label>
                                    <input type="text" name="phone" value="{{ old('phone') }}"
                                           class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white placeholder-indigo-300 transition-all"
                                           placeholder="+52 ...">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Estatus inicial</label>
                                    <select name="status" 
                                            class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white transition-all appearance-none cursor-pointer">
                                        <option value="active" class="text-gray-900">Activo</option>
                                        <option value="inactive" class="text-gray-900">Inactivo</option>
                                        <option value="trial" class="text-gray-900">Trial</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Card Informativa extra --}}
                        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-6">
                            <div class="flex gap-3 items-start">
                                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800">Nota de Estatus</p>
                                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                        El estatus <b>Trial</b> permite al coach usar la plataforma por 7 días de forma gratuita antes de requerir activación manual.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>