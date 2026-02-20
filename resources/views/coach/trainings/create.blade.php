<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Nuevo entrenamiento</h1>
                <p class="text-sm text-gray-600">Crea la cabecera y define tus secciones.</p>
            </div>

            <a href="{{ route('coach.trainings.index') }}"
               class="px-4 py-2 rounded-lg border text-sm text-gray-700">
                Volver
            </a>
        </div>

        <form method="POST"
              action="{{ route('coach.trainings.store') }}"
              class="mt-6 space-y-6"
              enctype="multipart/form-data">
            @csrf

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

            {{-- Card: Cabecera --}}
            <div class="rounded-2xl border bg-white p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {{-- Nombre --}}
                    <div class="lg:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                        <input type="text"
                               name="title"
                               value="{{ old('title') }}"
                               class="w-full h-10 rounded-lg border-gray-300"
                               required>
                        @error('title') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Fecha --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Fecha</label>
                        <input type="date"
                               name="scheduled_at"
                               value="{{ old('scheduled_at', $date ?? '') }}"
                               class="w-full h-10 rounded-lg border-gray-300"
                               required>
                        @error('scheduled_at') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Duración --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Duración (min)</label>
                        <input type="number"
                               name="duration_minutes"
                               value="{{ old('duration_minutes') }}"
                               min="1" max="600"
                               class="w-full h-10 rounded-lg border-gray-300">
                        @error('duration_minutes') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Visibilidad --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Visibilidad</label>
                        <select id="visibilitySelect"
                                name="visibility"
                                class="w-full h-10 rounded-lg border-gray-300">
                            <option value="free" @selected(old('visibility','free')==='free')>Libre</option>
                            <option value="assigned" @selected(old('visibility')==='assigned')>Asignado</option>
                        </select>
                        @error('visibility') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Nivel --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nivel</label>
                        <select name="level" class="w-full h-10 rounded-lg border-gray-300" required>
                            <option value="beginner" @selected(old('level','beginner')==='beginner')>Beginner</option>
                            <option value="intermediate" @selected(old('level')==='intermediate')>Intermediate</option>
                            <option value="advanced" @selected(old('level')==='advanced')>Advanced</option>
                        </select>
                        @error('level') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                

                    {{-- Tipo (catálogo) --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Tipo</label>
                        <select name="training_type_catalog_id"
                                class="w-full h-10 rounded-lg border-gray-300">
                            <option value="">Selecciona un tipo</option>
                            @foreach(($types ?? []) as $t)
                                <option value="{{ $t->id }}" @selected((string)old('training_type_catalog_id') === (string)$t->id)>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('training_type_catalog_id') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                        {{-- Legacy para no romper mientras "type" siga required en backend --}}
                        <input type="hidden" name="type" value="{{ old('type','fitness') }}">
                    </div>
    {{-- Objetivo --}}
                    <div>
                        {{-- <label class="block text-xs text-gray-600 mb-1">Objetivo</label>
                        <select name="goal" class="w-full h-10 rounded-lg border-gray-300" required>
                            <option value="strength" @selected(old('goal','strength')==='strength')>Fuerza</option>
                            <option value="cardio" @selected(old('goal')==='cardio')>Cardio</option>
                            <option value="technique" @selected(old('goal')==='technique')>Técnica</option>
                            <option value="mobility" @selected(old('goal')==='mobility')>Movilidad</option>
                            <option value="mixed" @selected(old('goal')==='mixed')>Mixto</option>
                        </select>
                        @error('goal') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror --}}
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
                    {{-- Color etiqueta --}}
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Color etiqueta</label>
                        <div class="flex items-center gap-2">
                            <input id="tagColorInput"
                                   type="color"
                                   name="tag_color"
                                   value="{{ old('tag_color', '#000000') }}"
                                   class="h-10 w-14 rounded border border-gray-300">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input id="noColorCheck" type="checkbox" class="rounded border-gray-300">
                                Sin color
                            </label>
                        </div>
                        @error('tag_color') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Cover image --}}
                    <div class="lg:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Cover image</label>
                        <input type="file"
                               name="cover_image"
                               accept="image/*"
                               onchange="previewCover(event)"
                               class="block w-full text-sm file:mr-4 file:py-2 file:px-4
                                      file:rounded-lg file:border-0 file:text-sm
                                      file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        @error('cover_image') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                        <p class="text-xs text-gray-500 mt-2">Recommended: JPG / PNG / WEBP · 1200×600</p>

                        {{-- Preview opcional (si no lo quieres, quítalo y deja previewCover sin usarse) --}}
                        <div id="coverPreview"
                             class="mt-3 h-44 rounded-xl border border-dashed bg-gray-50 flex items-center justify-center text-sm text-gray-500 overflow-hidden">
                            No image selected
                        </div>
                    </div>

                    {{-- Notas --}}
                    <div class="lg:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Notas</label>
                        <textarea name="notes"
                                  rows="3"
                                  class="w-full rounded-lg border-gray-300">{{ old('notes') }}</textarea>
                        @error('notes') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Asignación (SOLO UNA, estilo edit, IDs compatibles con JS de búsqueda) --}}
                <div id="assignBlock" class="mt-8 hidden">
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
                                        @foreach(old('assigned_clients', []) as $cid)
                                            @php $c = ($clients ?? collect())->firstWhere('id', (int)$cid); @endphp
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
                                         class="text-sm text-gray-500 mt-2 {{ count(old('assigned_clients', [])) ? 'hidden' : '' }}">
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
                                        @foreach(old('assigned_groups', []) as $gid)
                                            @php $g = ($assignedGroups ?? collect())->firstWhere('id', (int)$gid); @endphp
                                            @if($g)
                                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm border border-emerald-100"
                                                      data-group-pill="{{ $g->id }}">
                                                    <span class="font-medium">{{ $g->name }}</span>
                                                    <button type="button"
                                                            class="removeGroupPill text-emerald-700/70 hover:text-emerald-900"
                                                            title="Quitar">✕</button>
                                                    <input type="hidden" name="assigned_groups[]" value="{{ $g->id }}">
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>

                                    <div id="assignedGroupsEmpty"
                                         class="text-sm text-gray-500 mt-2 {{ count(old('assigned_groups', [])) ? 'hidden' : '' }}">
                                        No hay grupos asignados.
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div id="assignError"
                             class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            Si el entrenamiento es <b>Asignado</b>, debes asignar al menos 1 atleta o 1 grupo.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card: Secciones --}}
