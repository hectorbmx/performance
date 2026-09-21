<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('coach.tips.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-700 hover:text-indigo-900">← Volver a Tips</a>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">{{ $tip->exists ? 'Editar Tip' : 'Nuevo Tip' }}</h1>
                <p class="mt-1 text-sm text-gray-600">Prepara el contenido y revisa la imagen antes de enviarlo a aprobación.</p>
            </div>
            <div class="rounded-full bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
                Requiere aprobación
            </div>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <x-tips.form
                :tip="$tip"
                :action="$tip->exists ? route('coach.tips.update', $tip) : route('coach.tips.store')"
                :method="$tip->exists ? 'PUT' : 'POST'"
                :image-url="$tip->image_path ? route('coach.tips.image', $tip) : null"
            />
        </div>
    </div>
</x-app-layout>
