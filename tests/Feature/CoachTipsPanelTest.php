<?php
namespace Tests\Feature;

use App\Enums\TipStatus;
use App\Models\Tip;
use App\Models\User;
use App\Services\Tips\TipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoachTipsPanelTest extends TestCase
{
    private User $coach;
    private User $other;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tips_panel_memory' => ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true], 'cache.default'=>'array']);
        DB::setDefaultConnection('tips_panel_memory');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['2014_10_12_000000_create_users_table.php','2026_01_04_234242_create_permission_tables.php','2026_09_11_000001_create_tips_table.php','2026_09_15_000001_add_expires_at_to_tips_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('coach_subscriptions', function ($table) {
            $table->id(); $table->unsignedBigInteger('coach_id'); $table->date('ends_at'); $table->string('billing_status'); $table->softDeletes();
        });
        Storage::fake('local');
        $this->withoutVite();
        $this->coach = $this->actor();
        $this->other = $this->actor();
        $this->actingAs($this->coach);
        $this->withSession(['_token' => 'tips-panel-csrf'])->withHeader('X-CSRF-TOKEN', 'tips-panel-csrf');
    }
    protected function tearDown(): void
    {
        DB::purge('tips_panel_memory');
        parent::tearDown();
    }
    private function actor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('coach', 'web'));
        DB::table('coach_subscriptions')->insert(['coach_id'=>$user->id,'ends_at'=>'2030-01-01','billing_status'=>'paid']);
        return $user;
    }
    private function content(array $extra = []): array
    {
        return array_merge(['title'=>'Consejo del coach','body'=>'Texto <script>alert(1)</script>','type'=>'tip','category'=>'general'], $extra);
    }
    private function tip(?User $author = null, array $extra = []): Tip
    {
        return app(TipService::class)->save($author ?? $this->coach, $this->content($extra));
    }
    public function test_complete_coach_workflow_and_stale_conflicts(): void
    {
        $this->get('/coach/tips/create')->assertOk()->assertSee('Guardar borrador');
        $this->post('/coach/tips', $this->content(['intent'=>'submit', 'expires_at' => now()->addDays(3)->format('Y-m-d H:i')]))->assertRedirect();
        $tip = Tip::firstOrFail();
        $url = '/coach/tips/'.$tip->id;
        $this->assertSame(TipStatus::PENDING_APPROVAL, $tip->status);
        $this->get($url)->assertOk()->assertSee('está en revisión')->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($tip->expires_at);
        $this->get($url.'/edit')->assertRedirect($url);
        $this->postJson($url.'/submit')->assertStatus(409);
        $this->from($url)->post($url.'/submit')->assertRedirect($url)->assertSessionHas('error');
        $this->post($url.'/withdraw')->assertRedirect($url);
        $this->put($url, $this->content(['title'=>'Consejo corregido']))->assertRedirect($url);
        $this->post($url.'/archive')->assertRedirect($url);
        $this->post($url.'/restore')->assertRedirect($url);
        $this->assertSame(TipStatus::DRAFT, $tip->fresh()->status);
    }

    public function test_past_expiration_date_is_rejected_by_form(): void
    {
        $this->post('/coach/tips', $this->content([
            'expires_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ]))->assertSessionHasErrors('expires_at');

        $this->assertSame(0, Tip::count());
    }

    public function test_other_coach_cannot_access_or_mutate_tip_or_image(): void
    {
        $tip = $this->tip($this->other, ['title'=>'Privado de otro coach']);
        $url = '/coach/tips/'.$tip->id;
        $this->get('/coach/tips')->assertOk()->assertDontSee($tip->title);
        foreach ([$url, $url.'/edit', $url.'/image'] as $path) $this->get($path)->assertNotFound();
        $this->put($url, [])->assertNotFound();
        foreach (['submit','withdraw','archive','restore'] as $action) $this->post($url.'/'.$action)->assertNotFound();
    }
    public function test_filter_search_literal_wildcards_and_pagination(): void
    {
        $this->tip(null, ['title'=>'Recuperación al 100%','category'=>'recovery']);
        $this->tip(null, ['title'=>'Otro contenido']);
        $this->get('/coach/tips?q=%25&category=recovery&status=draft')->assertOk()->assertSee('Recuperación al 100%')->assertDontSee('Otro contenido');
        for ($i=0; $i<15; $i++) $this->tip(null, ['title'=>'Página '.$i]);
        $this->get('/coach/tips')->assertOk()->assertViewHas('tips', fn ($tips) => $tips->total() === 17 && $tips->count() === 15);
        $this->get('/coach/tips?page=2')->assertOk()->assertViewHas('tips', fn ($tips) => $tips->count() === 2);
        $this->getJson('/coach/tips?status=bad')->assertUnprocessable();
    }
    public function test_rejection_and_published_actions(): void
    {
        $tip = $this->tip();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $service = app(TipService::class);
        $service->transition($this->coach, $tip->id, 'submit');
        $service->transition($admin, $tip->id, 'reject', 'Añade instrucciones claras');
        $url = '/coach/tips/'.$tip->id;
        $this->get($url.'/edit')->assertOk()->assertSee('Añade instrucciones claras')->assertSee('se limpiarán');
        $this->put($url, $this->content(['intent'=>'submit']))->assertRedirect();
        $this->assertNull($tip->fresh()->rejection_reason);
        $service->transition($admin, $tip->id, 'approve');
        $this->get($url)->assertOk()->assertDontSee('>Editar<', false)->assertSee('Archivar');
        $this->putJson($url, $this->content())->assertStatus(409);
    }
    public function test_upload_validation_private_image_and_injected_fields(): void
    {
        $this->postJson('/coach/tips', $this->content(['intent'=>'publish']))->assertUnprocessable();
        $this->post('/coach/tips', $this->content(['image'=>UploadedFile::fake()->image('tip.png'),'status'=>'published','scope'=>'global','author_id'=>$this->other->id]))->assertRedirect();
        $tip = Tip::firstOrFail();
        $this->assertSame($this->coach->id, $tip->author_id);
        $this->assertSame(TipStatus::DRAFT, $tip->status);
        $this->get('/coach/tips/'.$tip->id.'/image')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->putJson('/coach/tips/'.$tip->id, $this->content(['remove_image'=>true,'image'=>UploadedFile::fake()->image('new.png')]))->assertUnprocessable();
        $this->assertSame($tip->image_path, $tip->fresh()->image_path);
    }
    public function test_subscription_gate_and_account_deletion_message(): void
    {
        $this->tip();
        $this->delete('/profile', ['password'=>'password'])->assertSessionHasErrors('password', null, 'userDeletion');
        $this->assertAuthenticatedAs($this->coach);
        $this->assertNotNull($this->coach->fresh());
        DB::table('coach_subscriptions')->where('coach_id', $this->coach->id)->update(['billing_status'=>'blocked']);
        $this->get('/coach/tips')->assertRedirect(route('coach.blocked'));
    }
}
