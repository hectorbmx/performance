<x-app-layout>
    <div class="max-w-5xl mx-auto space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-2xl font-bold">{{ $pending ? 'Revisión de coaches' : 'Tips globales' }}</h1><a href="{{ route('admin.tips.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Nueva publicación global</a></div>
        <nav class="flex flex-wrap gap-4" aria-label="Secciones de Tips">
            <a href="{{ route('admin.tips.index') }}" class="text-indigo-700" @if(!$pending) aria-current="page" @endif>Mis publicaciones globales</a>
            <a href="{{ route('admin.tips.pending') }}" class="text-indigo-700" @if($pending) aria-current="page" @endif>Pendientes de aprobación</a>
        </nav>
        <p class="text-gray-600">{{ $pending ? 'Revisa el contenido antes de que se muestre a los atletas del coach.' : 'Estas publicaciones se mostrarán a los atletas con acceso a anuncios globales.' }}</p>
        @forelse($tips as $tip)
            <a href="{{ route('admin.tips.show', $tip) }}" class="flex items-center justify-between gap-4 rounded-xl border bg-white p-4 hover:border-indigo-400">
                <div class="min-w-0">
                    @if(!$pending)
                        <x-tips.status :status="$tip->status" />
                    @endif
                    <h2 class="{{ $pending ? '' : 'mt-2' }} truncate text-lg font-semibold">{{ $tip->title }}</h2>
                    @if($pending)<p class="mt-1 truncate text-sm text-gray-600">Coach: {{ $tip->author->name }} · Último cambio: {{ $tip->updated_at->format('d/m/Y H:i') }}</p>@endif
                </div>
                @if($pending)
                    <span
                        title="Pendiente de aprobación"
                        aria-label="Pendiente de aprobación"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-1 ring-amber-200"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        </svg>
                    </span>
                @endif
            </a>
        @empty
            <p class="rounded-xl border bg-white p-8 text-center text-gray-600">{{ $pending ? 'No hay publicaciones pendientes de aprobación.' : 'Aún no tienes publicaciones globales.' }}</p>
        @endforelse
        {{ $tips->links() }}
    </div>
</x-app-layout>
