<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Conservative transition: no email matching, no inferred author for unrecorded starts. */
class WorkoutOwnershipTransition
{
    private const TABLES=['workout_executions','workout_max_records','workout_load_suggestions','workout_exercise_goals'];

    private function content($value): array
    {
        try {return is_array($value) ? $value : json_decode($value??'{}',true,512,JSON_THROW_ON_ERROR);}
        catch(\Throwable){return [];}
    }
    public function report(): array
    {
        $users=DB::table('user_apps')->whereNull('deleted_at')->get()->keyBy('id');
        $plans=[];$exceptions=[];$personal=[];$snapshots=[];
        $add=function(string $table,object $row,?int $owner,string $scope,string $evidence)use(&$plans){
            $plans[]=['table'=>$table,'id'=>(int)$row->id,'owner_user_app_id'=>$owner,'data_scope'=>$scope,
                'legacy_client_id'=>$row->client_id,'legacy_coach_id'=>$row->coach_id,'evidence'=>$evidence];
        };
        foreach(DB::table('workout_executions')->orderBy('id')->get() as $row) {
            $snapshot=DB::table('workout_execution_prescriptions')->where('workout_execution_id',$row->id)->first();
            $content=$this->content($snapshot?->content);$snapshots[$row->id]=[$content];
            foreach(DB::table('workout_prescription_revisions')->where('workout_execution_id',$row->id)->get() as $revision) $snapshots[$row->id][]=$this->content($revision->content);
            if($row->data_scope===WorkoutOwnership::PERSONAL){$personal[$row->id]=(int)$row->owner_user_app_id;continue;}
            if($row->data_scope!==WorkoutOwnership::LEGACY)continue;
            if(($content['source']??null)==='existing_assignment' && $row->training_assignment_id && $row->training_session_id) {
                $valid=DB::table('training_assignments')->where('id',$row->training_assignment_id)->where('client_id',$row->client_id)->where('training_session_id',$row->training_session_id)->exists()
                    && DB::table('training_sessions')->where('id',$row->training_session_id)->where('coach_id',$row->coach_id)->exists();
                if($valid){$add('workout_executions',$row,null,WorkoutOwnership::ASSIGNED,'assignment_relationship_and_snapshot');continue;}
            }
            if(($content['source']??null)==='athlete_generated' && !$row->training_assignment_id && !$row->training_session_id) {
                $ops=DB::table('workout_sync_operations')->where('workout_execution_id',$row->id)->get();
                $ids=$ops->pluck('user_app_id')->unique()->values();
                $user=$ids->count()===1 ? $users->get($ids[0]) : null;
                if($user && (int)$user->client_id===(int)$row->client_id && $ops->every(fn($op)=>(int)$op->client_id===(int)$row->client_id)
                    && (!$user->created_at || $user->created_at<=$row->started_at)) {
                    $personal[$row->id]=(int)$user->id;
                    $add('workout_executions',$row,(int)$user->id,WorkoutOwnership::PERSONAL,'snapshot_and_unanimous_operation_author');continue;
                }
            }
            $exceptions[]=['table'=>'workout_executions','id'=>$row->id,'reason'=>'unproven_or_ambiguous_owner'];
        }
        $maxRows=DB::table('workout_max_records')->get()->keyBy('id');$maxOwners=[];
        foreach($maxRows as $row) {
            if($row->data_scope===WorkoutOwnership::PERSONAL){$maxOwners[$row->id]=(int)$row->owner_user_app_id;continue;}
            $user=$users->get($row->user_app_id);
            if(!$user || $row->recorded_by_coach_id || (int)$user->client_id!==(int)$row->client_id || ($user->created_at && $user->created_at>$row->created_at))continue;
            if($row->source_set_id) {
                $set=DB::table('workout_execution_sets')->find($row->source_set_id);
                if(!$set || ($personal[$set->workout_execution_id]??null)!==(int)$user->id)continue;
            }elseif($row->source!=='athlete_reported')continue;
            $maxOwners[$row->id]=(int)$user->id;
        }
        // A correction chain with mixed authors stays legacy as a whole.
        do {
            $changed=false;
            foreach($maxRows as $row) if($row->supersedes_id && (($maxOwners[$row->id]??null)!==($maxOwners[$row->supersedes_id]??null))) {
                foreach([$row->id,$row->supersedes_id] as $id) if(isset($maxOwners[$id]) && $maxRows[$id]->data_scope!==WorkoutOwnership::PERSONAL){unset($maxOwners[$id]);$changed=true;}
            }
        }while($changed);
        foreach($maxRows as $row) if($row->data_scope===WorkoutOwnership::LEGACY) {
            if(isset($maxOwners[$row->id]))$add('workout_max_records',$row,$maxOwners[$row->id],WorkoutOwnership::PERSONAL,'athlete_author_and_consistent_evidence_chain');
            else $exceptions[]=['table'=>'workout_max_records','id'=>$row->id,'reason'=>'legacy_or_mixed_authorship'];
        }
        foreach(DB::table('workout_exercise_goals')->where('data_scope',WorkoutOwnership::LEGACY)->get() as $row) {
            $user=$users->get($row->user_app_id);
            if($user && !$row->recorded_by_coach_id && (int)$user->client_id===(int)$row->client_id && (!$user->created_at || $user->created_at<=$row->created_at))
                $add('workout_exercise_goals',$row,(int)$user->id,WorkoutOwnership::PERSONAL,'verified_athlete_author');
            else $exceptions[]=['table'=>'workout_exercise_goals','id'=>$row->id,'reason'=>'legacy_or_unknown_author'];
        }
        $references=[];
        foreach($snapshots as $executionId=>$versions) foreach($versions as $content) foreach($content['sections']??[] as $section) foreach($section['exercises']??[] as $item) foreach($item['sets']??[] as $set) {
            $recommended=$set['recommended']??[];$uuid=$recommended['suggestion_id']??null;
            if($uuid)$references[$uuid][]=[$executionId,$recommended];
        }
        foreach(DB::table('workout_load_suggestions')->where('data_scope',WorkoutOwnership::LEGACY)->get() as $row) {
            $refs=$references[$row->uuid]??[];$owners=[];$valid=!(bool)$row->preview && count($refs)>0;$result=$this->content($row->result);
            foreach($refs as [$executionId,$recommended]) {
                $owner=$personal[$executionId]??null;$owners[]=$owner;
                if(!$owner || $recommended!=$result)$valid=false;
            }
            $owners=array_unique($owners);$owner=count($owners)===1 ? $owners[0] : null;
            $user=$users->get($owner);
            if(!$user || (int)$user->client_id!==(int)$row->client_id)$valid=false;
            $basis=$result['basis']??null;
            if($basis && isset($basis['record_id']) && ($maxOwners[$basis['record_id']]??null)!==$owner)$valid=false;
            if($basis && isset($basis['source_set_id'])) {
                $set=DB::table('workout_execution_sets')->find($basis['source_set_id']);
                if(!$set || ($personal[$set->workout_execution_id]??null)!==$owner)$valid=false;
            }
            if($valid && $owner)$add('workout_load_suggestions',$row,$owner,WorkoutOwnership::PERSONAL,'identical_snapshot_recommendation_and_verified_owner');
            else $exceptions[]=['table'=>'workout_load_suggestions','id'=>$row->id,'reason'=>'unreferenced_preview_or_unproven_basis'];
        }
        $counts=[];foreach($plans as $plan)$counts[$plan['table'].':'.$plan['data_scope']]=($counts[$plan['table'].':'.$plan['data_scope']]??0)+1;
        return ['planned'=>$plans,'counts'=>$counts,'exceptions'=>$exceptions];
    }
    public function apply(): array
    {
        return DB::transaction(function(){
            DB::table('user_apps')->orderBy('id')->lockForUpdate()->get(['id']);
            foreach(self::TABLES as $table)DB::table($table)->orderBy('id')->lockForUpdate()->get(['id']);
            $report=$this->report();
            foreach($report['planned'] as $plan) {
                $changed=DB::table($plan['table'])->where('id',$plan['id'])->where('data_scope',WorkoutOwnership::LEGACY)->whereNull('owner_user_app_id')
                    ->update(['data_scope'=>$plan['data_scope'],'owner_user_app_id'=>$plan['owner_user_app_id']]);
                if($changed!==1)throw new \RuntimeException('La propiedad cambió durante la transición; no se aplican asignaciones parciales.');
                if($plan['owner_user_app_id'])DB::table('workout_ownership_claims')->insert([
                    'user_app_id'=>$plan['owner_user_app_id'],'resource_table'=>$plan['table'],'resource_id'=>$plan['id'],
                    'legacy_client_id'=>$plan['legacy_client_id'],'legacy_coach_id'=>$plan['legacy_coach_id'],'evidence'=>$plan['evidence'],'created_at'=>now(),
                ]);
            }
            return $report;
        },3);
    }
}
