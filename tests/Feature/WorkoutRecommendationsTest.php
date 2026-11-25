<?php
namespace Tests\Feature;

use App\Http\Middleware\EnsureClientMembershipIsActive;
use App\Http\Middleware\EnsureCoachApiAccess;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutAthleteSetting;
use App\Models\AthleteGymPreference;
use App\Models\WorkoutExercise;
use Database\Seeders\WorkoutExerciseCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutRecommendationsTest extends TestCase
{
    use \Tests\Concerns\InstallsPersonalWorkoutSchema;
    private User $coach; private UserApp $athlete; private Client $client;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureCoachApiAccess::class, EnsureClientMembershipIsActive::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);
        config(['database.connections.recommend_memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array', 'workouts.generator_enabled' => true]);
        DB::setDefaultConnection('recommend_memory'); $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['2014_10_12_000000_create_users_table.php', '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_230635_create_users_app.php', '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_01_06_042508_create_training_sessions_table.php', '2026_01_06_042534_create_training_sections_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php', '2026_11_14_000001_create_workout_exercise_catalog.php',
            '2026_11_14_000002_create_workout_athlete_settings.php'] as $file) (require database_path('migrations/'.$file))->up();
        $this->installPersonalWorkoutSchema();
        (new WorkoutExerciseCatalogSeeder)->run(); $this->coach = User::factory()->create();
        $this->client = Client::create(['coach_id' => $this->coach->id, 'first_name' => 'Atleta']);
        $this->athlete = UserApp::create(['client_id' => $this->client->id, 'email' => 'recommend@example.test', 'is_active' => true]);
        WorkoutExercise::query()->update(['review_status'=>'reviewed']);
        Sanctum::actingAs($this->athlete);
    }
    protected function tearDown(): void { DB::purge('recommend_memory'); parent::tearDown(); }
    private function id(string $code): int { return WorkoutExercise::where('code', 'lower_body_'.$code)->firstOrFail()->id; }
    private function body(array $selected = [], array $equipment = ['barbell','rack','leg_press_machine']): array {
        return ['selected_ids' => $selected, 'equipment' => $equipment, 'exercise_count' => 4];
    }
    private function recommendationUrl(): string { return '/api/v1/app/workout-generator/recommendations'; }
    private function enable(array $blocked = [], array $demands = []): void {
        AthleteGymPreference::updateOrCreate(['user_app_id'=>$this->athlete->id],['blocked_exercise_codes'=>$blocked,'blocked_demands'=>$demands]);
    }
    public function test_discovery_precedes_equipment_but_selected_exercises_still_require_all_items():void {
        $body=$this->body([],[]);$body['discover_equipment']=true;
        $r=$this->postJson($this->recommendationUrl(),$body)->assertOk();
        $this->assertContains($this->id('back_squat'),array_column($r->json('data.candidates'),'id'));
        $body['selected_ids']=[$this->id('back_squat')];$body['equipment']=['barbell'];
        $this->postJson($this->recommendationUrl(),$body)->assertUnprocessable();
        $body['equipment']=['barbell','rack'];$this->postJson($this->recommendationUrl(),$body)->assertOk();
    }
    public function test_platform_flags_control_gym_and_coach_permissions_are_retired():void {
        config(['workouts.generator_enabled'=>false,'workouts.executions_enabled'=>false]);
        Sanctum::actingAs($this->coach);
        $url='/api/v1/coach/clients/'.$this->client->id.'/workout-generator/settings';
        $body=['generator_enabled'=>true,'blocked_exercise_codes'=>[],'blocked_demands'=>[]];
        $this->putJson($url,$body)->assertGone();
        $this->getJson($url)->assertGone();
        Sanctum::actingAs($this->athlete);
        $this->getJson('/api/v1/app/workout-generator/settings')->assertOk()->assertJsonPath('data.generator_enabled',false)
            ->assertJsonPath('data.feature_enabled',false)->assertJsonPath('data.executions_enabled',false);
        config(['workouts.generator_enabled'=>true,'workouts.executions_enabled'=>true]);
        $this->getJson('/api/v1/app/workout-generator/settings')->assertOk()->assertJsonPath('data.generator_enabled',true)
            ->assertJsonPath('data.feature_enabled',true)->assertJsonPath('data.executions_enabled',true);
    }
    public function test_rumano_recommends_complementary_knee_pattern_reproducibly(): void
    {
        $body = $this->body([$this->id('romanian_deadlift')]);
        $first = $this->postJson($this->recommendationUrl(), $body)->assertOk();
        $this->assertContains('knee_dominant', $first->json('data.candidates.0.patterns'));
        $this->assertNotContains($this->id('romanian_deadlift'), array_column($first->json('data.candidates'), 'id'));
        $this->assertNotEmpty($first->json('data.candidates.0.reasons'));
        $this->postJson($this->recommendationUrl(), $body)->assertOk()->assertExactJson($first->json());
        $this->assertFalse($first->json('data.preview'));
    }
    public function test_changing_selection_recomputes_complement_and_never_uses_ai(): void
    {
        $response = $this->postJson($this->recommendationUrl(), $this->body([$this->id('leg_press')]))->assertOk();
        $this->assertContains('hip_hinge', $response->json('data.candidates.0.patterns'));
        $this->assertSame(config('workout_rules.version'), $response->json('data.rule_version'));
        $this->assertSame(array_sum($response->json('data.candidates.0.score_breakdown')), $response->json('data.candidates.0.score'));
    }
    public function test_equipment_and_personal_exclusions_are_hard_filters(): void
    {
        $this->enable(['lower_body_bodyweight_squat'], ['hip_motion']);
        $body = $this->body([], []); $body['blocked_demands'] = []; $body['preferred_ids'] = [$this->id('glute_bridge')];
        $response = $this->postJson($this->recommendationUrl(), $body)->assertOk();
        $codes = array_column($response->json('data.candidates'), 'code');
        $this->assertNotContains('lower_body_bodyweight_squat', $codes); $this->assertNotContains('lower_body_glute_bridge', $codes);
        $this->assertNotContains('lower_body_back_squat', $codes);
        $this->postJson($this->recommendationUrl(), $this->body([$this->id('glute_bridge')], []))->assertUnprocessable();
    }
    public function test_shortage_returns_fewer_candidates_with_explanation(): void
    {
        $this->enable([], ['hip_motion','knee_motion']);
        $response = $this->postJson($this->recommendationUrl(), $this->body([], []))->assertOk();
        $this->assertLessThan(4, count($response->json('data.candidates')));
        $this->assertGreaterThan(0, $response->json('data.shortage')); $this->assertNotEmpty($response->json('data.message'));
    }
    public function test_athlete_requires_platform_feature_and_reviewed_public_candidates(): void
    {
        Sanctum::actingAs($this->athlete);
        $url = '/api/v1/app/workout-generator/recommendations';
        config(['workouts.generator_enabled'=>false]);$this->postJson($url,$this->body())->assertForbidden();
        config(['workouts.generator_enabled'=>true]);WorkoutExercise::query()->update(['review_status'=>'pending']);
        $this->postJson($url, $this->body())->assertOk()->assertJsonCount(0, 'data.candidates')->assertJsonPath('data.preview', false);
        WorkoutExercise::where('code', 'lower_body_leg_press')->first()->update(['review_status' => 'reviewed']);
        $this->postJson($url, $this->body())->assertOk()->assertJsonCount(1, 'data.candidates');
        config(['workouts.generator_enabled' => false]); $this->postJson($url, $this->body())->assertForbidden();
    }
    public function test_other_coach_cannot_change_settings_or_preview_and_athlete_cannot_override_them(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $base = '/api/v1/coach/clients/'.$this->client->id.'/workout-generator';
        $this->getJson($base.'/settings')->assertNotFound(); $this->postJson($base.'/preview', $this->body())->assertNotFound();
        $this->putJson($base.'/settings', ['generator_enabled' => true, 'blocked_exercise_codes' => [], 'blocked_demands' => []])->assertNotFound();
        Sanctum::actingAs($this->athlete); $this->putJson($base.'/settings', ['generator_enabled' => true])->assertForbidden();
    }
    public function test_settings_update_is_scoped_to_owner_and_unknown_codes_are_rejected(): void
    {
        $url = '/api/v1/app/workout-generator/settings';
        $body = ['generator_enabled' => true, 'blocked_exercise_codes' => ['lower_body_back_squat'], 'blocked_demands' => ['external_load']];
        $this->patchJson($url, $body)->assertOk()->assertJsonPath('data.generator_enabled', true);
        $this->patchJson($url, $body)->assertOk(); $this->assertSame(1, AthleteGymPreference::count());
        $body['blocked_demands'] = ['imaginary']; $this->patchJson($url, $body)->assertUnprocessable();
        Sanctum::actingAs($this->athlete); $this->getJson('/api/v1/app/workout-generator/settings')->assertOk()->assertJsonPath('data.blocked_exercise_codes.0', 'lower_body_back_squat');
    }
    public function test_profile_duplicate_and_ineligible_selection_are_rejected(): void
    {
        $body = $this->body([$this->id('romanian_deadlift'), $this->id('romanian_deadlift')]);
        $this->postJson($this->recommendationUrl(), $body)->assertUnprocessable();
        $body = $this->body([$this->id('back_squat')], ['barbell']); $this->postJson($this->recommendationUrl(), $body)->assertUnprocessable();
        $body = $this->body(); $body['goal'] = 'clinical'; $this->postJson($this->recommendationUrl(), $body)->assertUnprocessable();
    }
    public function test_old_coach_restrictions_are_not_reused_after_coach_change(): void
    {
        WorkoutAthleteSetting::create(['client_id'=>$this->client->id,'coach_id'=>$this->coach->id,'generator_enabled'=>false,'blocked_exercise_codes'=>['lower_body_romanian_deadlift'],'blocked_demands'=>[]]);
        $this->client->update(['coach_id' => User::factory()->create()->id]); $this->athlete->unsetRelation('client');
        Sanctum::actingAs($this->athlete);
        $this->getJson('/api/v1/app/workout-generator/settings')->assertOk()->assertJsonPath('data.generator_enabled', true)->assertJsonCount(0, 'data.blocked_exercise_codes');
    }
}
