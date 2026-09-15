<x-app-layout>
    <div class="max-w-5xl mx-auto space-y-5">
        <div class="flex items-center justify-between gap-4"><h1 class="text-2xl font-bold">Tips</h1><a href="{{ route('coach.tips.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Nuevo Tip</a></div>
        <p class="text-gray-600">Comparte consejos con tus atletas. Todas las publicaciones pasan por revisión.</p>
        <form method="GET" action="{{ route('coach.tips.index') }}" class="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-4">
            <div><label for="q" class="text-sm">Buscar</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" class="w-full rounded-md border-gray-300" /></div>
            <div><label for="status" class="text-sm">Estado</label><select id="status" name="status" class="w-full rounded-md border-gray-300"><option value="">Todos</option>@foreach(\App\Enums\TipStatus::labels() as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="category" class="text-sm">Categoría</label><select id="category" name="category" class="w-full rounded-md border-gray-300"><option value="">Todas</option>@foreach(\App\Enums\TipCategory::labels() as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="flex items-end gap-3"><x-primary-button>Filtrar</x-primary-button><a href="{{ route('coach.tips.index') }}" class="py-2 text-sm text-indigo-700">Limpiar</a></div>
        </form>
        @forelse($tips as $tip)
            <a href="{{ route('coach.tips.show', $tip) }}" class="block rounded-xl border bg-white p-5 hover:border-indigo-400"><x-tips.status :status="$tip->status" /><h2 class="mt-2 text-lg font-semibold break-words">{{ $tip->title }}</h2><p class="mt-1 text-sm text-gray-500">{{ \App\Enums\TipCategory::labels()[$tip->category->value] }}</p></a>
        @empty
            <div class="rounded-xl border bg-white p-8 text-center text-gray-600">No hay Tips para mostrar. Crea uno o ajusta los filtros.</div>
        @endforelse
        {{ $tips->links() }}
    </div>
</x-app-layout>
