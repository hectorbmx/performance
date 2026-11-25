<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutExercise;
use App\Services\WorkoutExerciseCatalog;
use Illuminate\Http\Request;

class WorkoutExerciseCatalogController extends Controller
{
    private function actor(Request $request): array
    {
        $user = $request->user();
        if ($user instanceof User) {
            abort_unless($request->is('api/v1/coach/*'), 403);
            return [(int) $user->id, true]; // coach.api guards coach routes.
        }
        abort_unless($user instanceof UserApp && $user->is_active, 403);
        return [null, false];
    }
    public function index(Request $request, WorkoutExerciseCatalog $catalog)
    {
        [$coach, $isCoach] = $this->actor($request);
        return response()->json(['ok' => true] + $catalog->search($request, $coach, $isCoach));
    }
    public function meta(Request $request, WorkoutExerciseCatalog $catalog)
    {
        $this->actor($request); return response()->json(['ok' => true, 'data' => $catalog->meta()]);
    }
    public function show(Request $request, int $exercise, WorkoutExerciseCatalog $catalog)
    {
        [$coach, $isCoach] = $this->actor($request);
        $query=$isCoach ? WorkoutExercise::visibleForCoach($coach) : WorkoutExercise::whereNull('coach_id')->where('review_status','reviewed');
        $item = $query->where('is_active',true)->findOrFail($exercise);
        return response()->json(['ok' => true, 'data' => $catalog->payload($item, $isCoach ? $coach : null)]);
    }
    public function store(Request $request, WorkoutExerciseCatalog $catalog)
    {
        [$coach, $isCoach] = $this->actor($request); abort_unless($isCoach, 403);
        return response()->json(['ok' => true, 'data' => $catalog->payload($catalog->save($request, $coach), $coach)], 201);
    }
    public function update(Request $request, int $exercise, WorkoutExerciseCatalog $catalog)
    {
        [$coach, $isCoach] = $this->actor($request); abort_unless($isCoach, 403);
        $item = WorkoutExercise::where('coach_id', $coach)->findOrFail($exercise);
        return response()->json(['ok' => true, 'data' => $catalog->payload($catalog->save($request, $coach, $item), $coach)]);
    }
}
