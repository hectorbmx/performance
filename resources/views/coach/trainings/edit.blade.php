<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Editar entrenamiento</h1>
                <p class="text-sm text-gray-600">Actualiza la cabecera y las secciones.</p>
            </div>

            <a href="{{ route('coach.trainings.index') }}"
               class="px-4 py-2 rounded-lg border text-sm text-gray-700">
                Volver
            </a>
        </div>

        <form method="POST" action="{{ route('coach.trainings.update', $training) }}" class="mt-6 space-y-6" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- ERRORES --}}
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <div class="font-semibold mb-2">Revisa los errores:</div>
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- CABECERA --}}
            <div class="bg-white border rounded-xl p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                        <input name="title" required
                               value="{{ old('title', $training->title) }}"
                               class="w-full h-10 rounded-lg border-gray-300"/>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Fecha</label>
                        <input type="date" name="scheduled_at" required
                               value="{{ old('scheduled_at', $training->scheduled_at->toDateString()) }}"
                               class="w-full h-10 rounded-lg border-gray-300"/>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Duración (min)</label>
                        <input type="number" name="duration_minutes"
                               value="{{ old('duration_minutes', $training->duration_minutes) }}"
                               class="w-full h-10 rounded-lg border-gray-300"/>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Visibilidad</label>
                        <select name="visibility" class="w-full h-10 rounded-lg border-gray-300">
                            <option value="assigned" @selected(old('visibility', $training->visibility)==='assigned')>Asignado</option>
                            <option value="free" @selected(old('visibility', $training->visibility)==='free')>Libre</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nivel</label>
                        <select name="level" class="w-full h-10 rounded-lg border-gray-300">
                            @foreach(['beginner','intermediate','advanced'] as $lvl)
                                <option value="{{ $lvl }}" @selected(old('level',$training->level)===$lvl)>
                                    {{ ucfirst($lvl) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                          <label class="block text-xs text-gray-600 mb-1">Objetivo</label>
                            <select name="training_goal_catalog_id" class="w-full h-10 rounded-lg border-gray-300" required>
                                @foreach ($goals as $goal)
                                    <option value="{{ $goal->id }}"
                                        @selected(old('training_goal_catalog_id') == $goal->id)>
                                        {{ $goal->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('training_goal_catalog_id')
                                <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                            @enderror

                    </div>

                   <div>
                  <label class="block text-xs text-gray-600 mb-1">Tipo</label>

                  <select name="training_type_catalog_id" class="w-full h-10 rounded-lg border-gray-300">
                      <option value="">Selecciona un tipo</option>

                      @foreach($types as $t)
                          <option value="{{ $t->id }}"
                              @selected(
                                  (string)old('training_type_catalog_id', (string)($training->training_type_catalog_id ?? '')) === (string)$t->id
                              )>
                              {{ $t->name }}
                          </option>
                      @endforeach
                  </select>

                  @error('training_type_catalog_id')
                      <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                  @enderror
              </div>

{{-- Cover image --}}
@php
    $existingCoverUrl = !empty($training->cover_image)
        ? asset('storage/'.$training->cover_image)
        : null;
@endphp


<div class="space-y-2">
    <label class="block text-sm font-medium text-gray-700">
        Cover image
    </label>

    {{-- Preview --}}
    <div
        id="coverPreview"
        class="w-full h-40 rounded-xl border border-dashed border-gray-300
               flex items-center justify-center bg-gray-50 overflow-hidden"
    >
        @if($existingCoverUrl)
            <img
                id="coverPreviewImg"
                src="{{ $existingCoverUrl }}"
                alt="Cover"
                class="w-full h-full object-cover"
            >
        @else
            <span id="coverPreviewPlaceholder" class="text-sm text-gray-400">
                No image selected
            </span>
        @endif
    </div>

    {{-- File input (siempre visible) --}}
    <input
        type="file"
        name="cover_image"
        accept="image/*"
        class="block w-full text-sm text-gray-600
               file:mr-4 file:py-2 file:px-4
               file:rounded-lg file:border-0
               file:text-sm file:font-semibold
               file:bg-gray-100 file:text-gray-700
               hover:file:bg-gray-200"
        onchange="previewCover(event)"
    />

    <p class="text-xs text-gray-500">
        Recommended: JPG / PNG / WEBP · 1200×600
    </p>
</div>
{{-- Cover image --}}

{{-- Cover image --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Notas</label>
                        <textarea name="notes" rows="3"
                                  class="w-full rounded-lg border-gray-300">{{ old('notes',$training->notes) }}</textarea>
                    </div>
                </div>
            </div>
<div class="bg-white border rounded-xl p-5">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    {{-- IZQUIERDA: Asignación individual --}}
    <div>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Asignación individual</h2>
          <p class="text-sm text-gray-600 mt-1">
            Busca atletas y asígnalos al entrenamiento. Doble clic para agregar.
          </p>
        </div>
      </div>

      <div class="mt-4 relative">
        <label class="block text-xs text-gray-600 mb-1">Buscar atleta</label>
        <input id="clientSearchInput" type="text"
               class="w-full h-10 rounded-lg border-gray-300"
               placeholder="Nombre o email (mín. 2 caracteres)">

        <div id="clientSearchResults"
             class="absolute z-20 mt-2 w-full rounded-lg border bg-white shadow-lg overflow-hidden hidden">
          <div id="clientSearchResultsInner" class="max-h-72 overflow-auto"></div>
          <div id="clientSearchHint" class="px-3 py-2 text-xs text-gray-500 border-t bg-gray-50 hidden">
            Tip: doble clic para asignar.
          </div>
        </div>
      </div>

<div class="mt-4">
  <div class="text-xs text-gray-600 mb-2">Atletas asignados</div>

  <div id="assignedClientsPills" class="flex flex-wrap gap-2">
    @foreach(old('assigned_clients', $assignedClientIds) as $cid)
      @php
        $c = $clients->firstWhere('id', (int) $cid);
      @endphp

      @if($c)
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm border border-emerald-100"
              data-client-pill="{{ $c->id }}">
          <span class="font-medium">{{ $c->first_name }} {{ $c->last_name }}</span>

          <button type="button"
                  class="removeClientPill text-emerald-700/70 hover:text-emerald-900"
                  title="Quitar">✕</button>

          <input type="hidden" name="assigned_clients[]" value="{{ $c->id }}">
        </span>
      @endif
    @endforeach
  </div>

  <div id="assignedClientsEmpty"
       class="text-sm text-gray-500 mt-2 {{ count(old('assigned_clients', $assignedClientIds)) ? 'hidden' : '' }}">
    No hay atletas asignados.
  </div>
</div>

    </div>

   {{-- DERECHA: Asignación por grupo --}}
<div>
  <h2 class="text-lg font-semibold text-gray-900">Asignación por grupo</h2>
  <p class="text-sm text-gray-600 mt-1">
    Busca grupos y asígnalos al entrenamiento. Doble clic para agregar.
  </p>

  <div class="mt-4 relative">
    <label class="block text-xs text-gray-600 mb-1">Buscar grupo</label>
    <input id="groupSearchInput" type="text"
           class="w-full h-10 rounded-lg border-gray-300"
           placeholder="Nombre del grupo (mín. 2 caracteres)">

    <div id="groupSearchResults"
         class="absolute z-20 mt-2 w-full rounded-lg border bg-white shadow-lg overflow-hidden hidden">
      <div id="groupSearchResultsInner" class="max-h-72 overflow-auto"></div>
      <div id="groupSearchHint" class="px-3 py-2 text-xs text-gray-500 border-t bg-gray-50 hidden">
        Tip: doble clic para asignar.
      </div>
    </div>
  </div>

  <div class="mt-4">
   <div class="text-xs text-gray-600 mb-2">Grupos asignados</div>

    <div id="assignedGroupsPills" class="flex flex-wrap gap-2">
        @foreach(old('assigned_groups', $assignedGroups->pluck('id')->all()) as $gid)
            @php
                $g = $assignedGroups->firstWhere('id', (int)$gid);
            @endphp
            @if($g)
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm border border-emerald-100"
                      data-group-pill="{{ $g->id }}">
                    <span class="font-medium">{{ $g->name }}</span>

                    <button type="button"
                            class="removeGroupPill text-emerald-700/70 hover:text-emerald-900"
                            title="Quitar">
                        ✕
                    </button>

                    {{-- ✅ esto es lo que realmente guarda --}}
                    <input type="hidden" name="assigned_groups[]" value="{{ $g->id }}">
                </span>
            @endif
        @endforeach
    </div>

    <div id="assignedGroupsEmpty"
         class="text-sm text-gray-500 mt-2 {{ count(old('assigned_groups', $assignedGroups->pluck('id')->all())) ? 'hidden' : '' }}">
        No hay grupos asignados.
    </div>
</div>


  </div>
</div>
{{-- SECCIONES --}}
<div class="bg-white border rounded-xl p-5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Secciones</h2>
        <button type="button" id="addSection"
                class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">
            + Agregar sección
        </button>
    </div>

    {{-- Exponer unidades a JS (para secciones nuevas y cambios de tipo) --}}
    <script>
        window.__units = @json($units);
    </script>

    <div id="sections" class="mt-4 space-y-4">
        @foreach($training->sections as $i => $s)
            @php
                $rt = $s->result_type ?? 'none';

                // Compat: si traes datos viejos (kg/lb) mapéalos a weight
                if (in_array($rt, ['kg','lb'], true)) {
                    $rt = 'weight';
                }

                $resultTypes = [
                    'none'     => 'Sin resultados',
                    'reps'     => 'Repeticiones',
                    'time'     => 'Tiempo',
                    'weight'   => 'Peso',
                    'distance' => 'Distancia',
                    'rounds'   => 'Rondas',
                    'sets'     => 'Series',
                    'calories' => 'Calorías',
                    'points'   => 'Puntos',
                    'note'     => 'Nota / Texto',
                    'boolean'  => 'Sí / No',
                ];

                $requiresUnit = in_array($rt, ['weight','time','distance','reps','rounds','sets','calories','points'], true);

                // Opciones de unidades (filtradas por tipo)
                $unitOptions = $units->where('result_type', $rt);
            @endphp

            <div class="rounded-xl border p-4" data-sec>
                <input type="hidden" name="sections[{{ $i }}][id]" value="{{ $s->id }}"/>

                <div class="flex items-center justify-between">
                    <div class="font-semibold">Sección {{ $i+1 }}</div>
                    <button type="button" class="removeSec text-sm text-red-600">Eliminar</button>
                </div>

                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs mb-1">Nombre</label>
                        <input name="sections[{{ $i }}][name]"
                               value="{{ $s->name }}"
                               class="w-full h-10 rounded-lg border-gray-300"
                               required/>
                    </div>

                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs mb-1">Tipo de resultado</label>
                            {{-- ⚠️ No usar disabled aquí: si se deshabilita no se envía en el POST --}}
                            <select name="sections[{{ $i }}][result_type]"
                                    class="secResultType w-full h-10 rounded-lg border-gray-300">
                                @foreach($resultTypes as $key => $label)
                                    <option value="{{ $key }}" @selected($rt === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Unidad: visible solo si aplica --}}
                        <div class="flex-1 secUnitWrap {{ $requiresUnit ? '' : 'hidden' }}">
                            <label class="block text-xs mb-1">Unidad</label>

                           <select name="sections[{{ $i }}][unit_id]"
                                class="secUnit w-full h-10 rounded-lg border-gray-300">
                            <option value="">Selecciona una unidad</option>

                            @foreach($unitOptions as $u)
                                <option value="{{ $u->id }}"
                                    @selected(
                                        old("sections.$i.unit_id", $s->unit_id) == $u->id
                                    )
                                >
                                    {{ $u->name }} ({{ $u->symbol }})
                                </option>
                            @endforeach
                        </select>

                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs mb-1">Descripción</label>
                        <textarea name="sections[{{ $i }}][description]"
                                  class="w-full rounded-lg border-gray-300"
                                  rows="3">{{ $s->description }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs mb-1">Video (YouTube URL)</label>
                        <input type="url"
                               name="sections[{{ $i }}][video_url]"
                               value="{{ $s->video_url }}"
                               class="w-full h-10 rounded-lg border-gray-300"
                               placeholder="https://www.youtube.com/watch?v=..." />
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fa fa-youtube" aria-hidden="true"></i>
                            Opcional: pega un link de YouTube.
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Template para secciones nuevas --}}
    <template id="sectionTpl">
        <div class="rounded-xl border p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold text-gray-900">Sección <span class="secNum"></span></div>
                <button type="button" class="removeSec text-sm text-red-600">Eliminar</button>
            </div>

            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                    <input class="secName w-full h-10 rounded-lg border-gray-300" required />
                </div>

                <div class="flex items-end gap-3">
                    <input type="hidden" class="secAccepts" value="1" />

                    <div class="flex-1">
                        <label class="block text-xs text-gray-600 mb-1">Tipo de resultado</label>
                        <select class="secResultType w-full h-10 rounded-lg border-gray-300">
                            <option value="none" selected>Sin resultados</option>
                            <option value="reps">Repeticiones</option>
                            <option value="time">Tiempo</option>
                            <option value="weight">Peso</option>
                            <option value="distance">Distancia</option>
                            <option value="rounds">Rondas</option>
                            <option value="sets">Series</option>
                            <option value="calories">Calorías</option>
                            <option value="points">Puntos</option>
                            <option value="note">Nota / Texto</option>
                            <option value="boolean">Sí / No</option>
                        </select>
                    </div>

                    <div class="flex-1 secUnitWrap hidden">
                        <label class="block text-xs text-gray-600 mb-1">Unidad</label>
                        <select class="secUnit w-full h-10 rounded-lg border-gray-300">
                            <option value="" selected>Selecciona una unidad</option>
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Descripción</label>
                    <textarea class="secDesc w-full rounded-lg border-gray-300" rows="3"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Video (YouTube URL)</label>
                    <input type="url" class="secVideo w-full h-10 rounded-lg border-gray-300"
                           placeholder="https://www.youtube.com/watch?v=..." />
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fa fa-youtube" aria-hidden="true"></i>
                        Opcional: pega un link de YouTube.
                    </p>
                </div>
            </div>
        </div>
    </template>

    {{-- Script de secciones (edit + nuevas) --}}
    <script>
        (function () {
            const sectionsEl = document.getElementById('sections');
            const tpl = document.getElementById('sectionTpl');
            const addBtn = document.getElementById('addSection');
            if (!sectionsEl || !tpl || !addBtn) return;

            function toggleUnitUI(card) {
                const resultType = card.querySelector('.secResultType');
                const unitWrap = card.querySelector('.secUnitWrap');
                const unitSel = card.querySelector('.secUnit');

                const rt = (resultType?.value || 'none');

                // Si no hay resultados
                if (!rt || rt === 'none' || rt === 'note' || rt === 'boolean') {
                    if (unitWrap) unitWrap.classList.add('hidden');
                    if (unitSel) unitSel.value = '';
                    return;
                }

                const allUnits = Array.isArray(window.__units) ? window.__units : [];
                const options = allUnits.filter(u => u.result_type === rt);

                if (unitSel) {
                    const current = unitSel.value || '';
                    unitSel.innerHTML =
                        `<option value="" selected>Selecciona una unidad</option>` +
                        options.map(u => `<option value="${u.id}">${u.name} (${u.symbol})</option>`).join('');

                    // Mantener selección si aún existe
                    if (current && options.some(u => String(u.id) === String(current))) {
                        unitSel.value = current;
                    } else {
                        unitSel.value = '';
                    }
                }

                const show = options.length > 0;
                if (unitWrap) unitWrap.classList.toggle('hidden', !show);
                if (!show && unitSel) unitSel.value = '';
            }

            function rebuildNames() {
                const cards = sectionsEl.querySelectorAll('[data-sec]');
                cards.forEach((card, idx) => {
                    // Si es un card renderizado por blade, ya trae names
                    // Solo numeración visual si existiera placeholder; no forzamos aquí.
                    const num = card.querySelector('.secNum');
                    if (num) num.textContent = (idx + 1);
                });
            }

            function wireCard(card) {
                const resultType = card.querySelector('.secResultType');
                if (resultType) {
                    resultType.addEventListener('change', () => toggleUnitUI(card));
                    toggleUnitUI(card);
                }

                const removeBtn = card.querySelector('.removeSec');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        card.remove();
                        rebuildNames();
                    });
                }
            }

            function addSection() {
                const node = tpl.content.cloneNode(true);
                const wrapper = document.createElement('div');
                wrapper.dataset.sec = '1';
                wrapper.appendChild(node);

                // asignar names para nuevas secciones
                const idx = sectionsEl.querySelectorAll('[data-sec]').length;
                wrapper.querySelector('.secNum').textContent = (idx + 1);

                wrapper.querySelector('.secName').setAttribute('name', `sections[${idx}][name]`);
                wrapper.querySelector('.secDesc').setAttribute('name', `sections[${idx}][description]`);
                wrapper.querySelector('.secResultType').setAttribute('name', `sections[${idx}][result_type]`);
                wrapper.querySelector('.secUnit').setAttribute('name', `sections[${idx}][unit_id]`);

                const video = wrapper.querySelector('.secVideo');
                if (video) video.setAttribute('name', `sections[${idx}][video_url]`);

                sectionsEl.appendChild(wrapper);
                wireCard(wrapper);
                rebuildNames();
            }

            // Wire cards existentes (blade)
            sectionsEl.querySelectorAll('[data-sec]').forEach(wireCard);

            addBtn.addEventListener('click', addSection);
        })();
    </script>
</div>      
<!--AQUI TERMINA SECCIONES --> 

            <div class="flex justify-end gap-3">
                <a href="{{ route('coach.trainings.index') }}" class="px-4 py-2 rounded-lg border">Cancelar</a>
                <button class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-medium">Guardar cambios</button>
            </div>
        </form>
    </div>
    <template id="sectionTpl">
  <div class="rounded-xl border p-4" data-sec>
    <input type="hidden" class="secId" />

    <div class="flex items-center justify-between">
      <div class="font-semibold">Sección <span class="secNum"></span></div>
      <button type="button" class="removeSec text-sm text-red-600">Eliminar</button>
    </div>

    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs mb-1">Nombre</label>
        <input class="secName w-full h-10 rounded-lg border-gray-300" />
      </div>

      <div class="flex items-end gap-3">
        {{-- <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" class="secAccepts rounded border-gray-300" />
          Acepta resultados
        </label> --}}

        <div class="flex-1">
          <label class="block text-xs mb-1">Tipo de resultado</label>
        <select class="secResultType w-full h-10 rounded-lg border-gray-300" >
          <option value="none" selected>Sin resultados</option>
          <option value="reps">Repeticiones</option>
          <option value="time">Tiempo</option>
          <option value="weight">Peso</option>
          <option value="distance">Distancia</option>
          <option value="rounds">Rondas</option>
          <option value="sets">Series</option>
          <option value="calories">Calorías</option>
          <option value="points">Puntos</option>
          <option value="note">Notas</option>
          <option value="boolean">Sí / No</option>
      </select>

        </div>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs mb-1">Descripción</label>
        <textarea class="secDesc w-full rounded-lg border-gray-300" rows="3"></textarea>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-gray-600 mb-1">Video (YouTube URL)</label>
        <input type="url" class="secVideo w-full h-10 rounded-lg border-gray-300"
               placeholder="https://www.youtube.com/watch?v=..." />
      </div>
    </div>
  </div>
  <!-- Confirm modal -->
<div id="confirmModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-6">
    <h3 class="text-lg font-semibold text-gray-900">
      Cambiar visibilidad a “Libre”
    </h3>

    <p class="mt-3 text-sm text-gray-600">
      Vas a cambiar este entrenamiento a <b>Libre</b>.
    </p>

    <ul class="mt-3 text-sm text-gray-600 list-disc pl-5 space-y-1">
      <li>Todos podrán ver este entrenamiento después del cambio.</li>
      <li>Se eliminarán las asignaciones actuales (atletas y grupos).</li>
    </ul>

    <div class="mt-6 flex justify-end gap-3">
      <button type="button"
              id="confirmCancel"
              class="px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50">
        Cancelar
      </button>

      <button type="button"
              id="confirmAccept"
              class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
        Sí, cambiar a Libre
      </button>
    </div>
  </div>
</div>
</template>


</x-app-layout>
    <script>
        (function(){
            const input = document.getElementById('clientSearch');
            const list = document.getElementById('clientList');
            if (!input || !list) return;

            input.addEventListener('input', () => {
                const q = input.value.toLowerCase().trim();
                list.querySelectorAll('label').forEach(card => {
                    const text = card.innerText.toLowerCase();
                    card.style.display = text.includes(q) ? '' : 'none';
                });
            });
        })();
    </script>
<script>
(function () {
  const sectionsEl = document.getElementById('sections');
  const tpl = document.getElementById('sectionTpl');
  const addBtn = document.getElementById('addSection');
  if (!sectionsEl || !tpl || !addBtn) return;

  function bindCard(card) {
    const accepts = card.querySelector('.secAccepts');
    const resultType = card.querySelector('.secResultType');

    if (accepts && resultType) {
      accepts.addEventListener('change', () => {
        resultType.disabled = !accepts.checked;
        if (!accepts.checked) resultType.value = '';
      });
    }

    const removeBtn = card.querySelector('.removeSec');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        card.remove();
        rebuildNames();
      });
    }
  }

  function rebuildNames() {
    const cards = sectionsEl.querySelectorAll('[data-sec]');
    cards.forEach((card, i) => {
      const num = card.querySelector('.secNum');
      if (num) num.textContent = (i + 1);

      // id (si existe)
      const hiddenId = card.querySelector('input[type="hidden"]');
      if (hiddenId) hiddenId.name = `sections[${i}][id]`;

      // name/desc/video
      const name = card.querySelector('.secName') || card.querySelector('input[name*="[name]"]');
      const desc = card.querySelector('.secDesc') || card.querySelector('textarea[name*="[description]"]');
      const video = card.querySelector('.secVideo') || card.querySelector('input[name*="[video_url]"]');
      const accepts = card.querySelector('.secAccepts') || card.querySelector('input[type="checkbox"][name*="[accepts_results]"]');
      const resultType = card.querySelector('.secResultType') || card.querySelector('select[name*="[result_type]"]');

      if (name) name.name = `sections[${i}][name]`;
      if (desc) desc.name = `sections[${i}][description]`;
      if (video) video.name = `sections[${i}][video_url]`;
      if (accepts) {
        accepts.name = `sections[${i}][accepts_results]`;
        accepts.value = '1';
      }
      if (resultType) resultType.name = `sections[${i}][result_type]`;
    });
  }

  function addSection() {
    const node = tpl.content.firstElementChild.cloneNode(true);
    sectionsEl.appendChild(node);
    bindCard(node);
    rebuildNames();
  }

  // Bind existentes (los que vienen del foreach)
  sectionsEl.querySelectorAll('[data-sec]').forEach(card => {
    bindCard(card);
  });

  // Reindex inicial (por si acaso)
  rebuildNames();

  addBtn.addEventListener('click', addSection);
})();
</script>
<script>
(function () {
  // =========================
  // ATLETAS (clients)
  // =========================
  const input = document.getElementById('clientSearchInput');
  const box = document.getElementById('clientSearchResults');
  const inner = document.getElementById('clientSearchResultsInner');
  const hint = document.getElementById('clientSearchHint');
  const selected = document.getElementById('assignedClientsPills');
  const selectedEmpty = document.getElementById('assignedClientsEmpty');

  if (input && box && inner && selected) {
    const searchUrlBase = @json(route('coach.clients.search'));
    let timer = null;
    let abortController = null;

    function setEmptyState() {
      const any = selected.querySelector('input[name="assigned_clients[]"]');
      if (selectedEmpty) selectedEmpty.classList.toggle('hidden', !!any);
    }

    function isSelected(id) {
      return !!selected.querySelector(`span[data-client-pill="${id}"]`);
    }

    function bindRemoveButtonsWithin(container) {
      container.querySelectorAll('.removeClientPill').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
          btn.closest('span[data-client-pill]')?.remove();
          setEmptyState();
        });
      });
    }

    function addPill(item) {
      if (!item?.id) return;
      if (isSelected(item.id)) return;

      const pill = document.createElement('span');
      pill.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm border border-emerald-100';
      pill.setAttribute('data-client-pill', item.id);

      const label = item.label ?? item.name ?? (item.email ?? `Atleta #${item.id}`);

      pill.innerHTML = `
        <span class="font-medium"></span>
        <button type="button"
                class="removeClientPill text-emerald-700/70 hover:text-emerald-900"
                title="Quitar">✕</button>
        <input type="hidden" name="assigned_clients[]" value="${item.id}">
      `;

      pill.querySelector('span').textContent = label;

      selected.appendChild(pill);
      bindRemoveButtonsWithin(pill);
      setEmptyState();
    }

    function renderResults(items) {
      inner.innerHTML = '';

      if (!items || !items.length) {
        inner.innerHTML = `<div class="px-3 py-2 text-sm text-gray-500">Sin resultados.</div>`;
        hint?.classList.add('hidden');
        return;
      }

      items.forEach(item => {
        const row = document.createElement('div');
        row.className = 'px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm flex items-center justify-between gap-3';

        const label = item.label ?? item.name ?? (item.email ?? `Atleta #${item.id}`);
        const email = item.email ?? '';

        row.innerHTML = `
          <div>
            <div class="font-medium text-gray-900"></div>
            <div class="text-xs text-gray-500"></div>
          </div>
          <div class="text-xs text-gray-400">${isSelected(item.id) ? 'Asignado' : 'Doble clic'}</div>
        `;

        row.querySelector('.font-medium').textContent = label;
        row.querySelector('.text-xs').textContent = email;

        row.addEventListener('dblclick', () => {
          addPill(item);
          row.querySelector('.text-gray-400').textContent = 'Asignado';
        });

        inner.appendChild(row);
      });

      hint?.classList.remove('hidden');
    }

    async function doSearch(q) {
      if (abortController) abortController.abort();
      abortController = new AbortController();

      const url = `${searchUrlBase}?q=${encodeURIComponent(q)}`;

      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' },
        signal: abortController.signal,
        credentials: 'same-origin',
      });

      const contentType = res.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        inner.innerHTML = `<div class="px-3 py-2 text-sm text-red-600">Respuesta no JSON (probable sesión/auth o ruta incorrecta).</div>`;
        box.classList.remove('hidden');
        hint?.classList.add('hidden');
        return;
      }

      const json = await res.json();
      renderResults(json.data || []);
    }

    input.addEventListener('input', () => {
      const q = input.value.trim();
      clearTimeout(timer);

      if (q.length < 2) {
        box.classList.add('hidden');
        inner.innerHTML = '';
        hint?.classList.add('hidden');
        return;
      }

      timer = setTimeout(() => {
        box.classList.remove('hidden');
        doSearch(q).catch(err => {
          if (err.name === 'AbortError') return;
          inner.innerHTML = `<div class="px-3 py-2 text-sm text-red-600">Error al buscar atletas.</div>`;
          hint?.classList.add('hidden');
        });
      }, 250);
    });

    document.addEventListener('click', (e) => {
      if (!box.contains(e.target) && e.target !== input) {
        box.classList.add('hidden');
      }
    });

    // Bind para pills precargadas del Blade
    bindRemoveButtonsWithin(selected);
    setEmptyState();
  }

  // =========================
  // GRUPOS
  // =========================
  const gInput = document.getElementById('groupSearchInput');
  const gBox = document.getElementById('groupSearchResults');
  const gInner = document.getElementById('groupSearchResultsInner');
  const gHint = document.getElementById('groupSearchHint');
  const gSelected = document.getElementById('assignedGroupsPills');
  const gSelectedEmpty = document.getElementById('assignedGroupsEmpty');

  if (gInput && gBox && gInner && gSelected) {
    const searchUrlBase = @json(route('coach.groups.search'));
    let timer = null;
    let abortController = null;

    function setEmptyState() {
      const any = gSelected.querySelector('input[name="assigned_groups[]"]');
      if (gSelectedEmpty) gSelectedEmpty.classList.toggle('hidden', !!any);
    }

    function isSelected(id) {
      return !!gSelected.querySelector(`span[data-group-pill="${id}"]`);
    }

    function bindRemoveButtonsWithin(container) {
      container.querySelectorAll('.removeGroupPill').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
          btn.closest('span[data-group-pill]')?.remove();
          setEmptyState();
        });
      });
    }

    function addPill(item) {
      if (!item?.id) return;
      if (isSelected(item.id)) return;

      const pill = document.createElement('span');
      pill.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm border border-emerald-100';
      pill.setAttribute('data-group-pill', item.id);

      const label = item.name ?? item.label ?? `Grupo #${item.id}`;

      pill.innerHTML = `
        <span class="font-medium"></span>
        <button type="button"
                class="removeGroupPill text-emerald-700/70 hover:text-emerald-900"
                title="Quitar">✕</button>
        <input type="hidden" name="assigned_groups[]" value="${item.id}">
      `;

      pill.querySelector('span').textContent = label;

      gSelected.appendChild(pill);
      bindRemoveButtonsWithin(pill);
      setEmptyState();
    }

    function renderResults(items) {
      gInner.innerHTML = '';

      if (!items || !items.length) {
        gInner.innerHTML = `<div class="px-3 py-2 text-sm text-gray-500">Sin resultados.</div>`;
        gHint?.classList.add('hidden');
        return;
      }

      items.forEach(item => {
        const row = document.createElement('div');
        row.className = 'px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm flex items-center justify-between gap-3';

        const label = item.name ?? item.label ?? `Grupo #${item.id}`;

        row.innerHTML = `
          <div class="font-medium text-gray-900"></div>
          <div class="text-xs text-gray-400">${isSelected(item.id) ? 'Asignado' : 'Doble clic'}</div>
        `;

        row.querySelector('.font-medium').textContent = label;

        row.addEventListener('dblclick', () => {
          addPill(item);
          row.querySelector('.text-gray-400').textContent = 'Asignado';
        });

        gInner.appendChild(row);
      });

      gHint?.classList.remove('hidden');
    }

    async function doSearch(q) {
      if (abortController) abortController.abort();
      abortController = new AbortController();

      const url = `${searchUrlBase}?q=${encodeURIComponent(q)}`;

      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' },
        signal: abortController.signal,
        credentials: 'same-origin',
      });

      const contentType = res.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        gInner.innerHTML = `<div class="px-3 py-2 text-sm text-red-600">Respuesta no JSON (probable sesión/auth o ruta incorrecta).</div>`;
        gBox.classList.remove('hidden');
        gHint?.classList.add('hidden');
        return;
      }

      const json = await res.json();
      renderResults(json.data || []);
    }

    gInput.addEventListener('input', () => {
      const q = gInput.value.trim();
      clearTimeout(timer);

      if (q.length < 2) {
        gBox.classList.add('hidden');
        gInner.innerHTML = '';
        gHint?.classList.add('hidden');
        return;
      }

      timer = setTimeout(() => {
        gBox.classList.remove('hidden');
        doSearch(q).catch(err => {
          if (err.name === 'AbortError') return;
          gInner.innerHTML = `<div class="px-3 py-2 text-sm text-red-600">Error al buscar grupos.</div>`;
          gHint?.classList.add('hidden');
        });
      }, 250);
    });

    document.addEventListener('click', (e) => {
      if (!gBox.contains(e.target) && e.target !== gInput) {
        gBox.classList.add('hidden');
      }
    });

    // Bind para pills precargadas del Blade
    bindRemoveButtonsWithin(gSelected);
    setEmptyState();
  }
})();

  function previewCover(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const preview = document.getElementById('coverPreview');
    const placeholder = document.getElementById('coverPreviewPlaceholder');
    let img = document.getElementById('coverPreviewImg');

    if (placeholder) placeholder.remove();

    if (!img) {
      img = document.createElement('img');
      img.id = 'coverPreviewImg';
      img.className = 'w-full h-full object-cover';
      img.alt = 'Cover';
      preview.innerHTML = '';
      preview.appendChild(img);
    }

    img.src = URL.createObjectURL(file);
  }
  
