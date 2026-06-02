<x-app-layout>
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Encabezado -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Nuevo cliente</h1>
                    <p class="text-sm text-slate-500 mt-1">Registra los datos de un nuevo miembro en tu plataforma.</p>
                </div>
                <a href="{{ route('coach.clients.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors duration-150 group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver
                </a>
            </div>

            <!-- Formulario -->
            <form method="POST" action="{{ route('coach.clients.store') }}"
                  class="bg-white border border-slate-200/80 shadow-sm rounded-xl p-6 sm:p-8 space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
                        <input name="first_name" type="text" value="{{ old('first_name') }}"
                               class="w-full rounded-lg border-slate-300 text-slate-800 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 transition duration-150"
                               placeholder="Ej. Juan" required>
                        @error('first_name') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Apellido -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Apellido</label>
                        <input name="last_name" type="text" value="{{ old('last_name') }}"
                               class="w-full rounded-lg border-slate-300 text-slate-800 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 transition duration-150"
                               placeholder="Ej. Pérez">
                        @error('last_name') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                        <div class="relative shadow-sm rounded-lg">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2 6.75A2.75 2.75 0 014.75 4h14.5A2.75 2.75 0 0122 6.75v10.5A2.75 2.75 0 0119.25 20H4.75A2.75 2.75 0 012 17.25V6.75zm2.75-1.25c-.3 0-.57.1-.78.27l7.35 5.14c.41.29.95.29 1.36 0l7.35-5.14a1.24 1.24 0 00-.78-.27H4.75zm15.75 2.03l-6.96 4.87a2.75 2.75 0 01-3.08 0L3.5 7.53v9.72c0 .69.56 1.25 1.25 1.25h14.5c.69 0 1.25-.56 1.25-1.25V7.53z"/>
                                </svg>
                            </span>
                            <input name="email" type="email" value="{{ old('email') }}"
                                   data-validate="email" pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                   class="w-full rounded-lg border-slate-300 pl-9 text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 transition duration-150"
                                   placeholder="cliente@email.com">
                        </div>
                        <p class="mt-1.5 hidden text-xs font-medium" data-feedback-for="email"></p>
                        @error('email') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Celular -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Celular</label>
                        <div class="relative shadow-sm rounded-lg">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-emerald-500/90">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.52 3.48A11.84 11.84 0 0012.08 0C5.52 0 .18 5.34.18 11.9c0 2.1.55 4.15 1.6 5.96L0 24l6.3-1.65a11.88 11.88 0 005.78 1.47h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.47-8.44zM12.09 21.8h-.01a9.9 9.9 0 01-5.05-1.38l-.36-.21-3.74.98 1-3.64-.24-.37a9.86 9.86 0 01-1.51-5.27c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.13 1.03 7 2.9a9.84 9.84 0 012.9 7c0 5.45-4.44 9.88-9.89 9.88zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.39-1.47a8.95 8.95 0 01-1.65-2.05c-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.07 2.9 1.22 3.1c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35z"/>
                                </svg>
                            </span>
                            <input name="phone" value="{{ old('phone') }}"
                                   data-validate="phone" pattern="^(?:\+52\s?)?(?:\d{10}|(?:\d{2}\s?){5})$"
                                   class="w-full rounded-lg border-slate-300 pl-9 text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 transition duration-150"
                                   placeholder="5512345678">
                        </div>
                        <p class="mt-1.5 hidden text-xs font-medium" data-feedback-for="phone"></p>
                        @error('phone') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado</label>
                        <select name="state" class="w-full rounded-lg border-slate-300 text-slate-800 focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 shadow-sm transition duration-150">
                            <option value="" class="text-slate-400">Selecciona un estado</option>
                            @foreach($mexicoStates as $state)
                                <option value="{{ $state }}" {{ old('state') === $state ? 'selected' : '' }}>
                                    {{ $state }}
                                </option>
                            @endforeach
                        </select>
                        @error('state') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Ciudad -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Ciudad</label>
                        <input name="city" value="{{ old('city') }}"
                               class="w-full rounded-lg border-slate-300 text-slate-800 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 transition duration-150"
                               placeholder="Ej. Guadalajara">
                        @error('city') <p class="mt-1.5 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <hr class="border-slate-100 my-2">

                <!-- Checkbox Activo -->
                <div class="flex items-center">
                    <label class="relative flex items-center cursor-pointer select-none group">
                        <input type="checkbox" name="is_active" value="1" checked
                               class="h-4.5 w-4.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 transition duration-150">
                        <span class="ml-2.5 text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Cliente activo</span>
                    </label>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end items-center gap-3 pt-2">
                    <a href="{{ route('coach.clients.index') }}"
                       class="px-4 py-2.5 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-all duration-150 shadow-sm">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-150 shadow-sm shadow-indigo-500/10">
                        Guardar cliente
                    </button>
                </div>
            </form>

        </div>
    </div>

    @include('coach.clients.partials.contact-validation')
</x-app-layout>