<div class="bg-white border rounded-xl p-5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Secciones</h2>
        <button type="button" id="addSection"
                class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">
            + Agregar sección
        </button>
    </div>

    <div id="sections" class="mt-4 space-y-4" secLibraryHidden></div>

    <template id="sectionTpl">
        <div class="rounded-xl border p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold text-gray-900">Sección <span class="secNum"></span></div>
                <button type="button" class="removeSec text-sm text-red-600">Eliminar</button>
            </div>

            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                    <input class="sec-name w-full h-10 rounded-lg border-gray-300" required />
                </div>

                <div class="flex items-end gap-3">
                    {{-- <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" class="secAccepts rounded border-gray-300" />
                        Acepta resultados
                    </label> --}}
                    <input type="hidden" class="secAccepts" value="1" />

                    <div class="flex-1">
                        <label class="block text-xs text-gray-600 mb-1">Tipo de resultado</label>
                        <select class="sec-result-type w-full h-10 rounded-lg border-gray-300">
                            <option value="none" selected>Sin resultados</option>
                            <option value="time">Tiempo</option>
                            <option value="weight">Peso</option>
                            <option value="distance">Distancia</option>
                            <option value="rounds">Rounds</option>
                            <option value="sets">Sets</option>
                            <option value="calories">Calorias</option>
                            <option value="points">Puntos</option>
                            <option value="note">Notas</option>
                        </select>
                    </div>

                    {{-- ✅ Unidad (solo si acepta resultados y tiene tipo != none) --}}
                    <div class="flex-1 secUnitWrap hidden">
                        <label class="block text-xs text-gray-600 mb-1">Unidad</label>
                        <select class="sec-unit-id w-full h-10 rounded-lg border-gray-300">
                            <option value="" selected>Selecciona una unidad</option>
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Descripción</label>
                    <textarea class="sec-desc w-full rounded-lg border-gray-300" rows="3"></textarea>
                </div>

 <div class="md:col-span-2">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <!-- Buscador biblioteca -->
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        Biblioteca (buscar y agregar)
      </label>

      <div class="relative">
        <input type="text"
               class="secLibrarySearch w-full h-10 rounded-lg border-gray-300"
               placeholder="Buscar por nombre o ID..." />

        <div class="secLibraryResults absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-sm hidden"></div>
      </div>

      <p class="text-xs text-gray-500 mt-1">
        Opcional: selecciona uno o varios videos.
      </p>
    </div>
 <!-- URL directa -->
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        Video URL (YouTube/Vimeo)
      </label>
      <input type="url"
             class="sec-video-url w-full h-10 rounded-lg border-gray-300"
             placeholder="https://www.youtube.com/watch?v=..." />
      <p class="text-xs text-gray-500 mt-1">
        Opcional: pega un link externo.
      </p>
    </div>
  </div>

  <!-- Pills debajo del buscador -->
  <div class="secLibraryPills mt-3 flex flex-wrap gap-2"></div>
