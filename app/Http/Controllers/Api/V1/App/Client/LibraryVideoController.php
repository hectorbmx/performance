<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\LibraryVideo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\UserApp;
use App\Models\Client;
use App\Models\TrainingTypeCatalog;

class LibraryVideoController extends Controller
{
    public function index(Request $request)
    {
 
        $user = $request->user();
        
        

        // 🔒 Resolver coach_id “dueño” de la librería a la que este usuario de app tiene acceso
        $coachId = $this->resolveCoachId($user);
        

        // Si no tiene coach asignado, regresamos vacío (o 403 si prefieres)
        // if (!$coachId) {
        //     return response()->json([
        //         'ok' => true,
        //         'data' => [
        //             'current_page' => 1,
        //             'data' => [],
        //             'total' => 0,
        //         ],
        //     ]);
        // }
        if (!$coachId) {
    return response()->json([
        'ok' => true,
        'debug' => [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'resolved_coach_id' => $coachId,
        ],
        'data' => [
            'current_page' => 1,
            'data' => [],
            'total' => 0,
        ],
    ]);
}

        $data = $request->validate([
            'q' => ['nullable','string','max:150'],
            'training_type_catalog_id' => ['nullable','integer'],
            'per_page' => ['nullable','integer','min:1','max:50'],
        ]);

        $q = trim((string)($data['q'] ?? ''));
        $perPage = (int)($data['per_page'] ?? 20);

        $query = LibraryVideo::query()
            ->visibleForCoach($coachId)
            ->where('is_active', 1);

        // 🔎 Búsqueda
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('youtube_id', $q)
                   ->orWhere('id', $q);
            });
        }

        // 🧩 Filtro por tipo de entrenamiento
        if (!empty($data['training_type_catalog_id'])) {
            $query->where('training_type_catalog_id', $data['training_type_catalog_id']);
        }
       \Log::info('APP LIBRARY index', [
  'user_id' => $request->user()->id,
  'user_email' => $request->user()->email,
  'coach_id_resolved' => $coachId ?? null,
]);

\Log::info('APP LIBRARY counts', [
  'total_in_db' => \App\Models\LibraryVideo::count(),
  'active_in_db' => \App\Models\LibraryVideo::where('is_active',1)->count(),
  'visible_count' => $coachId
      ? \App\Models\LibraryVideo::visibleForCoach($coachId)->where('is_active',1)->count()
      : null,
]);
        $videos = $query
            ->latest()
            ->paginate($perPage, [
                'id',
                'name',
                'youtube_id',
                'youtube_url',
                'thumbnail_url',
                'training_type_catalog_id',
            ]);

        return response()->json([
            'ok' => true,
            'data' => $videos,
        ]);
    }

    public function show(Request $request, LibraryVideo $video)
    {
        $user = $request->user();
        $coachId = $this->resolveCoachId($user);

        // 🔒 Evitar que el usuario lea videos que no son visibles para su coach
        $allowed = LibraryVideo::query()
            ->visibleForCoach($coachId)
            ->where('id', $video->id)
            ->exists();

        abort_unless($allowed, 403);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $video->id,
                'name' => $video->name,
                'youtube_id' => $video->youtube_id,
                'youtube_url' => $video->youtube_url,
                'thumbnail_url' => $video->thumbnail_url,
                'training_type_catalog_id' => $video->training_type_catalog_id,
                'is_active' => (bool)$video->is_active,
                'created_at' => optional($video->created_at)->toISOString(),
            ],
        ]);
    }

    /**
     * Ajusta esto a TU modelo real:
     * - Si el usuario app es "cliente/atleta", normalmente cuelga de un coach.
     */
private function resolveCoachId($user): ?int
{
    // Aquí $user DEBE ser instancia de UserApp
    if (!$user) return null;

    // Cargar relación client si no viene
    if (!$user->relationLoaded('client')) {
        $user->load('client:id,coach_id,is_active,deleted_at');
    }

    $client = $user->client;

    if (!$client || !$client->is_active || $client->deleted_at) {
        return null;
    }

    return (int) $client->coach_id;
}
/**
 * GET /api/v1/library/training/catalog
 */
public function catalog(Request $request)
{
    $user = $request->user();
    $coachId = $this->resolveCoachId($user);

    // Si el usuario no tiene coach, no hay catálogo que mostrar
    if (!$coachId) {
        return response()->json([
            'ok' => true,
            'data' => []
        ]);
    }

    // Traemos las categorías activas que pertenecen al coach
    $catalog = TrainingTypeCatalog::where('coach_id', $coachId)
        ->where('is_active', true)
        ->orderBy('name', 'asc')
        ->get(['id', 'name']);

    return response()->json([
        'ok' => true,
        'data' => $catalog,
    ]);
}
}