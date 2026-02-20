<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Biblioteca de Videos
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                Sube videos por URL (YouTube) para reutilizarlos en tus secciones.
            </p>
        </div>
    </x-slot>

<div class="py-6">
  <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
    <div class="px-4 sm:px-0">
  {{-- Alerts --}}
  @if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
      {{ session('success') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
      <div class="font-semibold mb-1">Revisa los campos:</div>
      <ul class="list-disc pl-5 text-sm space-y-1">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Form + List --}}
  {{-- Row: Captura --}}
<div class="bg-white border rounded-2xl p-5 mb-6">
  <div class="flex items-start justify-between gap-4 mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Agregar video</h2>
  </div>

<form action="{{ route('coach.library.store') }}" method="POST" 
      style="display:flex; flex-direction:row; gap:12px; align-items:flex-end; width:100%;">
    @csrf

    {{-- Nombre --}}
    <div style="flex:3; min-width:0;">
        <label class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
        <input type="text" name="name" value="{{ old('name') }}"
               placeholder="Ej. Sentadilla técnica"
               class="w-full rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200"
               required maxlength="150">
        @error('name')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
        @enderror
    </div>

    {{-- URL --}}
    <div style="flex:4; min-width:0;">
        <label class="block text-xs font-medium text-slate-600 mb-1">URL de YouTube</label>
        <input type="text" name="youtube_url" value="{{ old('youtube_url') }}"
               placeholder="https://www.youtube.com/watch?v=..."
               class="w-full rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200"
               required>
        @error('youtube_url')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
        @enderror
    </div>

    {{-- Tipo --}}
    <div style="flex:3; min-width:0;">
        <label class="block text-xs font-medium text-slate-600 mb-1">Tipo</label>
        <select name="training_type_catalog_id"
                class="w-full rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
            <option value="">—</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}" @selected(old('training_type_catalog_id') == $t->id)>
                    {{ $t->name }}
                </option>
            @endforeach
        </select>
        @error('training_type_catalog_id')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
        @enderror
    </div>

    {{-- Botón --}}
    <div style="flex:0 0 auto;">
        <button type="submit"
                class="inline-flex items-center justify-center gap-1 rounded-xl bg-slate-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 whitespace-nowrap">
            + Guardar
        </button>
    </div>

</form>
</div>

{{-- Grid: Videos --}}
<div class="bg-white border rounded-2xl p-5">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Videos</h2>
    <div class="text-xs text-slate-500">{{ $videos->total() }} registros</div>
  </div>

  @if($videos->count() === 0)
    <div class="rounded-xl border border-dashed p-6 text-center text-slate-500">
      Aún no tienes videos. Agrega el primero arriba.
    </div>
  @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($videos as $v)
        <div class="rounded-2xl border overflow-hidden">
          <div class="aspect-video bg-slate-100">
            @if($v->thumbnail_url)
              <img src="{{ $v->thumbnail_url }}" alt="{{ $v->name }}" class="w-full h-full object-cover">
            @endif
          </div>

          <div class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-900 leading-tight">{{ $v->name }}</div>
                <div class="mt-1 text-xs text-slate-500">
                  {{ optional($v->type)->name ?? 'Sin tipo' }}
                </div>
              </div>

              <form action="{{ route('coach.library.destroy', $v->id) }}"
                    method="POST"
                    onsubmit="return confirm('¿Eliminar este video?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-red-600 hover:text-red-700">Eliminar</button>
              </form>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <a href="{{ $v->youtube_url }}" target="_blank"
                 class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                Abrir
              </a>

              @if($v->youtube_id)
                <button type="button"
                        class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                        onclick="navigator.clipboard.writeText('{{ $v->youtube_id }}')">
                  Copiar ID
                </button>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="mt-6">
      {{ $videos->links() }}
    </div>
  @endif
</div>

  </div>
</div>
    </div>
  </div>
</div>

</x-app-layout>

