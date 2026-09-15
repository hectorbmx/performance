@props(['tip', 'imageUrl' => null])
<article class="space-y-4">
    <x-tips.status :status="$tip->status" />
    <h1 class="text-2xl font-bold text-gray-900 break-words">{{ $tip->title }}</h1>
    <p class="text-sm text-gray-500">{{ \App\Enums\TipCategory::labels()[$tip->category->value] }} · {{ \App\Enums\TipType::labels()[$tip->type->value] }}</p>
    @if($tip->expires_at)
        <p class="text-sm {{ $tip->expires_at->isPast() ? 'text-red-600' : 'text-gray-500' }}">
            Expira: {{ $tip->expires_at->format('d/m/Y H:i') }}
        </p>
    @endif
    @if($imageUrl)<img src="{{ $imageUrl }}" alt="Imagen de la publicación" class="max-h-96 rounded-lg object-contain" />@endif
    <div class="whitespace-pre-wrap break-words text-gray-700">{{ $tip->body }}</div>
</article>
