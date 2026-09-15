<x-app-layout>
    <div class="max-w-3xl mx-auto space-y-5">
        <a href="{{ route('admin.tips.index') }}" class="text-indigo-700">← Volver a Tips</a>
        <h1 class="text-2xl font-bold">{{ $tip->exists ? 'Editar publicación global' : 'Nueva publicación global' }}</h1>
        <p class="text-gray-600">Puedes guardar un borrador o publicar directamente como administrador.</p>
        <div class="rounded-xl border bg-white p-6"><x-tips.form :tip="$tip" :action="$tip->exists ? route('admin.tips.update', $tip) : route('admin.tips.store')" :method="$tip->exists ? 'PUT' : 'POST'" intent="publish" submit-label="Publicar" /></div>
    </div>
</x-app-layout>
