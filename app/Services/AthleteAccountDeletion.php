<?php
namespace App\Services;

use App\Models\UserApp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AthleteAccountDeletion
{
    public function completeUnlinked(string $id): bool
    {
        $completed = DB::transaction(function () use ($id) {
            $request = DB::table('athlete_account_deletions')->where('id',$id)->lockForUpdate()->first();
            if (!$request) throw new \RuntimeException('Solicitud no encontrada.');
            if ($request->status === 'completed') return true;
            $user = UserApp::withTrashed()->whereKey($request->user_app_id)->lockForUpdate()->first();
            // Commercial/legacy records require platform review, never a coach's authorization.
            if (!$user || $user->is_active || $user->client_id !== null || !$user->self_registered_at) return false;
            if (Schema::hasTable('workout_ownership_claims') && !$this->erasePersonalGym($user)) return false;
            foreach (['workout_sync_operations','workout_max_records','workout_exercise_goals'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('user_app_id',$user->id)->exists()) return false;
            }
            $email = $user->email;
            DB::table('athlete_account_deletions')->where('id',$id)->update([
                'notification_email'=>Crypt::encryptString($email),'status'=>'completed','completed_at'=>now(),
            ]);
            $user->tokens()->delete();
            $user->athleteProfile()->delete();
            if (Schema::hasTable('user_devices')) $user->devices()->delete();
            if (Schema::hasTable('push_notifications')) DB::table('push_notifications')->where('user_id',$user->id)->delete();
            if (Schema::hasTable('notifications')) DB::table('notifications')->where('notifiable_type',$user->getMorphClass())->where('notifiable_id',$user->id)->delete();
            DB::table('athlete_email_actions')->where('email',mb_strtolower($email))->delete();
            DB::table('athlete_setup_tokens')->where('email',$email)->delete();
            $user->forceDelete();
            return true;
        });
        if ($completed) $this->notify($id);
        return $completed;
    }

    private function erasePersonalGym(UserApp $user): bool
    {
        $personal=WorkoutOwnership::PERSONAL;
        $executions=DB::table('workout_executions')->where('data_scope',$personal)->where('owner_user_app_id',$user->id)->pluck('id');
        $sets=DB::table('workout_execution_sets')->whereIn('workout_execution_id',$executions)->pluck('id');
        $maxes=DB::table('workout_max_records')->where('data_scope',$personal)->where('owner_user_app_id',$user->id)->pluck('id');
        // Validate all dependencies before erasing anything. Mixed legacy evidence needs review.
        if (DB::table('workout_sync_operations')->where('user_app_id',$user->id)->whereNotIn('workout_execution_id',$executions)->exists()
            || DB::table('workout_sync_operations')->whereIn('workout_execution_id',$executions)->where('user_app_id','!=',$user->id)->exists()
            || DB::table('workout_max_records')->whereIn('source_set_id',$sets)->whereNotIn('id',$maxes)->exists()
            || DB::table('workout_max_records')->whereIn('supersedes_id',$maxes)->whereNotIn('id',$maxes)->exists()) return false;
        foreach(['workout_max_records','workout_exercise_goals'] as $table) {
            if (DB::table($table)->where('user_app_id',$user->id)->where(function($q)use($user,$personal){
                $q->where('data_scope','!=',$personal)->orWhereNull('owner_user_app_id')->orWhere('owner_user_app_id','!=',$user->id);
            })->exists())return false;
        }
        DB::table('workout_max_records')->whereIn('id',$maxes)->update(['supersedes_id'=>null]);
        DB::table('workout_max_records')->whereIn('id',$maxes)->delete();
        foreach(['workout_exercise_goals','workout_load_suggestions'] as $table)DB::table($table)->where('data_scope',$personal)->where('owner_user_app_id',$user->id)->delete();
        $operations=DB::table('workout_sync_operations')->whereIn('workout_execution_id',$executions)->pluck('id');
        DB::table('workout_set_revisions')->whereIn('workout_sync_operation_id',$operations)->delete();
        DB::table('workout_prescription_revisions')->whereIn('workout_execution_id',$executions)->delete();
        DB::table('workout_sync_operations')->whereIn('id',$operations)->delete();
        DB::table('workout_execution_sets')->whereIn('id',$sets)->delete();
        DB::table('workout_execution_prescriptions')->whereIn('workout_execution_id',$executions)->delete();
        DB::table('workout_executions')->whereIn('id',$executions)->delete();
        DB::table('athlete_gym_preferences')->where('user_app_id',$user->id)->delete();
        DB::table('workout_ownership_claims')->where('user_app_id',$user->id)->delete();
        return true;
    }

    private function notify(string $id): void
    {
        // If delivery fails, leave an encrypted recipient for retry; never mark it as sent.
        $request = DB::table('athlete_account_deletions')->where('id',$id)->first();
        if (!$request || $request->notification_sent_at || !$request->notification_email) return;
        if (in_array(config('mail.default'), ['log','array'], true)) return;
        $email = Crypt::decryptString($request->notification_email);
        Mail::raw('La eliminación de tu cuenta personal de atleta y sus datos asociados ha sido completada.', function ($message) use ($email) {
            $message->to($email)->subject('Cuenta de atleta eliminada');
        });
        DB::table('athlete_account_deletions')->where('id',$id)->update(['notification_sent_at'=>now(),'notification_email'=>null]);
    }
}
