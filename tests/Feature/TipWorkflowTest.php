<?php

namespace Tests\Feature;

use App\Enums\TipStatus;
use App\Models\Tip;
use App\Models\User;
use App\Services\Tips\TipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class TipWorkflowTest extends TestCase
{
    private TipService $service;
    private User $coach;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tips_workflow_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('tips_workflow_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_01_04_234242_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_09_11_000001_create_tips_table.php'))->up();
        (require database_path('migrations/2026_09_15_000001_add_expires_at_to_tips_table.php'))->up();
        Storage::fake('local');
        $this->coach = $this->actor('coach');
        $this->admin = $this->actor('admin');
        $this->service = app(TipService::class);
    }

    protected function tearDown(): void
    {
        DB::purge('tips_workflow_memory');
        parent::tearDown();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function content(): array
    {
        return ['title' => 'Consejo de prueba', 'body' => 'Contenido de prueba'];
    }

    public function test_coach_requires_review_and_admin_can_publish_own_content(): void
    {
        $tip = $this->service->save($this->coach, array_merge($this->content(), ['expires_at' => now()->addWeek()]), intent: 'submit');
        $this->assertSame(TipStatus::PENDING_APPROVAL, $tip->status);
        $this->assertNotNull($tip->expires_at);
        $approved = $this->service->transition($this->admin, $tip->id, 'approve');
        $this->assertSame(TipStatus::PUBLISHED, $approved->status);
        $this->assertSame($this->admin->id, $approved->reviewed_by);
        $this->assertSame($this->coach->id, $approved->coach_id);
        $global = $this->service->save($this->admin, $this->content(), intent: 'publish');
        $this->assertSame(TipStatus::PUBLISHED, $global->status);
        $this->assertNull($global->coach_id);
        $this->assertNull($global->reviewed_by);
    }

    public function test_invalid_publish_rolls_back_draft_and_uploaded_file(): void
    {
        try {
            $this->service->save($this->coach, $this->content(), image: UploadedFile::fake()->image('photo.png'), intent: 'publish');
            $this->fail('Coach cannot publish.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(0, Tip::count());
            $this->assertSame([], Storage::disk('local')->allFiles('tips'));
        }
    }

    public function test_reject_requires_reason_and_correction_requires_resubmission(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), intent: 'submit');
        try {
            $this->service->transition($this->admin, $tip->id, 'reject', ' ');
            $this->fail('Reason required.');
        } catch (ValidationException $exception) {
            $this->assertSame(TipStatus::PENDING_APPROVAL, $tip->fresh()->status);
        }
        $this->service->transition($this->admin, $tip->id, 'reject', 'Corregir contenido');
        $corrected = $this->service->save($this->coach, $this->content(), $tip->id);
        $this->assertSame(TipStatus::DRAFT, $corrected->status);
        $this->assertNull($corrected->rejection_reason);
        $this->assertNull($corrected->reviewed_by);
    }

    public function test_withdraw_prevents_stale_approval(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), intent: 'submit');
        $this->service->transition($this->coach, $tip->id, 'withdraw');
        $this->expectException(ConflictHttpException::class);
        $this->service->transition($this->admin, $tip->id, 'approve');
    }

    public function test_second_review_cannot_overwrite_first_decision(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), intent: 'submit');
        $this->service->transition($this->admin, $tip->id, 'approve');
        $this->expectException(ConflictHttpException::class);
        $this->service->transition($this->admin, $tip->id, 'reject', 'Otra decisión');
    }

    public function test_pending_and_published_cannot_be_edited(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), intent: 'submit');
        foreach ([TipStatus::PENDING_APPROVAL, TipStatus::PUBLISHED] as $state) {
            try {
                $this->service->save($this->coach, ['title' => 'Cambio'], $tip->id);
                $this->fail('State must prevent edits.');
            } catch (ConflictHttpException $exception) {
                $this->assertSame('Consejo de prueba', $tip->fresh()->title);
            }
            if ($state === TipStatus::PENDING_APPROVAL) {
                $this->service->transition($this->admin, $tip->id, 'approve');
            }
        }
    }

    public function test_other_coach_and_admin_cannot_edit_coach_content(): void
    {
        $tip = $this->service->save($this->coach, $this->content());
        foreach ([$this->actor('coach'), $this->admin] as $actor) {
            try {
                $this->service->save($actor, $this->content(), $tip->id);
                $this->fail('Author only.');
            } catch (AuthorizationException $exception) {
                $this->assertSame(404, $exception->status());
            }
        }
    }

    public function test_archiving_and_restoring_keeps_original_publication_date(): void
    {
        $tip = $this->service->save($this->admin, $this->content(), intent: 'publish');
        $first = $tip->published_at->toISOString();
        $this->service->transition($this->admin, $tip->id, 'archive');
        $draft = $this->service->transition($this->admin, $tip->id, 'restore');
        $this->assertSame(TipStatus::DRAFT, $draft->status);
        $this->assertNull($draft->archived_at);
        $this->travel(1)->hours();
        $published = $this->service->transition($this->admin, $tip->id, 'publish');
        $this->assertSame($first, $published->published_at->toISOString());
    }

    public function test_image_replace_remove_and_failure_preserve_consistency(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), image: UploadedFile::fake()->image('first.png'));
        $original = $tip->image_path;
        try {
            $this->service->save($this->coach, $this->content(), $tip->id, UploadedFile::fake()->image('second.png'), intent: 'publish');
            $this->fail('Invalid action must roll back.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($original, $tip->fresh()->image_path);
            Storage::disk('local')->assertExists($original);
            $this->assertCount(1, Storage::disk('local')->allFiles('tips'));
        }
        $updated = $this->service->save($this->coach, $this->content(), $tip->id, UploadedFile::fake()->image('third.png'));
        Storage::disk('local')->assertMissing($original);
        Storage::disk('local')->assertExists($updated->image_path);
        $removed = $this->service->save($this->coach, $this->content(), $tip->id, removeImage: true);
        $this->assertNull($removed->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('tips'));
    }

    public function test_invalid_image_is_rejected_before_commit(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->save($this->coach, $this->content(), image: UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'));
    }

    public function test_uploaded_image_is_resized_and_stored_optimized(): void
    {
        $tip = $this->service->save(
            $this->coach,
            $this->content(),
            image: UploadedFile::fake()->image('large.png', 3000, 1800)
        );

        $this->assertMatchesRegularExpression('/\.(webp|jpg)$/', $tip->image_path);
        Storage::disk('local')->assertExists($tip->image_path);
        $stored = Storage::disk('local')->get($tip->image_path);
        $dimensions = getimagesizefromstring($stored);

        $this->assertNotFalse($dimensions);
        $this->assertContains($dimensions['mime'], ['image/webp', 'image/jpeg']);
        $this->assertLessThanOrEqual(1920, max($dimensions[0], $dimensions[1]));
    }

    public function test_private_image_is_available_to_author_and_reviewer_only(): void
    {
        $tip = $this->service->save($this->coach, $this->content(), image: UploadedFile::fake()->image('private.png'));
        $images = app(\App\Services\Tips\TipImageService::class);
        foreach ([$this->coach, $this->admin] as $actor) {
            $response = $images->responseForPanel($actor, $tip);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->expectException(AuthorizationException::class);
        $images->responseForPanel($this->actor('coach'), $tip);
    }

    public function test_scope_status_and_reviewer_input_cannot_override_server_rules(): void
    {
        $tip = $this->service->save($this->coach, array_merge($this->content(), [
            'author_id' => $this->admin->id, 'scope' => 'global', 'coach_id' => null,
            'status' => 'published', 'reviewed_by' => $this->admin->id,
        ]));
        $this->assertSame($this->coach->id, $tip->author_id);
        $this->assertSame($this->coach->id, $tip->coach_id);
        $this->assertSame(TipStatus::DRAFT, $tip->status);
        $this->assertNull($tip->reviewed_by);
    }

    public function test_coach_panel_http_create_preview_submit_and_withdraw(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureCoachSubscriptionIsActive::class);
        $this->actingAs($this->coach)->withSession(['_token' => 'tips-test-csrf']);
        $this->get(route('coach.tips.index'))->assertOk()->assertSee('Nuevo Tip');
        $this->get(route('coach.tips.create'))->assertOk()
            ->assertSee('Guardar borrador')
            ->assertSee('data-tip-form', false)
            ->assertSee('data-tip-image-preview', false);
        $this->post(route('coach.tips.store'), array_merge($this->content(), [
            'type' => 'tip', 'category' => 'general', '_token' => 'tips-test-csrf',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $tip = Tip::firstOrFail();
        $this->get(route('coach.tips.show', $tip))->assertOk()->assertSee($tip->title);
        $this->post(route('coach.tips.transition', [$tip, 'submit']), ['_token' => 'tips-test-csrf'])->assertRedirect();
        $this->assertSame(TipStatus::PENDING_APPROVAL, $tip->fresh()->status);
        $this->get(route('coach.tips.edit', $tip))->assertRedirect(route('coach.tips.show', $tip));
        $this->post(route('coach.tips.transition', [$tip, 'withdraw']), ['_token' => 'tips-test-csrf'])->assertRedirect();
        $this->assertSame(TipStatus::DRAFT, $tip->fresh()->status);
        $this->get(route('coach.tips.edit', $tip))->assertOk();
    }

    public function test_admin_panel_creates_global_and_moderates_without_editing_coach_content(): void
    {
        $this->withoutVite();
        $this->admin->email_verified_at = null;
        $this->admin->save();
        $this->actingAs($this->admin)->withSession(['_token' => 'admin-tips-csrf'])->withHeader('X-CSRF-TOKEN', 'admin-tips-csrf');
        $this->get('/admin/tips')->assertOk();
        $this->get('/admin/tips/create')->assertOk()->assertSee('Publicar');
        $this->post('/admin/tips', array_merge($this->content(), ['type' => 'news', 'category' => 'general', 'intent' => 'publish']))->assertRedirect();
        $global = Tip::firstOrFail();
        $this->assertNull($global->coach_id);
        $this->assertSame(TipStatus::PUBLISHED, $global->status);
        $this->get('/admin/tips/'.$global->id)->assertOk()->assertDontSee('Motivo del rechazo');
        $pending = $this->service->save($this->coach, $this->content(), intent: 'submit');
        $url = '/admin/tips/'.$pending->id;
        $this->get('/admin/tips/pending')->assertOk()->assertSee($this->coach->name);
        $this->get($url)->assertOk()->assertSee('Aprobar')->assertSee('Motivo del rechazo');
        $this->get($url.'/edit')->assertNotFound();
        $this->put($url, [])->assertNotFound();
        $this->post($url.'/reject', [])->assertSessionHasErrors('rejection_reason');
        $this->post($url.'/reject', ['rejection_reason' => 'Explica mejor el consejo'])->assertRedirect($url);
        $this->assertSame(TipStatus::REJECTED, $pending->fresh()->status);
        $this->service->save($this->coach, $this->content(), $pending->id, intent: 'submit');
        $this->post($url.'/approve')->assertRedirect($url);
        $this->assertSame(TipStatus::PUBLISHED, $pending->fresh()->status);
        $this->assertSame($this->admin->id, $pending->fresh()->reviewed_by);
        $this->postJson($url.'/reject', ['rejection_reason' => 'Revisión vieja'])->assertStatus(409);
    }

    public function test_coach_cannot_enter_admin_moderation_routes(): void
    {
        $this->actingAs($this->coach)->withSession(['_token' => 'csrf-test'])->withHeader('X-CSRF-TOKEN', 'csrf-test');
        $tip = $this->service->save($this->coach, $this->content(), intent: 'submit');
        $this->get('/admin/tips/pending')->assertForbidden();
        $this->post('/admin/tips/'.$tip->id.'/approve')->assertForbidden();
        $this->assertSame(TipStatus::PENDING_APPROVAL, $tip->fresh()->status);
    }

    public function test_coach_panel_excludes_other_author_and_escapes_content(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureCoachSubscriptionIsActive::class);
        $other = $this->service->save($this->actor('coach'), ['title' => 'Contenido ajeno', 'body' => 'Privado']);
        $own = $this->service->save($this->coach, ['title' => 'Consejo 100%', 'body' => '<script>alert(1)</script>']);
        $this->actingAs($this->coach);
        $this->get(route('coach.tips.index'))->assertOk()->assertDontSee('Contenido ajeno');
        $this->get(route('coach.tips.show', $other))->assertNotFound();
        $this->get(route('coach.tips.image', $other))->assertNotFound();
        $this->get(route('coach.tips.show', $own))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('coach.tips.index', ['q' => '%']))->assertOk()->assertSee('Consejo 100%');
    }
}
