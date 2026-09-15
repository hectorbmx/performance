<x-app-layout>
    <div class="max-w-4xl mx-auto space-y-5">
        <a href="{{ route('coach.tips.index') }}" class="text-indigo-700">← Volver a Tips</a>
        <div class="rounded-xl border bg-white p-6"><x-tips.preview :tip="$tip" :image-url="$tip->image_path ? route('coach.tips.image', $tip) : null" /></div>
        @if($tip->status === \App\Enums\TipStatus::PENDING_APPROVAL)<p class="rounded-lg bg-indigo-50 p-4 text-indigo-800">Tu Tip está en revisión. Si necesitas modificarlo, retíralo primero.</p>@endif
        @if($tip->status === \App\Enums\TipStatus::REJECTED)<div class="rounded-lg bg-amber-50 p-4"><strong>Motivo del rechazo</strong><p>{{ $tip->rejection_reason }}</p></div>@endif
        <div class="flex flex-wrap gap-3">
            @if(in_array($tip->status, [\App\Enums\TipStatus::DRAFT, \App\Enums\TipStatus::REJECTED]))<a href="{{ route('coach.tips.edit', $tip) }}" class="rounded-lg border bg-white px-4 py-2">{{ $tip->status === \App\Enums\TipStatus::REJECTED ? 'Corregir' : 'Editar' }}</a>@endif
            @php
                $actions = match($tip->status) {
                    \App\Enums\TipStatus::DRAFT => ['submit' => 'Enviar a revisión', 'archive' => 'Archivar'],
                    \App\Enums\TipStatus::PENDING_APPROVAL => ['withdraw' => 'Retirar de revisión', 'archive' => 'Archivar'],
                    \App\Enums\TipStatus::ARCHIVED => ['restore' => 'Restaurar a borrador'],
                    default => ['archive' => 'Archivar'],
                };
            @endphp
            @foreach($actions as $action => $label)
                @can($action, $tip)
                <form method="POST" action="{{ route('coach.tips.transition', [$tip, $action]) }}" @if($action === 'archive') onsubmit="return confirm('¿Archivar este Tip? Dejará de estar disponible para tus atletas.')" @endif>@csrf<x-secondary-button type="submit">{{ $label }}</x-secondary-button></form>
                @endcan
            @endforeach
        </div>
    </div>
</x-app-layout>
