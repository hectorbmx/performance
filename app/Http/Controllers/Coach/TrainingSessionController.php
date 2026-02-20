<?php

namespace App\Http\Controllers\Coach;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Group;
use App\Models\Unit;
use App\Models\TrainingGoalCatalog;
use App\Models\TrainingSection;
use App\Models\GroupTrainingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\TrainingTypeCatalog;
use App\Models\LibraryVideo;
use Illuminate\Validation\Rule;
use App\Enums\TrainingSectionResultType;

class TrainingSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  public function index(Request $request)
{
    $coachId = auth()->id();
    $view = $request->get('view', 'list');

    if ($view === 'calendar') {
        
        // month: YYYY-MM (default: mes actual)
        $month = $request->get('month', now()->format('Y-m'));
        $current = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        // grid: inicia lunes y termina domingo (6 semanas típicas)
        $start = $current->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end   = $current->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $items = TrainingSession::query()
            ->where('coach_id', $coachId)
            ->whereBetween('scheduled_at', [$start->toDateString(), $end->toDateString()])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get(['id','title','tag_color','scheduled_at','visibility','level','type']);

        $byDate = $items->groupBy(fn ($t) => $t->scheduled_at->format('Y-m-d'));
// dd($items->take(10)->toArray());
        // Pasamos al blade lo necesario
        return view('coach.trainings.index', [
            'view' => $view,
            'trainings' => null, // en calendar no usamos paginate
            'date' => null,
            'month' => $month,
            'currentMonth' => $current,
            'start' => $start,
            'end' => $end,
            'byDate' => $byDate,
        ]);
    }

    // ---- LIST VIEW (lo que ya tenías) ----
    $date = $request->get('date');

    $query = TrainingSession::query()
        ->where('coach_id', $coachId)
        ->withCount('sections')
        ->orderBy('scheduled_at', 'desc')
        ->orderBy('id', 'desc');

    if ($date) {
        $query->whereDate('scheduled_at', $date);
    }

    $trainings = $query->paginate(15)->withQueryString();

    return view('coach.trainings.index', compact('trainings', 'view', 'date'));
}

    /**
     * Show the form for creating a new resource.
     */


