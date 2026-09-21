@props(['tip', 'action', 'method' => 'POST', 'submitLabel' => 'Enviar a revisión', 'intent' => 'submit', 'imageUrl' => null])
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5 p-5 sm:p-6" data-tip-form>
    @csrf
    @if($method !== 'POST') @method($method) @endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($tip->status === \App\Enums\TipStatus::REJECTED)
        <div class="rounded-lg bg-amber-50 p-4 text-amber-900">
            <p class="font-semibold">Motivo del rechazo</p><p>{{ $tip->rejection_reason }}</p>
            <p class="mt-2 text-sm">Al guardar la corrección se limpiarán el motivo y la revisión anterior. Puedes guardarla como borrador o enviarla de nuevo a revisión.</p>
        </div>
    @endif
    <div>
        <x-input-label for="title" value="Título" />
        <x-text-input id="title" name="title" class="mt-1.5 w-full text-base" :value="old('title', $tip->title)" placeholder="Ej. Tres claves para recuperarte después de entrenar" required minlength="3" maxlength="150" />
    </div>

    <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
        <div class="lg:col-span-2">
            <x-input-label for="type" value="Tipo" />
            <select id="type" name="type" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">@foreach(\App\Enums\TipType::labels() as $key => $label)<option value="{{ $key }}" @selected(old('type', $tip->type->value) === $key)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="lg:col-span-2">
            <x-input-label for="category" value="Categoría" />
            <select id="category" name="category" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">@foreach(\App\Enums\TipCategory::labels() as $key => $label)<option value="{{ $key }}" @selected(old('category', $tip->category->value) === $key)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="lg:col-span-3">
            <x-input-label for="expires_at" value="Fecha de expiración" />
            <input id="expires_at" type="datetime-local" name="expires_at" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="{{ old('expires_at', $tip->expires_at?->format('Y-m-d\TH:i')) }}" />
            <p class="mt-1 text-xs text-gray-500">Opcional; vacío significa que no expira.</p>
        </div>
        <div class="lg:col-span-5">
            <x-input-label for="image" value="Imagen" />
            <div class="mt-1.5 flex min-h-[82px] items-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 transition hover:border-indigo-400" data-tip-image-dropzone>
                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-gray-200">
                    <img
                        @if($imageUrl) src="{{ $imageUrl }}" @endif
                        alt="Vista previa de la imagen"
                        class="h-full w-full object-cover {{ $imageUrl ? '' : 'hidden' }}"
                        data-tip-image-preview
                    />
                    <svg data-tip-image-placeholder class="h-7 w-7 text-gray-400 {{ $imageUrl ? 'hidden' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm12-11.25h.008v.008h-.008V8.25Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="image" class="inline-flex cursor-pointer items-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-indigo-50">Seleccionar imagen</label>
                    <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" data-tip-image-input />
                    <p class="mt-1 truncate text-xs text-gray-500" data-tip-image-status>{{ $imageUrl ? 'Imagen actual. Selecciona otra para reemplazarla.' : 'JPG, PNG o WebP. La optimizaremos antes de subirla.' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between gap-3">
            <x-input-label for="body" value="Contenido" />
            <span class="text-xs text-gray-400"><span data-tip-body-count>0</span> / 10 000</span>
        </div>
        <textarea id="body" name="body" rows="8" required maxlength="10000" class="mt-1.5 w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Escribe el consejo o anuncio con instrucciones claras..." data-tip-body>{{ old('body', $tip->body) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Puedes separar el contenido en párrafos para facilitar la lectura en la app.</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 pt-5">
        <div>
            @if($tip->image_path)
                <label class="inline-flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('remove_image')) data-tip-remove-image /> Retirar imagen actual</label>
            @else
                <p class="text-xs text-gray-500">La publicación se guardará primero como borrador.</p>
            @endif
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <x-secondary-button type="submit" data-tip-submit>Guardar borrador</x-secondary-button>
            <x-primary-button name="intent" :value="$intent" data-tip-submit>{{ $submitLabel }}</x-primary-button>
        </div>
    </div>
</form>
