<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WorkoutOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutOwnershipController extends Controller
{
    /** Proof for local migration; an owner supplied by the device is never trusted. */
    public function show(Request $request, WorkoutOwnership $ownership)
    {
        $actor=$ownership->athlete($request);
        $contexts=DB::table('workout_ownership_claims')->where('user_app_id',$actor->id)
            ->get(['legacy_client_id','legacy_coach_id'])->map(fn($r)=>[(int)$r->legacy_client_id,(int)$r->legacy_coach_id])
            ->filter(fn($r)=>$r[0]>0 && $r[1]>0)->values()->all();
        $client=$actor->client;
        $current=$client?->is_active ? [(int)$client->id,(int)$client->coach_id] : null;
        if($current)$contexts[]=$current;
        $executions=$ownership->executions($actor)->get(['uuid','data_scope']);
        $assignments=$current ? DB::table('training_assignments as a')->join('training_sessions as t','t.id','=','a.training_session_id')
            ->where('a.client_id',$current[0])->where('t.coach_id',$current[1])->pluck('a.id')->map(fn($id)=>(int)$id)->all() : [];
        return response()->json(['ok'=>true,'data'=>['user_app_id'=>(int)$actor->id,'contexts'=>$contexts,'current_context'=>$current,
            'personal'=>$executions->where('data_scope',WorkoutOwnership::PERSONAL)->pluck('uuid')->values(),
            'assigned'=>$executions->where('data_scope',WorkoutOwnership::ASSIGNED)->pluck('uuid')->values(),
            'assignments'=>$assignments]]);
    }
}
