<x-app-layout>
    <div class="max-w-4xl mx-auto space-y-5">
        <a href="{{ $tip->scope === \App\Enums\TipScope::TENANT ? route('admin.tips.pending') : route('admin.tips.index') }}" class="text-indigo-700">← Volver a Tips</a>
        <p class="text-sm text-gray-600">Autor: {{ $tip->author->name }} · {{ $tip->scope === \App\Enums\TipScope::GLOBAL ? 'Global' : 'Atletas del coach' }}</p>
        <div class="rounded-xl border bg-white p-6"><x-tips.preview :tip="$tip" :image-url="$tip->image_path ? route('admin.tips.image', $tip) : null" /></div>
        @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if($tip->rejection_reason)<div class="rounded-lg bg-amber-50 p-4"><strong>Motivo del rechazo</strong><p>{{ $tip->rejection_reason }}</p></div>@endif
        <div class="flex flex-wrap gap-3">
            @if($tip->author_id === auth()->id() && $tip->status === \App\Enums\TipStatus::DRAFT)
                <a href="{{ route('admin.tips.edit', $tip) }}" class="rounded-lg border bg-white px-4 py-2">Editar</a>
                <form method="POST" action="{{ route('admin.tips.transition', [$tip, 'publish']) }}" onsubmit="return confirm('¿Publicar este anuncio global?')">@csrf<x-primary-button>Publicar</x-primary-button></form>
            @endif
            @if($tip->scope === \App\Enums\TipScope::TENANT && $tip->status === \App\Enums\TipStatus::PENDING_APPROVAL)
                <form method="POST" action="{{ route('admin.tips.transition', [$tip, 'approve']) }}" onsubmit="return confirm('¿Aprobar este contenido para los atletas del coach?')">@csrf<x-primary-button>Aprobar</x-primary-button></form>
            @endif
            @if($tip->status !== \App\Enums\TipStatus::ARCHIVED)
                <form method="POST" action="{{ route('admin.tips.transition', [$tip, 'archive']) }}" onsubmit="return confirm('¿Archivar esta publicación? Dejará de estar disponible.')">@csrf<x-secondary-button type="submit">Archivar</x-secondary-button></form>
            @elseif($tip->author_id === auth()->id())
                <form method="POST" action="{{ route('admin.tips.transition', [$tip, 'restore']) }}">@csrf<x-secondary-button type="submit">Restaurar a borrador</x-secondary-button></form>
            @endif
        </div>
        @if($tip->scope === \App\Enums\TipScope::TENANT && $tip->status === \App\Enums\TipStatus::PENDING_APPROVAL)
            <form method="POST" action="{{ route('admin.tips.transition', [$tip, 'reject']) }}" class="space-y-3 rounded-xl border bg-white p-5">
                @csrf<x-input-label for="rejection_reason" value="Motivo del rechazo" />
                <textarea id="rejection_reason" name="rejection_reason" required maxlength="2000" rows="3" class="w-full rounded-md border-gray-300">{{ old('rejection_reason') }}</textarea>
                <p class="text-sm text-gray-500">El coach verá este motivo para corregir su publicación.</p>
                <x-danger-button>Rechazar</x-danger-button>
            </form>
        @endif
    </div>
</x-app-layout>