</div>

{{-- <input type="hidden" name="sections[IDX][library_video_ids][]" value="VIDEO_ID"> --}}


                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">Video MP4 (máx 10MB)</label>
                    <input type="file" class="sec-video-file w-full h-10 rounded-lg border-gray-300"
                           accept="video/mp4" />
                    <p class="text-xs text-gray-500 mt-1">Opcional: sube un archivo MP4. Si subes archivo, se usará ese video.</p>
                </div>
            </div>
        </div>
    </template>
</div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('coach.trainings.index') }}"
                   class="px-4 py-2 rounded-lg border text-sm">Cancelar</a>
                <button class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">Guardar</button>
            </div>
        </form>
    </div>

    {{-- JS: Secciones (tu lógica) --}}
    <script>
  window.__units = @json($units);
</script>

    <script>
(() => {
  const sectionsEl = document.getElementById('sections');
  const tpl = document.getElementById('sectionTpl');
  const addBtn = document.getElementById('addSection');
  if (!sectionsEl || !tpl || !addBtn) return;

  const SEARCH_URL = @json(route('coach.library.search'));
  const UNITS = Array.isArray(window.__units) ? window.__units : [];

  const escapeHtml = (str) =>
    String(str).replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[s]);

  // =========================
  // NAMES builder (CRÍTICO)
  // =========================
  function rebuildNames() {
    const cards = sectionsEl.querySelectorAll('[data-sec]');
    cards.forEach((card, idx) => {
      // número visual
      const numSpan = card.querySelector('.secNum');
      if (numSpan) numSpan.textContent = String(idx + 1);

      // fields
      const nameInput = card.querySelector('.sec-name');
      if (nameInput) nameInput.name = `sections[${idx}][name]`;

      const descInput = card.querySelector('.sec-desc');
      if (descInput) descInput.name = `sections[${idx}][description]`;

      const videoUrl = card.querySelector('.sec-video-url');
      if (videoUrl) videoUrl.name = `sections[${idx}][video_url]`;

      const videoFile = card.querySelector('.sec-video-file');
      if (videoFile) videoFile.name = `sections[${idx}][video_file]`;

      const resType = card.querySelector('.sec-result-type');
      if (resType) resType.name = `sections[${idx}][result_type]`;

      const unitId = card.querySelector('.sec-unit-id');
      if (unitId) unitId.name = `sections[${idx}][unit_id]`;

      // biblioteca: renombrar TODOS los hidden inputs de videos
      card.querySelectorAll('input.sec-library-video-id').forEach(inp => {
        inp.name = `sections[${idx}][library_video_ids][]`; // ✅ idx correcto
      });
    });
  }
  window.rebuildNames = rebuildNames;

  // =========================
  // Units UI (result_type -> unit options)
  // =========================
  function toggleUnitUI(card) {
    const resultType = card.querySelector('.sec-result-type');
    const unitWrap = card.querySelector('.secUnitWrap');
    const unitSel = card.querySelector('.sec-unit-id');

    const rt = (resultType?.value || 'none');

    if (!rt || rt === 'none') {
      unitWrap?.classList.add('hidden');
      if (unitSel) unitSel.value = '';
      return;
    }

    const options = UNITS.filter(u => String(u.result_type) === String(rt));

    if (unitSel) {
      const current = unitSel.value || '';
      unitSel.innerHTML =
        `<option value="" selected>Selecciona una unidad</option>` +
        options.map(u => `<option value="${u.id}">${escapeHtml(u.name)} (${escapeHtml(u.symbol)})</option>`).join('');

      if (current && options.some(u => String(u.id) === String(current))) {
        unitSel.value = current;
      } else {
        unitSel.value = '';
      }
    }

    const show = options.length > 0;
    unitWrap?.classList.toggle('hidden', !show);
    if (!show && unitSel) unitSel.value = '';
  }

  // =========================
  // Pills + hidden inputs
  // =========================
  function addVideoPill(card, videoId, videoName) {
    const pills = card.querySelector('.secLibraryPills');
    if (!pills) return;

    // evitar duplicados (por data)
    if (pills.querySelector(`[data-pill-id="${videoId}"]`)) return;

    const pill = document.createElement('span');
    pill.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-xs';
    pill.dataset.pillId = String(videoId);

    pill.innerHTML = `
      <span>${escapeHtml(videoName)}</span>
      <input type="hidden" class="sec-library-video-id" value="${parseInt(videoId, 10)}">
      <button type="button" class="text-slate-500 hover:text-red-600" aria-label="Quitar">&times;</button>
    `;

    pill.querySelector('button')?.addEventListener('click', () => {
      pill.remove();
      rebuildNames();
    });

    pills.appendChild(pill);
    rebuildNames();
  }

  // =========================
  // Add/remove section
  // =========================
  function addSection() {
    const node = tpl.content.cloneNode(true);

    const wrapper = document.createElement('div');
    wrapper.dataset.sec = '1';
    wrapper.appendChild(node);

    // listener: result type
    const resultType = wrapper.querySelector('.sec-result-type');
    if (resultType) {
      resultType.addEventListener('change', () => toggleUnitUI(wrapper));
    }

    // remove section
    wrapper.querySelector('.removeSec')?.addEventListener('click', () => {
      wrapper.remove();
      rebuildNames();
    });

    sectionsEl.appendChild(wrapper);

    // init
    rebuildNames();
    toggleUnitUI(wrapper);
  }

  addBtn.addEventListener('click', addSection);
  addSection(); // 1 sección por defecto

  // =========================
  // Library search (delegation)
  // =========================
  let debounceTimer = null;
  let abortCtrl = null;

  async function doSearch(q) {
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();

    const res = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}`, {
      headers: { 'Accept': 'application/json' },
      signal: abortCtrl.signal,
      credentials: 'same-origin',
    });

    const json = await res.json();
    return Array.isArray(json) ? json : (json.data || []);
  }

  function renderResults(box, items) {
    if (!items || !items.length) {
      box.classList.add('hidden');
      box.innerHTML = '';
      return;
    }

    box.innerHTML = items.map(v => `
      <button type="button"
        class="w-full text-left px-3 py-2 hover:bg-slate-50"
        data-video-id="${v.id}"
        data-video-name="${escapeHtml(v.name)}">
        <div class="text-sm font-medium text-slate-900">${escapeHtml(v.name)}</div>
        <div class="text-xs text-slate-500">ID: ${v.id}${v.youtube_id ? ` · YT: ${escapeHtml(v.youtube_id)}` : ''}</div>
      </button>
    `).join('');

    box.classList.remove('hidden');
  }

  document.addEventListener('input', (e) => {
    const input = e.target.closest('.secLibrarySearch');
    if (!input) return;

    const card = input.closest('[data-sec]'); // ✅ wrapper real
    const box  = card?.querySelector('.secLibraryResults');
    if (!card || !box) return;

    const q = input.value.trim();
    clearTimeout(debounceTimer);

    if (q.length < 2) {
      renderResults(box, []);
      return;
    }

    debounceTimer = setTimeout(async () => {
      try {
        const items = await doSearch(q);
        renderResults(box, items);
      } catch (err) {
        if (err?.name !== 'AbortError') console.error('Library search failed', err);
        renderResults(box, []);
      }
    }, 250);
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.secLibraryResults [data-video-id]');
    if (!btn) return;

    const card = btn.closest('[data-sec]');
    const input = card?.querySelector('.secLibrarySearch');
    const box = card?.querySelector('.secLibraryResults');
    if (!card || !input || !box) return;

    const id = btn.dataset.videoId;
    const name = btn.dataset.videoName || btn.querySelector('.text-sm')?.textContent?.trim() || `Video ${id}`;

    addVideoPill(card, id, name);

    input.value = '';
    box.classList.add('hidden');
    box.innerHTML = '';
  });

  // cerrar dropdown si click fuera
  document.addEventListener('click', (e) => {
    const isInside = e.target.closest('.secLibrarySearch') || e.target.closest('.secLibraryResults');
    if (isInside) return;
    document.querySelectorAll('.secLibraryResults').forEach(b => b.classList.add('hidden'));
  });
})();

function addVideoToSection(sectionEl, videoId, label) {
  // 1) Evitar duplicados
  if (sectionEl.querySelector(`input[data-library-video-id="${videoId}"]`)) return;

  // 2) UI pill (lo tuyo)
  const pillsWrap = sectionEl.querySelector('.secLibraryPills'); // tu contenedor
  pillsWrap.appendChild(makePill(label, () => removeVideoFromSection(sectionEl, videoId)));

  // 3) Hidden input (LO IMPORTANTE)
  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.value = String(videoId);
  hidden.dataset.libraryVideoId = String(videoId);
  hidden.className = 'sec-library-video-id'; // para renombrar luego
  sectionEl.appendChild(hidden);

  // 4) Renombrar todo con el índice real
  rebuildNames();
}

function removeVideoFromSection(sectionEl, videoId) {
  // borrar hidden
  sectionEl.querySelector(`input[data-library-video-id="${videoId}"]`)?.remove();
  rebuildNames();
}

        (function () {
            const form = document.querySelector('form');
            const visibilitySelect = document.getElementById('visibilitySelect');
            const assignBlock = document.getElementById('assignBlock');
            const assignError = document.getElementById('assignError');

            const assignedClients = document.getElementById('assignedClientsPills');
            const assignedGroups  = document.getElementById('assignedGroupsPills');

            const clientBox  = document.getElementById('clientSearchResults');
            const clientInner = document.getElementById('clientSearchResultsInner');
            const clientHint  = document.getElementById('clientSearchHint');
            const clientInput = document.getElementById('clientSearchInput');

            const groupBox  = document.getElementById('groupSearchResults');
            const groupInner = document.getElementById('groupSearchResultsInner');
            const groupHint  = document.getElementById('groupSearchHint');
            const groupInput = document.getElementById('groupSearchInput');

            if (!form || !visibilitySelect || !assignBlock) return;

            function anyAssignments() {
                const anyClient = assignedClients?.querySelector('input[name="assigned_clients[]"]');
                const anyGroup  = assignedGroups?.querySelector('input[name="assigned_groups[]"]');
                return !!(anyClient || anyGroup);
            }

            function clearAssignments() {
                assignedClients && (assignedClients.innerHTML = '');
                assignedGroups  && (assignedGroups.innerHTML = '');

                const emptyClients = document.getElementById('assignedClientsEmpty');
                const emptyGroups  = document.getElementById('assignedGroupsEmpty');
                emptyClients && emptyClients.classList.remove('hidden');
                emptyGroups && emptyGroups.classList.remove('hidden');

                if (clientInput) clientInput.value = '';
                if (groupInput) groupInput.value = '';

                if (clientBox) clientBox.classList.add('hidden');
                if (groupBox) groupBox.classList.add('hidden');

                if (clientInner) clientInner.innerHTML = '';
                if (groupInner) groupInner.innerHTML = '';

                if (clientHint) clientHint.classList.add('hidden');
                if (groupHint) groupHint.classList.add('hidden');
            }

            function syncVisibilityUI() {
                const isAssigned = visibilitySelect.value === 'assigned';
                assignBlock.classList.toggle('hidden', !isAssigned);
                assignError && assignError.classList.add('hidden');

                if (!isAssigned) clearAssignments();
            }

            visibilitySelect.addEventListener('change', syncVisibilityUI);

            form.addEventListener('submit', (e) => {
                if (visibilitySelect.value === 'assigned' && !anyAssignments()) {
                    e.preventDefault();
                    assignError && assignError.classList.remove('hidden');
                    assignBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            syncVisibilityUI();
        })();
 
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

    bindRemoveButtonsWithin(gSelected);
    setEmptyState();
  }
})();
    

    //{{-- JS: tag color "Sin color" --}}
    
        (function () {
            const form = document.querySelector('form');
            const noColorCheck = document.getElementById('noColorCheck');
            const tagColorInput = document.getElementById('tagColorInput');
            if (!form || !noColorCheck || !tagColorInput) return;

            noColorCheck.addEventListener('change', () => {
                if (noColorCheck.checked) {
                    tagColorInput.setAttribute('disabled', 'disabled');
                    let hidden = document.getElementById('tagColorHidden');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'tag_color';
                        hidden.id = 'tagColorHidden';
                        form.appendChild(hidden);
                    }
                    hidden.value = '';
                } else {
                    tagColorInput.removeAttribute('disabled');
                    document.getElementById('tagColorHidden')?.remove();
                }
            });
        })();

        function previewCover(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            const preview = document.getElementById('coverPreview');
            if (!preview) return;

            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover" alt="Cover">`;
            };
            reader.readAsDataURL(file);
        }
    
