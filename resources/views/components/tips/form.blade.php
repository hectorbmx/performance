@props(['tip', 'action', 'method' => 'POST', 'submitLabel' => 'Enviar a revisión', 'intent' => 'submit'])
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    @if($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($tip->status === \App\Enums\TipStatus::REJECTED)
        <div class="rounded-lg bg-amber-50 p-4 text-amber-900">
            <p class="font-semibold">Motivo del rechazo</p><p>{{ $tip->rejection_reason }}</p>
            <p class="mt-2 text-sm">Al guardar la corrección se limpiarán el motivo y la revisión anterior. Puedes guardarla como borrador o enviarla de nuevo a revisión.</p>
        </div>
    @endif
    <div><x-input-label for="title" value="Título" /><x-text-input id="title" name="title" class="mt-1 w-full" :value="old('title', $tip->title)" required minlength="3" maxlength="150" /></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><x-input-label for="type" value="Tipo" /><select id="type" name="type" class="mt-1 w-full rounded-md border-gray-300">@foreach(\App\Enums\TipType::labels() as $key => $label)<option value="{{ $key }}" @selected(old('type', $tip->type->value) === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><x-input-label for="category" value="Categoría" /><select id="category" name="category" class="mt-1 w-full rounded-md border-gray-300">@foreach(\App\Enums\TipCategory::labels() as $key => $label)<option value="{{ $key }}" @selected(old('category', $tip->category->value) === $key)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div>
        <x-input-label for="expires_at" value="Fecha de expiración opcional" />
        <input id="expires_at" type="datetime-local" name="expires_at" class="mt-1 w-full rounded-md border-gray-300" value="{{ old('expires_at', $tip->expires_at?->format('Y-m-d\TH:i')) }}" />
        <p class="mt-1 text-sm text-gray-500">Al pasar esta fecha, el Tip deja de aparecer en la app. Si queda vacío, no expira automáticamente.</p>
    </div>
    <div><x-input-label for="body" value="Contenido" /><textarea id="body" name="body" rows="10" required maxlength="10000" class="mt-1 w-full rounded-md border-gray-300">{{ old('body', $tip->body) }}</textarea><p class="text-sm text-gray-500">Hasta 10 000 caracteres. Puedes separar el texto en párrafos.</p></div>
    <div><x-input-label for="image" value="Imagen opcional" /><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full" /><p class="mt-1 text-sm text-gray-500">JPG, PNG o WebP. Máximo 5 MB y 4096 × 4096 píxeles.</p></div>
    @if($tip->image_path)<label class="flex items-center gap-2"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image')) /> Retirar imagen actual</label>@endif
    <div class="flex flex-wrap gap-3"><x-secondary-button type="submit">Guardar borrador</x-secondary-button><x-primary-button name="intent" :value="$intent">{{ $submitLabel }}</x-primary-button></div>
</form>
