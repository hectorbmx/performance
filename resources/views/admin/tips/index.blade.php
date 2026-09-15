<x-app-layout>
    <div class="max-w-5xl mx-auto space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-2xl font-bold">{{ $pending ? 'Revisión de coaches' : 'Tips globales' }}</h1><a href="{{ route('admin.tips.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Nueva publicación global</a></div>
        <nav class="flex flex-wrap gap-4" aria-label="Secciones de Tips">
            <a href="{{ route('admin.tips.index') }}" class="text-indigo-700" @if(!$pending) aria-current="page" @endif>Mis publicaciones globales</a>
            <a href="{{ route('admin.tips.pending') }}" class="text-indigo-700" @if($pending) aria-current="page" @endif>Pendientes de aprobación</a>
        </nav>
        <p class="text-gray-600">{{ $pending ? 'Revisa el contenido antes de que se muestre a los atletas del coach.' : 'Estas publicaciones se mostrarán a los atletas con acceso a anuncios globales.' }}</p>
        @forelse($tips as $tip)
            <a href="{{ route('admin.tips.show', $tip) }}" class="block rounded-xl border bg-white p-5 hover:border-indigo-400">
                <x-tips.status :status="$tip->status" /><h2 class="mt-2 text-lg font-semibold break-words">{{ $tip->title }}</h2>
                @if($pending)<p class="mt-1 text-sm text-gray-600">Coach: {{ $tip->author->name }} · Último cambio: {{ $tip->updated_at->format('d/m/Y H:i') }}</p>@endif
            </a>
        @empty
            <p class="rounded-xl border bg-white p-8 text-center text-gray-600">{{ $pending ? 'No hay publicaciones pendientes de aprobación.' : 'Aún no tienes publicaciones globales.' }}</p>
        @endforelse
        {{ $tips->links() }}
    </div>
</x-app-layout>