</script>
<script>
(function () {
  const form = document.querySelector('form');
  const visibility = document.querySelector('select[name="visibility"]');
  if (!form || !visibility) return;

  let last = visibility.value;

  function hasAssignments() {
    const anyClient = document.querySelector('input[name="assigned_clients[]"]');
    const anyGroup  = document.querySelector('input[name="assigned_groups[]"]');
    return !!(anyClient || anyGroup);
  }

  function clearAssignmentsUI() {
    // Pills
    const clientsPills = document.getElementById('assignedClientsPills');
    const groupsPills  = document.getElementById('assignedGroupsPills');
    if (clientsPills) clientsPills.innerHTML = '';
    if (groupsPills) groupsPills.innerHTML = '';

    // Empty states
    document.getElementById('assignedClientsEmpty')?.classList.remove('hidden');
    document.getElementById('assignedGroupsEmpty')?.classList.remove('hidden');

    // Limpiar inputs búsqueda y cerrar dropdowns
    const cInput = document.getElementById('clientSearchInput');
    const gInput = document.getElementById('groupSearchInput');
    if (cInput) cInput.value = '';
    if (gInput) gInput.value = '';

    document.getElementById('clientSearchResults')?.classList.add('hidden');
    document.getElementById('groupSearchResults')?.classList.add('hidden');
    const cInner = document.getElementById('clientSearchResultsInner');
    const gInner = document.getElementById('groupSearchResultsInner');
    if (cInner) cInner.innerHTML = '';
    if (gInner) gInner.innerHTML = '';
    document.getElementById('clientSearchHint')?.classList.add('hidden');
    document.getElementById('groupSearchHint')?.classList.add('hidden');
  }

  visibility.addEventListener('change', () => {
    const now = visibility.value;

    if (now === 'free' && last !== 'free' && hasAssignments()) {
      const ok = window.confirm(
        'Vas a cambiar este entrenamiento a "Libre".\n\n' +
        '- Todos podrán ver este entrenamiento después del cambio.\n' +
        '- Se eliminarán las asignaciones actuales (atletas y grupos).\n\n' +
        '¿Deseas continuar?'
      );

      if (!ok) {
        visibility.value = last; // revertir
        return;
      }

      // Limpieza visual inmediata (para que el usuario vea el efecto)
      clearAssignmentsUI();
    }

    last = visibility.value;
  });

  // Confirm extra al guardar (por si no cambió el select pero está en free y tenía asignaciones en UI)
  form.addEventListener('submit', (e) => {
    if (visibility.value === 'free' && hasAssignments()) {
      const ok = window.confirm(
        'Este entrenamiento está marcado como "Libre".\n\n' +
        'Al guardar:\n' +
        '- Todos podrán ver este entrenamiento.\n' +
        '- Se eliminarán las asignaciones actuales.\n\n' +
        '¿Guardar cambios?'
      );
      if (!ok) e.preventDefault();
    }
  });
})();
</script>
<script>
(function () {
  const visibilitySelect = document.getElementById('visibilitySelect');
  const modal = document.getElementById('confirmModal');
  const btnCancel = document.getElementById('confirmCancel');
  const btnAccept = document.getElementById('confirmAccept');

  if (!visibilitySelect || !modal) return;

  let previousValue = visibilitySelect.value;

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  visibilitySelect.addEventListener('change', () => {
    if (visibilitySelect.value === 'free' && previousValue === 'assigned') {
      // revertimos momentáneamente
      visibilitySelect.value = previousValue;
      openModal();
    } else {
      previousValue = visibilitySelect.value;
    }
  });

  btnCancel.addEventListener('click', () => {
    closeModal();
  });

  btnAccept.addEventListener('click', () => {
    visibilitySelect.value = 'free';
    previousValue = 'free';
    closeModal();

    // dispara manualmente el change para que tu lógica actual limpie asignaciones
    visibilitySelect.dispatchEvent(new Event('change'));
  });
})();
</script>
