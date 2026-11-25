<?php
namespace Tests\Feature;

use App\Http\Middleware\EnsureCoachApiAccess;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\TrainingSession;
use App\Models\TrainingSection;
use App\Models\TrainingSectionExerciseBlock;
use App\Models\WorkoutExercise;
use App\Models\WorkoutTaxonomyTerm;
use App\Services\WorkoutPrescriptionSnapshot;
use Database\Seeders\WorkoutExerciseCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutExerciseCatalogTest extends TestCase
{
    use \Tests\Concerns\InstallsPersonalWorkoutSchema;
    private User $coach;
    private User $otherCoach;
    private UserApp $athlete;
    private const MIGRATION = '2026_11_14_000001_create_workout_exercise_catalog.php';
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureCoachApiAccess::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);
        config(['database.connections.catalog_memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'cache.default' => 'array']);
        DB::setDefaultConnection('catalog_memory'); $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['2014_10_12_000000_create_users_table.php', '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_230635_create_users_app.php', '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_01_06_042508_create_training_sessions_table.php', '2026_01_06_042534_create_training_sections_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php', '2026_07_28_120200_create_training_section_lifting_rows_table.php', self::MIGRATION] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->installPersonalWorkoutSchema();
        (new WorkoutExerciseCatalogSeeder)->run();
        $this->coach = User::factory()->create(); $this->otherCoach = User::factory()->create();
        $client = Client::create(['coach_id' => $this->coach->id, 'first_name' => 'Atleta']);
        $this->athlete = UserApp::create(['client_id' => $client->id, 'email' => 'catalog@example.test', 'is_active' => true]);
        Sanctum::actingAs($this->coach);
    }
    protected function tearDown(): void { DB::purge('catalog_memory'); parent::tearDown(); }
    private function body(string $code = 'my_squat'): array
    {
        return ['code' => $code, 'names' => ['es' => 'Mi sentadilla', 'en' => 'My Squat'], 'load_mode' => 'total_barbell',
            'fatigue' => 'moderate', 'unilateral' => false, 'is_active' => true, 'review_status' => 'reviewed',
            'regions' => ['lower_body'], 'goals' => ['hypertrophy'], 'levels' => ['intermediate'], 'patterns' => ['knee_dominant'],
            'equipment' => ['barbell', 'rack'], 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'demands' => ['knee_motion']];
    }
    private function create(string $code = 'my_squat'): array
    { return $this->postJson('/api/v1/coach/exercise-catalog', $this->body($code))->assertCreated()->json('data'); }
    public function test_seed_is_idempotent_and_preserves_reviewed_edits_and_ids(): void
    {
        $original = WorkoutExercise::orderBy('id')->pluck('id', 'code')->all();
        $exercise = WorkoutExercise::first(); $exercise->update(['is_active' => false, 'review_status' => 'reviewed', 'notes' => 'Revisión humana']);
        $pivotCount = DB::table('workout_exercise_terms')->count();
        (new WorkoutExerciseCatalogSeeder)->run();
        $this->assertSame(50, WorkoutExercise::count()); $this->assertSame($original, WorkoutExercise::orderBy('id')->pluck('id', 'code')->all());
        $this->assertFalse($exercise->fresh()->is_active); $this->assertSame('Revisión humana', $exercise->fresh()->notes);
        $this->assertSame($pivotCount, DB::table('workout_exercise_terms')->count());
    }
    public function test_all_seed_entries_have_classification_translations_and_valid_variants(): void
    {
        foreach (WorkoutExercise::with('terms', 'variantOf')->get() as $exercise) {
            $this->assertNotEmpty($exercise->names['es']); $this->assertNotEmpty($exercise->names['en']);
            foreach (['region', 'pattern', 'equipment', 'goal', 'level', 'demand'] as $kind) $this->assertTrue($exercise->terms->contains('kind', $kind));
            $this->assertTrue($exercise->terms->contains(fn ($term) => $term->kind === 'muscle' && $term->pivot->role === 'primary'));
            if ($exercise->variant_of_id) $this->assertNotNull($exercise->variantOf);
            $this->assertSame('pending', $exercise->review_status);
        }
    }
    public function test_athlete_sees_only_reviewed_globals_without_private_coach_catalogs(): void
    {
        $own = $this->create(); Sanctum::actingAs($this->otherCoach); $other = $this->create();
        WorkoutExercise::where('code','lower_body_back_squat')->update(['review_status'=>'reviewed']);
        Sanctum::actingAs($this->athlete);
        $response = $this->getJson('/api/v1/app/exercise-catalog?per_page=100')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertNotContains($own['id'], array_column($response->json('data'), 'id'));
        $this->assertNotContains($other['id'], array_column($response->json('data'), 'id'));
        $this->getJson('/api/v1/app/exercise-catalog/'.$other['id'])->assertNotFound();
        $this->getJson('/api/v1/app/exercise-catalog?include_inactive=1')->assertUnprocessable();
    }
    public function test_equipment_filter_requires_all_items_not_just_one(): void
    {
        WorkoutExercise::whereNull('coach_id')->update(['review_status'=>'reviewed']);
        Sanctum::actingAs($this->athlete);
        $result = $this->getJson('/api/v1/app/exercise-catalog?per_page=100&equipment_filter=1&equipment[]=barbell')->assertOk();
        $codes = array_column($result->json('data'), 'code');
        $this->assertContains('lower_body_romanian_deadlift', $codes); $this->assertNotContains('lower_body_back_squat', $codes);
        $this->assertContains('lower_body_glute_bridge', $codes);
        $bodyweight = $this->getJson('/api/v1/app/exercise-catalog?equipment_filter=1')->assertOk();
        foreach ($bodyweight->json('data') as $exercise) foreach ($exercise['terms'] as $term) if ($term['kind'] === 'equipment') $this->assertSame('bodyweight', $term['code']);
    }
    public function test_filters_and_metadata_use_stable_codes(): void
    {
        $result = $this->getJson('/api/v1/coach/exercise-catalog?patterns[]=knee_flexion&muscles[]=hamstrings&regions[]=lower_body')->assertOk();
        $this->assertGreaterThan(0, $result->json('meta.total'));
        $this->getJson('/api/v1/coach/exercise-catalog?equipment[]=imaginary')->assertUnprocessable();
        $this->getJson('/api/v1/coach/exercise-catalog?q=rumano')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/coach/exercise-catalog/meta')->assertOk()->assertJsonStructure(['data' => ['region', 'muscle', 'equipment', 'pattern', 'goal', 'level', 'demand']]);
    }
    public function test_only_owner_can_update_private_exercise_and_global_is_read_only(): void
    {
        $own = $this->create(); $body = $this->body(); unset($body['code']); $body['names']['es'] = 'Actualizado';
        $this->putJson('/api/v1/coach/exercise-catalog/'.$own['id'], $body)->assertOk()->assertJsonPath('data.names.es', 'Actualizado');
        Sanctum::actingAs($this->otherCoach);
        $this->putJson('/api/v1/coach/exercise-catalog/'.$own['id'], $body)->assertNotFound();
        $this->putJson('/api/v1/coach/exercise-catalog/'.WorkoutExercise::whereNull('coach_id')->first()->id, $body)->assertNotFound();
    }
    public function test_codes_identity_and_muscle_roles_are_validated(): void
    {
        $own = $this->create();
        $this->putJson('/api/v1/coach/exercise-catalog/'.$own['id'], $this->body())->assertUnprocessable();
        $body = $this->body('bad_overlap'); $body['secondary_muscles'] = ['quadriceps'];
        $this->postJson('/api/v1/coach/exercise-catalog', $body)->assertUnprocessable();
        $this->postJson('/api/v1/coach/exercise-catalog', $this->body())->assertUnprocessable();
        Sanctum::actingAs($this->otherCoach); $other = $this->create('other_variant'); Sanctum::actingAs($this->coach);
        $body = $this->body('foreign_variant'); $body['variant_of_id'] = $other['id'];
        $this->postJson('/api/v1/coach/exercise-catalog', $body)->assertUnprocessable();
    }
    public function test_inactive_and_wrong_actor_access_is_blocked(): void
    {
        $own = $this->create(); $body = $this->body(); unset($body['code']); $body['is_active'] = false;
        $this->putJson('/api/v1/coach/exercise-catalog/'.$own['id'], $body)->assertOk();
        $this->getJson('/api/v1/coach/exercise-catalog/'.$own['id'])->assertNotFound();
        $this->getJson('/api/v1/app/exercise-catalog')->assertForbidden();
        Sanctum::actingAs($this->athlete); $this->athlete->update(['is_active' => false]);
        $this->getJson('/api/v1/app/exercise-catalog')->assertUnauthorized();
    }
    private function block(): TrainingSectionExerciseBlock
    {
        $training = TrainingSession::create(['coach_id' => $this->coach->id, 'title' => 'Legado', 'scheduled_at' => '2026-11-14',
            'level' => 'intermediate', 'goal' => 'strength', 'type' => 'weightlifting', 'visibility' => 'assigned']);
        $section = TrainingSection::create(['training_session_id' => $training->id, 'name' => 'Fuerza', 'order' => 1]);
        return TrainingSectionExerciseBlock::create(['training_section_id' => $section->id, 'exercise_name' => 'Mi ejercicio legado', 'order' => 1, 'exercise_catalog_id' => WorkoutExercise::first()->id]);
    }
    public function test_canonical_link_is_explicit_and_snapshot_preserves_its_classification(): void
    {
        $block = $this->block(); $service = new WorkoutPrescriptionSnapshot;
        $training = TrainingSession::find($block->section->training_session_id);
        $legacy = $service->capture($training)['sections'][0]['exercises'][0];
        $this->assertNull($legacy['catalog']); $this->assertNull($legacy['workout_exercise_id']);
        $exercise = WorkoutExercise::first(); $block->update(['workout_exercise_id' => $exercise->id]);
        $snapshot = $service->capture($training)['sections'][0]['exercises'][0];
        $this->assertSame($exercise->code, $snapshot['catalog']['code']); $this->assertNotEmpty($snapshot['catalog']['terms']);
        $exercise->update(['names' => ['es' => 'Nombre cambiado']]);
        $this->assertNotEquals($exercise->fresh()->names, $snapshot['catalog']['names']);
    }
    public function test_older_coach_editor_omitting_new_field_preserves_the_canonical_link(): void
    {
        $block = $this->block(); $exercise = WorkoutExercise::first();
        $block->update(['workout_exercise_id' => $exercise->id]);
        foreach ([\App\Http\Controllers\Api\V1\Coach\TrainingController::class, \App\Http\Controllers\Coach\TrainingSessionController::class] as $class) {
            $method = new \ReflectionMethod($class, 'syncLiftingBlocks');
            $method->invoke(new $class, $block->section, [['id' => $block->id, 'exercise_name' => 'Edición de notas', 'notes' => 'Conservar vínculo', 'rows' => []]]);
            $this->assertSame($exercise->id, $block->fresh()->workout_exercise_id);
        }
    }

    public function test_new_migration_rollback_in_memory_preserves_legacy_blocks(): void
    {
        $block = $this->block(); (require database_path('migrations/'.self::MIGRATION))->down();
        $this->assertSame('Mi ejercicio legado', $block->fresh()->exercise_name);
        $this->assertNotNull($block->fresh()->exercise_catalog_id);
    }

    public function test_upper_body_recommendations_filter_initial_muscle_and_reject_other_region(): void
    {
        (new \Database\Seeders\WorkoutUpperBodyCatalogSeeder)->run();
        config(['workouts.generator_enabled'=>true]);
        WorkoutExercise::whereNull('coach_id')->update(['review_status'=>'reviewed']);
        $service=app(\App\Services\WorkoutRoutineRecommendations::class);
        $base=['region'=>'upper_body','selected_ids'=>[],'equipment'=>[],'discover_equipment'=>true,'exercise_count'=>4];
        $result=$service->recommend(new \Illuminate\Http\Request($base+['initial_muscle'=>'pectorals']),$this->athlete);
        $this->assertNotEmpty($result['candidates']);
        foreach($result['candidates'] as $row) {
            $this->assertStringStartsWith('upper_body_',$row['code']);
            $this->assertContains('pectorals',$row['primary_muscles']);
        }
        $pushup=WorkoutExercise::where('code','upper_body_push_up')->firstOrFail();
        $next=$service->recommend(new \Illuminate\Http\Request(array_replace($base,['selected_ids'=>[$pushup->id],'initial_muscle'=>'pectorals'])),$this->athlete);
        $this->assertNotEmpty(array_filter($next['candidates'],fn($row)=>!in_array('pectorals',$row['primary_muscles'],true)));
        $lower=WorkoutExercise::where('code','lower_body_back_squat')->firstOrFail();
        try {
            $service->recommend(new \Illuminate\Http\Request(array_replace($base,['selected_ids'=>[$lower->id]])),$this->athlete);
            $this->fail('A lower body exercise must be rejected for an upper body session.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422,$e->getStatusCode());
        }
    }
}
