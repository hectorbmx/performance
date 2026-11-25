<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\WorkoutExercise;
use App\Services\PlatformWorkoutCatalog;
use App\Services\WorkoutExerciseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WorkoutCatalogController extends Controller
{
    public function index(){return view('admin.workout-catalog.index',['exercises'=>WorkoutExercise::whereNull('coach_id')->orderBy('code')->paginate(30)]);}
    public function edit(WorkoutExercise $exercise,PlatformWorkoutCatalog $service){
        abort_unless($exercise->coach_id===null,404);
        return view('admin.workout-catalog.edit',['exercise'=>$exercise,'fingerprint'=>$service->fingerprint($exercise),
            'taxonomy'=>app(WorkoutExerciseCatalog::class)->meta(),'fields'=>PlatformWorkoutCatalog::FIELDS,
            'reviews'=>DB::table('workout_catalog_reviews')->where('workout_exercise_id',$exercise->id)->latest('id')->limit(10)->get()]);
    }
    public function update(Request $request,WorkoutExercise $exercise,PlatformWorkoutCatalog $service){
        $service->save($request,$exercise);
        return redirect()->route('admin.workout-catalog.edit',$exercise)->with('success',$request->input('action')==='publish'?'Ejercicio revisado y publicado en GYM.':'Catálogo guardado sin publicación.');
    }
}