(() => {
  const SEARCH_URL = @json(route('coach.library.search'));

  const escapeHtml = (str) =>
    String(str).replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[s]);

   function makePill(pillsContainer, id, name) {
    if (pillsContainer.querySelector(`[data-pill-id="${id}"]`)) return;

    const pill = document.createElement('span');
    pill.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-xs';
    pill.dataset.pillId = id;
    
    // El input DEBE tener la clase .pillVideoId para que rebuildNames le asigne el nombre correcto
    pill.innerHTML = `
      <span>${escapeHtml(name)}</span>
      <input type="hidden" class="pillVideoId" value="${parseInt(id)}">
      <button type="button" class="text-slate-500 hover:text-red-600">&times;</button>
    `;
    
    pill.querySelector('button').addEventListener('click', () => {
        pill.remove();
        rebuildNames(); 
    });
    
    pillsContainer.appendChild(pill);
    rebuildNames(); 
}

  async function doSearch(q) {
    const res = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}`, {
      headers: { 'Accept': 'application/json' }
    });
    return res.json();
  }

  function renderResults(box, items) {
    if (!items.length) {
      box.classList.add('hidden');
      box.innerHTML = '';
      return;
    }
    box.innerHTML = items.map(v => `
      <button type="button"
        class="w-full text-left px-3 py-2 hover:bg-slate-50"
        data-video-id="${v.id}"
        data-video-name="${escapeHtml(v.name)}">
        <div class="text-sm font-medium text-slate-900">${escapeHtml(v.name)}</div>
        <div class="text-xs text-slate-500">ID: ${v.id}${v.youtube_id ? ` · YT: ${escapeHtml(v.youtube_id)}` : ''}</div>
      </button>
    `).join('');
    box.classList.remove('hidden');
  }

  // ✅ Event delegation: funciona para inputs existentes y los que se agreguen por template
  let debounceTimer = null;

  document.addEventListener('input', (e) => {
    const input = e.target.closest('.secLibrarySearch');
    if (!input) return;

 // Sube hasta encontrar el contenedor que tiene .secLibraryResults
    const section = input.closest('.rounded-xl.border');
    const box = section?.querySelector('.secLibraryResults');
    if (!box) return;

      const q = input.value.trim();
    clearTimeout(debounceTimer);


    if (q.length < 2) {
      renderResults(box, []);
      return;
    }

    debounceTimer = setTimeout(async () => {
      try {
        const items = await doSearch(q);
        renderResults(box, items);
      } catch (err) {
        console.error('Library search failed', err);
        renderResults(box, []);
      }
    }, 250);
  });

  // Click en un resultado (por ahora solo llena el input con el nombre y cierra dropdown)
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.secLibraryResults [data-video-id]');
    if (!btn) return;

    const section = btn.closest('.rounded-xl.border');
    const input = section?.querySelector('.secLibrarySearch');
    const box = section?.querySelector('.secLibraryResults');
    const pillsContainer = section?.querySelector('.secLibraryPills');

    if (!input || !box || !pillsContainer) return;

    const id   = btn.dataset.videoId;
    const name = btn.dataset.videoName || btn.querySelector('.text-sm')?.textContent?.trim() || '';

    makePill(pillsContainer, id, name);

    input.value = '';
    box.classList.add('hidden');
    box.innerHTML = '';
  });

  // Cerrar dropdown al click fuera
  document.addEventListener('click', (e) => {
    const isInside = e.target.closest('.secLibrarySearch') || e.target.closest('.secLibraryResults');
    if (isInside) return;

    document.querySelectorAll('.secLibraryResults').forEach(box => box.classList.add('hidden'));
  });
})();

function makePill(id, name) {
  const pill = document.createElement('span');
  pill.className = "inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-xs";
  pill.dataset.pillId = id;
  pill.innerHTML = `
    <span>${escapeHtml(name)}</span>
    <button type="button" class="text-slate-500 hover:text-red-600" data-pill-remove aria-label="Quitar">&times;</button>
  `;
  return pill;
}

</script>

</x-app-layout>
