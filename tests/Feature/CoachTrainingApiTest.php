<?php

namespace Tests\Feature;

use App\Models\CoachSubscription;
use App\Models\LibraryVideo;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoachTrainingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_api_creates_and_updates_lifting_blocks_and_rows(): void
    {
        $coach = $this->createCoach();
        Sanctum::actingAs($coach);
        $libraryVideo = LibraryVideo::create([
            'coach_id' => $coach->id,
            'name' => 'Squat tutorial',
            'youtube_url' => 'https://youtu.be/abc1234',
            'youtube_id' => 'abc1234',
            'thumbnail_url' => null,
            'is_active' => true,
        ]);

        $createResponse = $this->postJson('/api/v1/coach/trainings', [
            'title' => 'WOD lifting API',
            'scheduled_at' => '2026-11-01',
            'duration_minutes' => 60,
            'level' => 'intermediate',
            'goal' => 'strength',
            'type' => 'weightlifting',
            'visibility' => 'free',
            'sections' => [
                [
                    'name' => 'Main lift',
                    'result_type' => 'none',
                    'library_video_ids' => [$libraryVideo->id],
                    'lifting_blocks' => [
                        [
                            'exercise_name' => 'Back squat',
                            'notes' => 'Control the descent',
                            'rows' => [
                                [
                                    'percentage' => 80,
                                    'reps' => 3,
                                    'sets' => 5,
                                    'rest_seconds' => 180,
                                    'notes' => 'Leave one rep in reserve',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.sections.0.lifting_blocks.0.exercise_name', 'Back squat')
            ->assertJsonPath('data.sections.0.library_videos.0.id', $libraryVideo->id)
            ->assertJsonPath('data.sections.0.lifting_blocks.0.rows.0.percentage', 80)
            ->assertJsonPath('data.sections.0.lifting_blocks.0.rows.0.rest_seconds', 180);

        $trainingId = $createResponse->json('data.id');
        $blockId = $createResponse->json('data.sections.0.lifting_blocks.0.id');
        $rowId = $createResponse->json('data.sections.0.lifting_blocks.0.rows.0.id');

        $this->assertDatabaseHas('training_section_exercise_blocks', [
            'id' => $blockId,
            'exercise_name' => 'Back squat',
        ]);
        $this->assertDatabaseHas('training_section_lifting_rows', [
            'id' => $rowId,
            'percentage' => 80,
            'reps' => 3,
            'sets' => 5,
            'rest_seconds' => 180,
        ]);
        $this->assertDatabaseHas('training_section_library_videos', [
            'training_section_id' => $createResponse->json('data.sections.0.id'),
            'library_video_id' => $libraryVideo->id,
        ]);

        $this->putJson('/api/v1/coach/trainings/' . $trainingId, [
            'sections' => [
                [
                    'id' => $createResponse->json('data.sections.0.id'),
                    'name' => 'Main lift updated',
                    'result_type' => 'none',
                    'lifting_blocks' => [
                        [
                            'id' => $blockId,
                            'exercise_name' => 'Front squat',
                            'rows' => [
                                [
                                    'id' => $rowId,
                                    'percentage' => 75,
                                    'reps' => 2,
                                    'sets' => 6,
                                    'rest_seconds' => 150,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.sections.0.name', 'Main lift updated')
            ->assertJsonPath('data.sections.0.lifting_blocks.0.exercise_name', 'Front squat')
            ->assertJsonPath('data.sections.0.lifting_blocks.0.rows.0.percentage', 75);

        $this->assertDatabaseHas('training_section_exercise_blocks', [
            'id' => $blockId,
            'exercise_name' => 'Front squat',
        ]);
        $this->assertDatabaseHas('training_section_lifting_rows', [
            'id' => $rowId,
            'percentage' => 75,
            'reps' => 2,
            'sets' => 6,
            'rest_seconds' => 150,
        ]);
    }

    private function createCoach(): User
    {
        Role::findOrCreate('coach');

        $coach = User::factory()->create();
        $coach->assignRole('coach');

        $plan = MembershipPlan::create([
            'name' => 'Coach API test plan',
            'amount' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'manual',
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        CoachSubscription::create([
            'coach_id' => $coach->id,
            'membership_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'billing_cycle_days_snapshot' => 30,
            'client_limit_snapshot' => null,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'next_renewal_at' => now()->addMonth()->toDateString(),
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'paid_at' => now()->toDateString(),
        ]);

        return $coach;
    }
}
