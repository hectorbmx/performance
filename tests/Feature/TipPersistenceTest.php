<?php

namespace Tests\Feature;

use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TipPersistenceTest extends TestCase
{
    private $migration;

    protected function setUp(): void
    {
        parent::setUp();

        // Dedicated in-memory connection: never migrate/reset the configured MySQL database.
        config(['database.connections.tips_test_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('tips_test_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        $this->migration = require database_path('migrations/2026_09_11_000001_create_tips_table.php');
        $this->migration->up();
        (require database_path('migrations/2026_09_15_000001_add_expires_at_to_tips_table.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::purge('tips_test_memory');
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function draft(User $author, TipScope $scope = TipScope::TENANT): Tip
    {
        $tip = new Tip(['title' => '  Un consejo  ', 'body' => "  Texto\ncon párrafos  "]);
        $tip->author()->associate($author);
        $tip->scope = $scope;
        if ($scope === TipScope::TENANT) {
            $tip->coach()->associate($author);
        }
        $tip->save();

        return $tip;
    }

    public function test_tenant_and_global_drafts_persist_without_publishing(): void
    {
        $coach = $this->user();
        $admin = $this->user();
        $tenant = $this->draft($coach)->fresh();
        $global = $this->draft($admin, TipScope::GLOBAL)->fresh();

        $this->assertTrue($tenant->author->is($coach));
        $this->assertTrue($tenant->coach->is($coach));
        $this->assertNull($global->coach);
        $this->assertSame(TipScope::GLOBAL, $global->scope);
        $this->assertSame(TipStatus::DRAFT, $tenant->status);
        $this->assertNull($tenant->published_at);
        $this->assertNull($tenant->expires_at);
        $this->assertNull($tenant->reviewer);
        $this->assertSame('Un consejo', $tenant->title);
        $this->assertSame("Texto\ncon párrafos", $tenant->body);
    }

    public function test_optional_expiration_date_is_editorial_content(): void
    {
        $tip = $this->draft($this->user());
        $tip->fill(['expires_at' => now()->addDays(5)]);
        $tip->save();

        $this->assertTrue($tip->fresh()->expires_at->isSameDay(now()->addDays(5)));
    }

    public function test_editorial_mass_assignment_cannot_replace_ownership_or_status(): void
    {
        $tip = $this->draft($this->user());
        $authorId = $tip->author_id;
        $tip->fill(['title' => 'Otro consejo', 'author_id' => 123, 'coach_id' => 123,
            'scope' => 'global', 'status' => 'published', 'reviewed_by' => 123,
            'image_path' => 'arbitrary/path', 'published_at' => now()]);
        $tip->save();
        $tip->refresh();

        $this->assertSame($authorId, $tip->author_id);
        $this->assertSame($authorId, $tip->coach_id);
        $this->assertSame(TipScope::TENANT, $tip->scope);
        $this->assertSame(TipStatus::DRAFT, $tip->status);
        $this->assertNull($tip->reviewed_by);
        $this->assertNull($tip->published_at);
        $this->assertNull($tip->image_path);
    }

    public function test_tenant_cannot_be_associated_with_another_coach(): void
    {
        $tip = new Tip(['title' => 'Consejo', 'body' => 'Texto']);
        $tip->author()->associate($this->user());
        $tip->coach()->associate($this->user());
        $tip->scope = TipScope::TENANT;
        $this->expectException(ValidationException::class);
        $tip->save();
    }

    public function test_existing_ownership_is_immutable_even_with_direct_assignment(): void
    {
        $tip = $this->draft($this->user());
        $tip->scope = TipScope::GLOBAL;
        $tip->coach_id = null;
        $this->expectException(ValidationException::class);
        $tip->save();
    }

    public function test_blank_body_is_rejected(): void
    {
        $tip = $this->draft($this->user());
        $tip->body = '   ';
        $this->expectException(ValidationException::class);
        $tip->save();
    }

    public function test_author_cannot_be_deleted_leaving_an_orphan_tip(): void
    {
        $author = $this->user();
        $this->draft($author);
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $author->id)->delete();
    }

    public function test_reviewer_deletion_preserves_tip_and_review_date(): void
    {
        $tip = $this->draft($this->user());
        $reviewer = $this->user();
        $tip->reviewer()->associate($reviewer);
        $tip->reviewed_at = now();
        $tip->save();
        $this->assertTrue($tip->reviewer->is($reviewer));
        DB::table('users')->where('id', $reviewer->id)->delete();
        $tip->refresh();
        $this->assertNull($tip->reviewed_by);
        $this->assertNotNull($tip->reviewed_at);
    }

    public function test_migration_can_rollback_and_reapply_without_touching_users(): void
    {
        $author = $this->user();
        $this->migration->down();
        $this->assertFalse(Schema::hasTable('tips'));
        $this->assertTrue(User::whereKey($author->id)->exists());
        $this->migration->up();
        $this->assertSame(TipStatus::DRAFT, $this->draft($author)->status);
    }
}
