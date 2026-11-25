<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Services\PlatformWorkoutCatalog;
use Database\Seeders\WorkoutExerciseCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class PlatformWorkoutCatalogTest extends TestCase {
    private User $admin;
    protected function setUp():void {
        parent::setUp();$this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);config(['database.connections.platform_memory'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'cache.default'=>'array']);
        DB::setDefaultConnection('platform_memory');$this->assertSame(':memory:',DB::connection()->getDatabaseName());
        foreach(['2014_10_12_000000_create_users_table.php','2026_01_04_234242_create_permission_tables.php','2026_01_06_042508_create_training_sessions_table.php','2026_01_06_042534_create_training_sections_table.php','2026_07_28_120100_create_training_section_exercise_blocks_table.php','2026_11_14_000001_create_workout_exercise_catalog.php','2026_11_16_000003_create_workout_catalog_reviews.php'] as $f)(require database_path('migrations/'.$f))->up();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();Role::create(['name'=>'admin','guard_name'=>'web']);
        $this->admin=User::factory()->create();$this->admin->assignRole('admin');(new WorkoutExerciseCatalogSeeder)->run();$this->withoutVite();
    }
    protected function tearDown():void {DB::purge('platform_memory');parent::tearDown();}
    private function body(WorkoutExercise $e):array {
        $e->load('terms');$body=['fingerprint'=>app(PlatformWorkoutCatalog::class)->fingerprint($e),'action'=>'publish','review_confirmed'=>true,'reason'=>'Datos revisados por plataforma','names'=>$e->names,'load_mode'=>$e->load_mode,'fatigue'=>$e->fatigue,'unilateral'=>$e->unilateral,'is_active'=>true];
        foreach(PlatformWorkoutCatalog::FIELDS as $field=>$kind)$body[$field]=$e->terms->where('kind',$kind)->filter(fn($t)=>$field==='primary_muscles'?$t->pivot->role==='primary':($field==='secondary_muscles'?$t->pivot->role==='secondary':true))->pluck('code')->all();return $body;
    }
    public function test_admin_can_review_publish_withdraw_and_audit_without_changing_identity():void {
        $e=WorkoutExercise::first();$this->actingAs($this->admin)->get('/admin/workout-catalog/'.$e->id.'/edit')->assertOk()->assertSee('Guardar y publicar');
        $body=$this->body($e);$url='/admin/workout-catalog/'.$e->id;
        $this->patch($url,$body)->assertRedirect();$this->assertSame('reviewed',$e->fresh()->review_status);
        $this->patchJson($url,$body)->assertConflict();$body=$this->body($e->fresh());$body['action']='withdraw';unset($body['review_confirmed']);
        $this->patch($url,$body)->assertRedirect();$this->assertSame('pending',$e->fresh()->review_status);
        $this->assertSame(2,DB::table('workout_catalog_reviews')->count());$this->assertSame($e->code,$e->fresh()->code);
    }
    public function test_coach_cannot_publish_and_admin_cannot_promote_private_catalog():void {
        $e=WorkoutExercise::first();$coach=User::factory()->create();$this->actingAs($coach)->patchJson('/admin/workout-catalog/'.$e->id,$this->body($e))->assertForbidden();
        $private=$e->replicate();$private->code='private_test';$private->coach_id=$coach->id;$private->save();
        $this->actingAs($this->admin)->get('/admin/workout-catalog/'.$private->id.'/edit')->assertNotFound();$this->assertSame(0,DB::table('workout_catalog_reviews')->count());
    }
    public function test_explicit_review_and_complete_equipment_are_required():void {
        $e=WorkoutExercise::first();$body=$this->body($e);unset($body['review_confirmed']);
        $this->actingAs($this->admin)->patchJson('/admin/workout-catalog/'.$e->id,$body)->assertUnprocessable();
        $body=$this->body($e);$body['equipment']=[];$this->patchJson('/admin/workout-catalog/'.$e->id,$body)->assertUnprocessable();
        $this->assertSame('pending',$e->fresh()->review_status);
    }
}
