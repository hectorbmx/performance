<x-app-layout>
    <div class="max-w-3xl mx-auto space-y-5">
        <a href="{{ route('coach.tips.index') }}" class="text-indigo-700">← Volver a Tips</a>
        <h1 class="text-2xl font-bold">{{ $tip->exists ? 'Editar Tip' : 'Nuevo Tip' }}</h1>
        <p class="text-gray-600">Tus consejos necesitan aprobación antes de mostrarse a tus atletas.</p>
        <div class="rounded-xl border bg-white p-6"><x-tips.form :tip="$tip" :action="$tip->exists ? route('coach.tips.update', $tip) : route('coach.tips.store')" :method="$tip->exists ? 'PUT' : 'POST'" /></div>
    </div>
</x-app-layout>