public function create(Request $request)
{
    $date = $request->get('date');
    $coachId = $request->user()->id;

    $clients = Client::where('coach_id', auth()->id())
        ->where('is_active', 1)
        ->orderBy('first_name')
        ->get(['id','first_name','last_name','email']);


    $assignedGroups = collect(); // vacío
        $types = TrainingTypeCatalog::query()
        ->where('coach_id', $coachId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id','name']);


    $types = TrainingTypeCatalog::query()
        ->where('coach_id', $coachId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id','name']);
         // ✅ NUEVO: Goals (catálogo) por coach
    $goals = TrainingGoalCatalog::query()
        ->where('coach_id', $coachId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id','name']);
    $units = Unit::query()
        ->whereNull('coach_id')                 // por ahora solo globales
        ->where('is_active', true)
        ->orderBy('result_type')
        ->orderBy('name')
        ->get(['id','result_type','name','symbol','code']);



return view('coach.trainings.create', compact('date','types','goals','clients','assignedGroups','units'));

}


    /**
     * Store a newly created resource in storage.
     */
//  public function store(Request $request)
// {
//     $coachId = auth()->id();
    
//     $data = $request->validate([
//         'title'            => ['required','string','max:150'],
//         'scheduled_at'     => ['required','date'],
//         'duration_minutes' => ['nullable','integer','min:1','max:600'],

//         'level'            => ['required','in:beginner,intermediate,advanced'],
//         'goal'             => ['required','in:strength,cardio,technique,mobility,mixed'],
//         // 'type'             => ['required','in:fitness,functional_fitness,weightlifting,home_training'],
//         'training_type_catalog_id' => ['nullable','integer'],


//         'visibility'       => ['required','in:free,assigned'],
//         'notes'            => ['nullable','string'],
//         'tag_color'        => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
//         'cover_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
        

//         // sections[]
//         'sections'                         => ['required','array','min:1'],
//         'sections.*.name'                  => ['required','string','max:100'],
//         'sections.*.description'           => ['nullable','string'],
//         'sections.*.video_url'             => ['nullable','url','max:255'],

//         'sections.*.accepts_results'       => ['nullable','boolean'],
//         'sections.*.result_type'           => ['nullable','string','max:30'],
//     ]);
//             if (!empty($data['training_type_catalog_id'])) {
//             $exists = \App\Models\TrainingTypeCatalog::query()
//                 ->where('id', $data['training_type_catalog_id'])
//                 ->where('coach_id', $coachId)
//                 ->exists();

//             if (!$exists) {
//                 return back()
//                     ->withErrors(['training_type_catalog_id' => 'El tipo seleccionado no es válido.'])
//                     ->withInput();
//             }
//         }


//     return DB::transaction(function () use ($data, $coachId,$request) {
//     $coverPath = null;
//         if ($request->hasFile('cover_image')) {
//             $coverPath = $request->file('cover_image')->store('training-covers', 'public');
//         }
//             $training = TrainingSession::create([
//                 'coach_id'                 => $coachId,
//                 'title'                    => $data['title'],
//                 'scheduled_at'             => $data['scheduled_at'],
//                 'duration_minutes'         => $data['duration_minutes'] ?? null,
//                 'level'                    => $data['level'],
//                 'goal'                     => $data['goal'],
//                 // 'type'                     => $data['type'],
//                 'training_type_catalog_id' => ['nullable','integer'],
//                 'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
//                 // 'type' => $data['type'] ?? $training->type, // fallback

//                 'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
//                 'visibility'               => $data['visibility'],
//                 'notes'                    => $data['notes'] ?? null,
//                 'tag_color'                => $data['tag_color'] ?? null,
//                 'cover_image'              => $coverPath,
//             ]);

//         // Guardar secciones con order
//         $order = 1;
//         foreach ($data['sections'] as $s) {
//             $accepts = !empty($s['accepts_results']);

//             $training->sections()->create([
//                 'order'           => $order++,
//                 'name'            => $s['name'],
//                 'description'     => $s['description'] ?? null,
//                 'video_url' => $s['video_url'] ?? null,

//                 'accepts_results' => $accepts,
//                 'result_type'     => $accepts ? ($s['result_type'] ?? null) : null,
//             ]);
//         }

//         return redirect()
//             ->route('coach.trainings.index')
//             ->with('success', 'Entrenamiento creado correctamente.');
//     });
// }
public function store(Request $request)
{
    $coachId = auth()->id();

    $data = $request->validate([
        'title'            => ['required','string','max:150'],
        'scheduled_at'     => ['required','date'],
        'duration_minutes' => ['nullable','integer','min:1','max:600'],
        'level'            => ['required','in:beginner,intermediate,advanced'],
        // 'goal'             => ['required','in:strength,cardio,technique,mobility,mixed'],
        'training_goal_catalog_id' => ['required','integer','exists:training_goal_catalogs,id'],


        'visibility'       => ['required','in:free,assigned'],
        'notes'            => ['nullable','string'],
        'tag_color'        => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
        'cover_image'      => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],

        // ✅ asignaciones (vienen del blade)
        'assigned_clients'   => ['nullable','array'],
        'assigned_clients.*' => ['integer'],

        'assigned_groups'    => ['nullable','array'],
        'assigned_groups.*'  => ['integer'],

        // sections[]
        'sections'                         => ['required','array','min:1'],
        'sections.*.name'                  => ['required','string','max:100'],
        'sections.*.description'           => ['nullable','string'],
        'sections.*.video_url'             => ['nullable','url','max:255'],
        'sections.*.video_file'            => ['nullable','file','mimetypes:video/mp4','max:10240'],
        // 'sections.*.accepts_results'       => ['nullable','boolean'],
        // 'sections.*.result_type'           => ['nullable','string','max:30'],
        // 'sections.*.result_type' => [
        //             'required',
        //             Rule::in(TrainingSectionResultType::values()),
        //         ],
        'sections.*.result_type' => [
            'nullable',
            Rule::in(array_merge(['none'], TrainingSectionResultType::values())),
            ],
        'sections.*.unit_id' => ['nullable','integer','exists:units,id'],

    ]);
foreach (($data['sections'] ?? []) as $idx => $s) {
    $rt = $s['result_type'] ?? 'none';
    $unitId = $s['unit_id'] ?? null;

    if ($rt === 'none' && !empty($unitId)) {
        return back()
            ->withErrors(["sections.$idx.unit_id" => 'No se permite unidad si la sección es "Sin resultados".'])
            ->withInput();
    }

    // Para estos tipos, la unidad es requerida
    $requiresUnit = in_array($rt, ['weight','time','distance','reps','rounds','sets','calories','points'], true);

    if ($requiresUnit && empty($unitId)) {
        return back()
            ->withErrors(["sections.$idx.unit_id" => 'Selecciona una unidad para este tipo de resultado.'])
            ->withInput();
    }
}

    // Validar que el type catalog pertenezca al coach
    if (!empty($data['training_type_catalog_id'])) {
        $exists = \App\Models\TrainingTypeCatalog::query()
            ->where('id', $data['training_type_catalog_id'])
            ->where('coach_id', $coachId)
            ->exists();

        if (!$exists) {
            return back()
                ->withErrors(['training_type_catalog_id' => 'El tipo seleccionado no es válido.'])
                ->withInput();
        }
    }

    // ✅ regla: si es assigned debe traer al menos 1 atleta o 1 grupo
    $clientIds = $data['assigned_clients'] ?? [];
    $groupIds  = $data['assigned_groups'] ?? [];

    if (($data['visibility'] ?? 'free') === 'assigned' && count($clientIds) === 0 && count($groupIds) === 0) {
        return back()
            ->withErrors(['visibility' => 'Si el entrenamiento es Asignado, debes asignar al menos 1 atleta o 1 grupo.'])
            ->withInput();
    }

    return DB::transaction(function () use ($data, $coachId, $request, $clientIds, $groupIds) {

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('training-covers', 'public');
        }

        $training = TrainingSession::create([
            'coach_id'                 => $coachId,
            'title'                    => $data['title'],
            'scheduled_at'             => $data['scheduled_at'],
            'duration_minutes'         => $data['duration_minutes'] ?? null,
            'level'                    => $data['level'],
            // 'training_goal_catalog_id' => $data['training_goal_catalog_id'],
            'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
            'visibility'               => $data['visibility'],
            'notes'                    => $data['notes'] ?? null,
            'tag_color'                => $data['tag_color'] ?? null,
            'cover_image'              => $coverPath,
        ]);

        // Secciones
$order = 1;
foreach ($data['sections'] as $idx => $s) {

    $rt = $s['result_type'] ?? null;
    $unitId = $s['unit_id'] ?? null;

    // "none" => sin resultados
    $accepts = !empty($rt) && $rt !== 'none';

    $videoPath = null;
    if ($request->hasFile("sections.$idx.video_file")) {
        $videoPath = $request->file("sections.$idx.video_file")
            ->store("training-section-videos/coach-{$coachId}", 'public');
    }

    $training->sections()->create([
        'order'           => $order++,
        'name'            => $s['name'],
        'description'     => $s['description'] ?? null,
        'video_url'       => $videoPath ? null : ($s['video_url'] ?? null),
        'video_path'      => $videoPath,
        'accepts_results' => $accepts ? 1 : 0,
        'result_type'     => $accepts ? $rt : null,
        'unit_id' => $accepts ? $unitId : null,
    ]);
}


        // ✅ Asignaciones (ESTO era lo que ya no existía)
       // ✅ Asignaciones (según tu modelo real)
if ($training->visibility === 'assigned') {

    // Clientes
    foreach (array_unique($clientIds) as $clientId) {
        $training->assignments()->create([
            'client_id'     => $clientId,
            'scheduled_for' => $training->scheduled_at, // ✅
            'status'        => 'scheduled',             // ✅ recomendado
        ]);
    }

    // Grupos -> OJO: es otra tabla
    foreach (array_unique($groupIds) as $groupId) {
        \App\Models\GroupTrainingAssignment::create([
            'group_id'           => $groupId,
            'training_session_id'=> $training->id,
            'scheduled_for'      => $training->scheduled_at, // ✅
            'notes'              => null,
        ]);
    }

} else {
    $training->assignments()->delete();
    \App\Models\GroupTrainingAssignment::where('training_session_id', $training->id)->delete();
}



        return redirect()
            ->route('coach.trainings.index')
            ->with('success', 'Entrenamiento creado correctamente.');
    });
}


public function edit(TrainingSession $training)
        {
            if ($training->coach_id !== auth()->id()) {
                abort(403);
            }

            $training->load([
                'sections' => fn($q) => $q->orderBy('order'),
                'sections.libraryVideos' => fn($q) => $q->orderBy('training_section_library_videos.order'),
            ]);

            $clients = Client::where('coach_id', auth()->id())
                ->where('is_active', 1)
                ->orderBy('first_name')
                ->get(['id','first_name','last_name','email']);

            $assignedClientIds = $training->assignedClients()->pluck('clients.id')->all();

            $assignedGroupIds = GroupTrainingAssignment::where('training_session_id', $training->id)
                ->pluck('group_id')
                ->all();

            $assignedGroups = \App\Models\Group::where('coach_id', auth()->id())
                ->whereIn('id', $assignedGroupIds)
                ->orderBy('name')
                ->get(['id','name']);

            // ✅ NUEVO: tipos del coach
            $types = TrainingTypeCatalog::query()
                ->where('coach_id', auth()->id())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id','name']);
            $goals = TrainingGoalCatalog::query()
                ->where('coach_id', auth()->id())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id','name']);
            $units = Unit::query()
                ->whereNull('coach_id')                 // por ahora solo globales
                ->where('is_active', true)
                ->orderBy('result_type')
                ->orderBy('name')
                ->get(['id','result_type','name','symbol','code']);

            $libraryVideos = LibraryVideo::query()
                ->visibleForCoach(auth()->id())
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(['id','name','youtube_url']);

            return view('coach.trainings.edit', compact(
                'training',
                'clients',
                'assignedClientIds',
                'assignedGroups',
                'goals',
                'types', // ✅ NUEVO
                'units',
                'libraryVideos'
            ));
        }


public function update(Request $request, TrainingSession $training)
{
    abort_unless($training->coach_id === auth()->id(), 403);

    $coachId = auth()->id();

    $data = $request->validate([
        'title'            => ['required','string','max:150'],
        'scheduled_at'     => ['required','date'],
        'duration_minutes' => ['nullable','integer','min:1','max:600'],
        'level'      => ['required','in:beginner,intermediate,advanced'],
        // 'goal'       => ['required','in:strength,cardio,technique,mobility,mixed'],
        'training_goal_catalog_id' => ['required','integer','exists:training_goal_catalogs,id'],
        'training_type_catalog_id' => [
            'nullable',
            'integer',
            Rule::exists('training_type_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
        ],

        'visibility' => ['required','in:free,assigned'],
        'notes'      => ['nullable','string'],
        'cover_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        // --- SECCIONES ---
        'sections'                   => ['required','array','min:1'],
        'sections.*.id'              => ['nullable','integer'],
        'sections.*.name'            => ['required','string','max:100'],
        'sections.*.description'     => ['nullable','string'],
        'sections.*.video_url'       => ['nullable','url','max:255'],
        'sections.*.video_file'      => ['nullable','file','mimetypes:video/mp4','max:10240'],
        'sections.*.library_video_ids'   => ['nullable','array'],
        'sections.*.library_video_ids.*' => ['integer','exists:library_videos,id'],
        // 'sections.*.accepts_results' => ['nullable','boolean'],
        // 'sections.*.result_type'     => ['nullable','string','max:30'],
         'sections.*.result_type' => [
            'required',
            Rule::in(array_merge(['none'], TrainingSectionResultType::values())),
        ],
        'sections.*.unit_id' => ['nullable','integer','exists:units,id'],
                
        'video_url' => ['nullable','url','max:255'],
        // --- ASIGNACIONES ---
        'assigned_clients'   => ['nullable','array'],
        'assigned_clients.*' => ['integer'],
        'assigned_groups'    => ['nullable','array'],
        'assigned_groups.*'  => ['integer','exists:groups,id'],
    ]);
    foreach (($data['sections'] ?? []) as $idx => $s) {
        $rt = $s['result_type'] ?? 'none';
        $unitId = $s['unit_id'] ?? null;

        if ($rt === 'none' && !empty($unitId)) {
            return back()
                ->withErrors(["sections.$idx.unit_id" => 'No se permite unidad si la sección es "Sin resultados".'])
                ->withInput();
        }

        // Para estos tipos, la unidad es requerida
        $requiresUnit = in_array($rt, ['weight','time','distance','reps','rounds','sets','calories','points'], true);

        if ($requiresUnit && empty($unitId)) {
            return back()
                ->withErrors(["sections.$idx.unit_id" => 'Selecciona una unidad para este tipo de resultado.'])
                ->withInput();
        }
    }


    $visibility = $data['visibility'];

    // Si es ASSIGNED, exige al menos 1 asignación
    if ($visibility === 'assigned') {
        $hasClients = !empty($request->input('assigned_clients', []));
        $hasGroups  = !empty($request->input('assigned_groups', []));

        if (!$hasClients && !$hasGroups) {
            return back()
                ->withErrors(['assigned_clients' => 'Debes seleccionar al menos 1 atleta o 1 grupo si el entrenamiento es "Asignado".'])
                ->withInput();
        }
    }

    DB::transaction(function () use ($training, $data, $request, $visibility, $coachId) {
    $scheduledFor = \Carbon\Carbon::parse($data['scheduled_at'])->toDateString();

        // Cover image
        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('training-covers', 'public');

            if (!empty($training->cover_image)) {
                Storage::disk('public')->delete($training->cover_image);
            }

            $data['cover_image'] = $path;
        }

        // 1) Cabecera
        $training->update([
            'title'                    => $data['title'],
            'scheduled_at'             => $data['scheduled_at'],
            'duration_minutes'         => $data['duration_minutes'] ?? null,
            'level'                    => $data['level'],
            // 'goal'                     => $data['goal'],
            'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
            // 'type' legacy: no lo tocamos si no viene en el request
            'visibility'               => $data['visibility'],
            'notes'                    => $data['notes'] ?? null,
            ...(isset($data['cover_image']) ? ['cover_image' => $data['cover_image']] : []),
        ]);

        // 2) Asignaciones (regla definitiva)
        if ($visibility === 'free') {
            // limpiar SIEMPRE
            $training->assignedClients()->sync([]);
            GroupTrainingAssignment::where('training_session_id', $training->id)->delete();
        } else {
            // = assigned: sync clients
            $selectedClientIds = $request->input('assigned_clients', []);

            $selectedClientIds = \App\Models\Client::where('coach_id', $coachId)
                ->whereIn('id', $selectedClientIds)
                ->pluck('id')
                ->all();

            $training->assignedClients()->sync(
                collect($selectedClientIds)->mapWithKeys(fn($id) => [$id => ['status' => 'scheduled']])->all()
            );

            // sync groups via group_training_assignments
            $rawGroupIds = $request->input('assigned_groups', []);

            $groupIds = \App\Models\Group::where('coach_id', $coachId)
                ->whereIn('id', $rawGroupIds)
                ->pluck('id')
                ->all();
            \DB::table('training_assignments')
                ->where('training_session_id', $training->id)
                ->update(['scheduled_for' => $scheduledFor]);


            $scheduledFor = \Carbon\Carbon::parse($data['scheduled_at'])->toDateString();

            $training->assignedClients()->sync(
                    collect($selectedClientIds)->mapWithKeys(fn($id) => [
                        $id => [
                            'status'        => 'scheduled',
                            'scheduled_for' => $scheduledFor,
                        ]
                    ])->all()
                );

            $existingGroupIds = GroupTrainingAssignment::where('training_session_id', $training->id)
                ->pluck('group_id')
                ->all();

            $toDeleteGroups = array_values(array_diff($existingGroupIds, $groupIds));
            if (!empty($toDeleteGroups)) {
                GroupTrainingAssignment::where('training_session_id', $training->id)
                    ->whereIn('group_id', $toDeleteGroups)
                    ->delete();
            }

            $toCreateGroups = array_values(array_diff($groupIds, $existingGroupIds));
            foreach ($toCreateGroups as $gid) {
                GroupTrainingAssignment::create([
                    'group_id'            => $gid,
                    'training_session_id' => $training->id,
                    'scheduled_for'       => $scheduledFor,
                    'notes'               => null,
                ]);
            }

            // Si cambió la fecha: actualizar todos
            GroupTrainingAssignment::where('training_session_id', $training->id)
                ->update(['scheduled_for' => $scheduledFor]);
        }

        // 3) Secciones: eliminar quitadas
        $existingIds = $training->sections()->pluck('id')->all();
        $sentIds     = collect($data['sections'])->pluck('id')->filter()->all();

        $toDelete = array_diff($existingIds, $sentIds);
        if (!empty($toDelete)) {
            $sectionsToDelete = \App\Models\TrainingSection::whereIn('id', $toDelete)->get();
            foreach ($sectionsToDelete as $sectionToDelete) {
                if (!empty($sectionToDelete->video_path)) {
                    Storage::disk('public')->delete($sectionToDelete->video_path);
                }
            }
            \App\Models\TrainingSection::whereIn('id', $toDelete)->delete();
        }

        // 4) Secciones: crear/actualizar (manteniendo orden)
        $order = 1;
        foreach ($data['sections'] as $idx => $s) {

            $rt = $s['result_type'] ?? null;
            $unitId = $s['unit_id'] ?? null;

            // $accepts = !empty($s['accepts_results']);
            $accepts = ($rt !== 'none');

            $existingSection = null;
            if (!empty($s['id'])) {
                $existingSection = \App\Models\TrainingSection::query()
                    ->where('id', $s['id'])
                    ->where('training_session_id', $training->id)
                    ->first();
            }

            $videoPath = $existingSection?->video_path;
            $videoUrl = $s['video_url'] ?? null;

            if ($request->hasFile("sections.$idx.video_file")) {
                $videoPath = $request->file("sections.$idx.video_file")
                    ->store("training-section-videos/coach-{$coachId}", 'public');
                $videoUrl = null;

                if (!empty($existingSection?->video_path)) {
                    Storage::disk('public')->delete($existingSection->video_path);
                }
            }

            $payload = [
                'order'           => $order++,
                'name'            => $s['name'],
                'description'     => $s['description'] ?? null,
                'video_url'       => $videoPath ? null : $videoUrl,
                'video_path'      => $videoPath,
                'accepts_results' => $accepts ? 1 : 0,
                'result_type'     => $accepts ? $rt : null,
                'unit_id'         => $accepts ? $unitId : null,
            ];

            $section = $existingSection;
            if ($section) {
                $section->update($payload);
            } else {
                $section = $training->sections()->create($payload);
            }

            $libraryVideoIds = collect($s['library_video_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->unique()->values();

            $allowedLibraryVideoIds = LibraryVideo::query()
                ->visibleForCoach($coachId)
                ->where('is_active', 1)
                ->whereIn('id', $libraryVideoIds)
                ->pluck('id')
                ->all();

            $syncPayload = collect($allowedLibraryVideoIds)
                ->values()
                ->mapWithKeys(fn ($videoId, $order) => [$videoId => ['order' => $order + 1]])
                ->all();

            $section->libraryVideos()->sync($syncPayload);
        }
    });

    // Mensaje distinto si quedó FREE (opcional)
    $msg = ($data['visibility'] === 'free')
        ? 'Entrenamiento actualizado. Se cambió a "Libre" y se eliminaron las asignaciones.'
        : 'Entrenamiento actualizado correctamente.';

    return redirect()
        ->route('coach.trainings.index')
        ->with('success', $msg);
}


   public function destroy(TrainingSession $training)
        {
            abort_unless($training->coach_id === auth()->id(), 403);

            $training->delete(); // si usas SoftDeletes, queda en papelera

            return redirect()
                ->route('coach.trainings.index', request()->query())
                ->with('success', 'Entrenamiento eliminado correctamente.');
        }

}
