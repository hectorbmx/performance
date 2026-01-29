<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrainingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Ajusta si tu client_id vive en otro lado (ej: usuario_app, profile, etc.)
        $clientId = $user->client_id ?? null;

        if (!$clientId) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no identificado.',
            ], 422);
        }

        $from   = $request->query('from');
        $to     = $request->query('to');
        $status = $request->query('status');
        // $includeFree = filter_var($request->query('include') === 'free', FILTER_VALIDATE_BOOL);
        // $includeFree = $request->query('include') === 'free';
        $includeFree = $request->query('include') !== 'no-free';


        $pivotTable = 'client_group';

        // 1) Grupos del cliente
        $groupIds = DB::table($pivotTable)
            ->where('client_id', $clientId)
            ->pluck('group_id');

        if ($groupIds->isNotEmpty()) {

            // 2) Asignaciones grupales aplicables (filtradas por fechas si vienen)
            $groupTrainings = DB::table('group_training_assignments')
                ->whereIn('group_id', $groupIds)
                ->when($from, fn($q) => $q->whereDate('scheduled_for', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('scheduled_for', '<=', $to))
                ->get(['group_id', 'training_session_id', 'scheduled_for']);

            if ($groupTrainings->isNotEmpty()) {

                // 3) Traer assignments existentes para evitar duplicados
                //    (por client + session + scheduled_for)
                $existing = DB::table('training_assignments')
                    ->where('client_id', $clientId)
                    ->whereNotNull('scheduled_for')
                    ->get(['training_session_id', 'scheduled_for'])
                    ->map(fn($r) => $r->training_session_id . '|' . $r->scheduled_for)
                    ->flip();

                // 4) Preparar inserts faltantes
                $now = now();
                $inserts = [];

                foreach ($groupTrainings as $gt) {
                    $key = $gt->training_session_id . '|' . $gt->scheduled_for;

                    if (!isset($existing[$key])) {
                        $inserts[] = [
                            'training_session_id' => $gt->training_session_id,
                            'client_id' => $clientId,
                            'scheduled_for' => $gt->scheduled_for,
                            'status' => 'scheduled',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($inserts)) {
                    DB::table('training_assignments')->insert($inserts);
                }
            }
        }

        // -----------------------------
        // 1) PERSONALES (training_assignments)
        // -----------------------------
        $personal = DB::table('training_assignments as ta')
            ->join('training_sessions as ts', 'ts.id', '=', 'ta.training_session_id')
            ->where('ta.client_id', $clientId)
            ->whereNull('ts.deleted_at')
            ->whereNotNull('ta.scheduled_for')
            ->when($status, fn($q) => $q->where('ta.status', $status))
            ->when($from, fn($q) => $q->whereDate(DB::raw('COALESCE(ta.scheduled_for, ts.scheduled_at)'), '>=', $from))
            ->when($to, fn($q) => $q->whereDate(DB::raw('COALESCE(ta.scheduled_for, ts.scheduled_at)'), '<=', $to))

            ->select([
                DB::raw("'personal' as source"),
                'ta.id as assignment_id',
                'ta.status',
                // DB::raw('DATE(ts.scheduled_at) as scheduled_for'),
                DB::raw('DATE(COALESCE(ta.scheduled_for, ts.scheduled_at)) as scheduled_for'),


                'ts.id as training_session_id',
                'ts.coach_id',
                'ts.title',
                'ts.cover_image',
                'ts.duration_minutes',
                'ts.level',
                'ts.goal',
                'ts.type',
                'ts.visibility',
                'ts.notes',

                DB::raw("NULL as group_id"),
                DB::raw("NULL as group_name"),

            ]);

        // -----------------------------
        // 2) GRUPALES (group_training_assignments)
        //    Requiere pivot cliente<->grupo
        // -----------------------------
        // $pivotTable = 'client_group'; // <-- CAMBIA AQUÍ si tu pivot se llama distinto

        $group = DB::table('training_assignments as ta')
                ->join('training_sessions as ts', 'ts.id', '=', 'ta.training_session_id')

                // Enlazamos contra group_training_assignments por session + fecha
                ->join('group_training_assignments as gta', function ($join) {
                    $join->on('gta.training_session_id', '=', 'ta.training_session_id')
                        ->on('gta.scheduled_for', '=', 'ta.scheduled_for');
                })
                ->join('groups as g', 'g.id', '=', 'gta.group_id')
                ->join($pivotTable . ' as gc', 'gc.group_id', '=', 'g.id')

                ->where('ta.client_id', $clientId)
                ->whereNotNull('ta.scheduled_for')
                ->where('gc.client_id', $clientId)
                ->whereNull('ts.deleted_at')
                ->when($status, fn($q) => $q->where('ta.status', $status))
                ->when($from, fn($q) => $q->whereDate('ta.scheduled_for', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('ta.scheduled_for', '<=', $to))

                ->select([
                    DB::raw("'group' as source"),
                    'ta.id as assignment_id',
                    'ta.status',
                    DB::raw('DATE(ta.scheduled_for) as scheduled_for'),

                    'ts.id as training_session_id',
                    'ts.coach_id',
                    'ts.title',
                    'ts.cover_image',
                    'ts.duration_minutes',
                    'ts.level',
                    'ts.goal',
                    'ts.type',
                    'ts.visibility',
                    'ts.notes',

                    'g.id as group_id',
                    'g.name as group_name',
                ]);

        // -----------------------------
        // 3) LIBRES (training_sessions.visibility = free)
        // -----------------------------
        $free = DB::table('training_sessions as ts')
            ->where('ts.visibility', 'free')
            ->whereNull('ts.deleted_at')
            // Nota: por tu diseño, "cada coach tiene su set público".
            // Para filtrar por coach del cliente necesitaríamos resolver coach_id del cliente.
            // Aquí lo dejo sin filtro; lo conectamos cuando me digas cómo obtienes coach_id.
            ->select([
                DB::raw("'free' as source"),
                DB::raw("NULL as assignment_id"),
                DB::raw("NULL as status"),
                // DB::raw("NULL as scheduled_for"),
                DB::raw("DATE(ts.scheduled_at) as scheduled_for"), // ✅ antes NULL


                'ts.id as training_session_id',
                'ts.coach_id',
                'ts.title',
                'ts.cover_image',
                'ts.duration_minutes',
                'ts.level',
                'ts.goal',
                'ts.type',
                'ts.visibility',
                'ts.notes',
                DB::raw("NULL as group_id"),    // ✅ agregado
                DB::raw("NULL as group_name"),  // ✅ agregado
            ]);

        // Unificamos personal + group (+ free opcional)
        $union = $personal->unionAll($group);

        if ($includeFree) {
            $union = $union->unionAll($free);
        }

        $rows = DB::query()
            ->fromSub($union, 'x')
            ->orderByRaw('CASE WHEN scheduled_for IS NULL THEN 1 ELSE 0 END') // nulls al final
            ->orderBy('scheduled_for')
            ->orderBy('training_session_id')
            ->get();

            $assignmentIds = $rows
                ->pluck('assignment_id')
                ->filter()
                ->unique()
                ->values();

            $sectionsWithResultsByAssignment = DB::table('training_section_results')
                ->whereIn('training_assignment_id', $assignmentIds)
                ->select(
                    'training_assignment_id',
                    DB::raw('COUNT(DISTINCT training_section_id) as completed')
                )
                ->groupBy('training_assignment_id')
                ->pluck('completed', 'training_assignment_id');


        // -----------------------------
        // Progreso (MVP): basado en secciones con resultados (cuando ya exista training_section_results)
        // Por ahora: calculamos secciones_total.
        // -----------------------------
        $sessionIds = $rows->pluck('training_session_id')->unique()->values();

        $sectionsTotalBySession = DB::table('training_sections')
            ->whereIn('training_session_id', $sessionIds)
            ->select('training_session_id', DB::raw('COUNT(*) as total'))
            ->groupBy('training_session_id')
            ->pluck('total', 'training_session_id');

        // // $data = $rows->map(function ($r) use ($sectionsTotalBySession) {
            // $sectionsTotal = (int)($sectionsTotalBySession[$r->training_session_id] ?? 0);
                // $data = $rows->map(function ($r) use ($sectionsTotalBySession, $sectionsWithResultsByAssignment) {
                $data = $rows->map(function ($r) use ($sectionsTotalBySession, $sectionsWithResultsByAssignment) {

    $sectionsTotal = (int)($sectionsTotalBySession[$r->training_session_id] ?? 0); // ✅ ESTA LÍNEA

               $completed = (int)($sectionsWithResultsByAssignment[$r->assignment_id] ?? 0);
                $pct = $sectionsTotal > 0
                    ? (int)round(($completed / $sectionsTotal) * 100)
                    : 0;
            $coverUrl = $r->cover_image
                ? url(Storage::disk('public')->url($r->cover_image))
                : null;
            return [
                'assignment_id' => $r->assignment_id ? (int)$r->assignment_id : null,
                'source' => $r->source,
                'status' => $r->status,
                'scheduled_for' => $r->scheduled_for,

                'training_session' => [
                    'id' => (int)$r->training_session_id,
                    'coach_id' => (int)$r->coach_id,
                    'title' => $r->title,
                    'cover_image' => $coverUrl,
                    'duration_minutes' => $r->duration_minutes,
                    'level' => $r->level,
                    'goal' => $r->goal,
                    'type' => $r->type,
                    'visibility' => $r->visibility,
                    'notes' => $r->notes,
                ],

                'group' => $r->source === 'group'
                    ? ['id' => (int)$r->group_id, 'name' => $r->group_name]
                    : null,

                // 'progress' => [
                //     'sections_total' => $sectionsTotal,
                //     'sections_with_results' => 0,
                //     'pct' => $sectionsTotal > 0 ? 0 : 0,
                // ],
            

                'progress' => [
                    'sections_total' => $sectionsTotal,
                    'sections_with_results' => $completed,
                    'pct' => $pct,
                ],

            ];
        });

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }
}
