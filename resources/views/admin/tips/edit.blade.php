<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('admin.tips.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-700 hover:text-indigo-900">← Volver a Tips</a>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">{{ $tip->exists ? 'Editar publicación global' : 'Nueva publicación global' }}</h1>
                <p class="mt-1 text-sm text-gray-600">Crea un anuncio claro y confirma la imagen antes de publicarlo.</p>
            </div>
            <div class="rounded-full bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-800 ring-1 ring-inset ring-indigo-200">
                Publicación global
            </div>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <x-tips.form
                :tip="$tip"
                :action="$tip->exists ? route('admin.tips.update', $tip) : route('admin.tips.store')"
                :method="$tip->exists ? 'PUT' : 'POST'"
                :image-url="$tip->image_path ? route('admin.tips.image', $tip) : null"
                intent="publish"
                submit-label="Publicar"
            />
        </div>
    </div>
</x-app-layout>
