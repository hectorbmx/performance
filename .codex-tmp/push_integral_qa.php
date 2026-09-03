$run = now()->format('YmdHis');
$prefix = 'codex-integral-push-' . $run;

$coach = \App\Models\User::query()->create([
    'name' => 'Codex Integral Push Coach ' . $run,
    'email' => $prefix . '@example.test',
    'password' => bcrypt('password'),
]);

$goal = \App\Models\TrainingGoalCatalog::query()->create([
    'coach_id' => $coach->id,
    'name' => 'Codex Goal ' . $run,
    'description' => 'QA integral push',
    'is_active' => true,
]);

$type = \App\Models\TrainingTypeCatalog::query()->create([
    'coach_id' => $coach->id,
    'name' => 'Codex Type ' . $run,
    'description' => 'QA integral push',
    'behavior' => 'standard',
    'is_active' => true,
]);

$clients = collect(range(1, 4))->map(function ($i) use ($coach, $prefix) {
    $client = \App\Models\Client::query()->create([
        'coach_id' => $coach->id,
        'first_name' => 'CodexIntegral' . $i,
        'last_name' => 'Push',
        'email' => $prefix . '-client-' . $i . '@example.test',
        'phone' => '55510000' . $i,
        'is_active' => true,
    ]);

    $userApp = \App\Models\UserApp::query()->create([
        'client_id' => $client->id,
        'email' => $prefix . '-userapp-' . $i . '@example.test',
        'password' => bcrypt('password'),
        'is_active' => true,
        'activated_at' => now(),
    ]);

    return [$client, $userApp];
});

$inactiveClient = \App\Models\Client::query()->create([
    'coach_id' => $coach->id,
    'first_name' => 'CodexInactive',
    'last_name' => 'Push',
    'email' => $prefix . '-inactive@example.test',
    'phone' => '555199999',
    'is_active' => false,
]);

$inactiveUserApp = \App\Models\UserApp::query()->create([
    'client_id' => $inactiveClient->id,
    'email' => $prefix . '-inactive-userapp@example.test',
    'password' => bcrypt('password'),
    'is_active' => true,
    'activated_at' => now(),
]);

$group = \App\Models\Group::query()->create([
    'coach_id' => $coach->id,
    'name' => 'Codex Integral Group ' . $run,
    'description' => 'QA integral push',
    'is_active' => true,
]);

[$client1, $userApp1] = $clients[0];
[$client2, $userApp2] = $clients[1];
[$client3, $userApp3] = $clients[2];
[$client4, $userApp4] = $clients[3];
$group->clients()->syncWithoutDetaching([$client2->id, $client3->id, $inactiveClient->id]);

$service = app(\App\Services\AppNotificationService::class);
$beforeNotificationId = \App\Models\PushNotification::query()->max('id') ?? 0;

$free = \App\Models\TrainingSession::query()->create([
    'coach_id' => $coach->id,
    'title' => 'Codex Integral Free ' . $run,
    'scheduled_at' => now()->addDay()->toDateString(),
    'duration_minutes' => 45,
    'level' => 'beginner',
    'training_goal_catalog_id' => $goal->id,
    'training_type_catalog_id' => $type->id,
    'visibility' => 'free',
]);
$service->notifyTrainingCreated($free, $coach->id);

$assignedDirect = \App\Models\TrainingSession::query()->create([
    'coach_id' => $coach->id,
    'title' => 'Codex Integral Assigned Direct ' . $run,
    'scheduled_at' => now()->addDays(2)->toDateString(),
    'duration_minutes' => 60,
    'level' => 'intermediate',
    'training_goal_catalog_id' => $goal->id,
    'training_type_catalog_id' => $type->id,
    'visibility' => 'assigned',
]);
$service->notifyTrainingCreated($assignedDirect, $coach->id, [$client1->id, $client2->id, $client2->id, $inactiveClient->id], []);

$assignedGroup = \App\Models\TrainingSession::query()->create([
    'coach_id' => $coach->id,
    'title' => 'Codex Integral Assigned Group ' . $run,
    'scheduled_at' => now()->addDays(3)->toDateString(),
    'duration_minutes' => 60,
    'level' => 'advanced',
    'training_goal_catalog_id' => $goal->id,
    'training_type_catalog_id' => $type->id,
    'visibility' => 'assigned',
]);
\App\Models\GroupTrainingAssignment::query()->create([
    'group_id' => $group->id,
    'training_session_id' => $assignedGroup->id,
    'scheduled_for' => $assignedGroup->scheduled_at->toDateString(),
    'notes' => null,
]);
$resolvedGroup = $service->trainingRecipientClientIds($assignedGroup, $coach->id, [$client1->id, $client2->id], [$group->id]);
$service->notifyTrainingCreated($assignedGroup, $coach->id, [$client1->id, $client2->id], [$group->id]);

$assignedForUpdate = \App\Models\TrainingSession::query()->create([
    'coach_id' => $coach->id,
    'title' => 'Codex Integral Update New Recipients ' . $run,
    'scheduled_at' => now()->addDays(4)->toDateString(),
    'duration_minutes' => 50,
    'level' => 'intermediate',
    'training_goal_catalog_id' => $goal->id,
    'training_type_catalog_id' => $type->id,
    'visibility' => 'assigned',
]);
$previousRecipients = $service->trainingRecipientClientIds($assignedForUpdate, $coach->id, [$client1->id], []);
$newRecipients = $service->trainingRecipientClientIds($assignedForUpdate, $coach->id, [$client1->id, $client4->id], []);
$service->notifyTrainingCreated($assignedForUpdate, $coach->id, array_values(array_diff($newRecipients, $previousRecipients)));

$request = \Illuminate\Http\Request::create('/api/v1/app/training-sessions/' . $assignedGroup->id . '/assignment', 'GET', [
    'scheduled_for' => $assignedGroup->scheduled_at->toDateString(),
]);
$request->setUserResolver(fn () => $userApp3);
$lookupResponse = app(\App\Http\Controllers\Api\V1\App\Client\TrainingSessionsController::class)->resolveAssignment($request, $assignedGroup);
$lookupData = json_decode($lookupResponse->getContent(), true);

$pushTestRequest = \Illuminate\Http\Request::create('/api/v1/app/test/push', 'POST', [
    'user_id' => $userApp1->id,
    'training_session_id' => $assignedDirect->id,
    'source' => 'assigned',
]);
$pushTestResponse = app(\App\Http\Controllers\Api\V1\App\PushTestController::class)->send($pushTestRequest, $service);
$pushTestData = json_decode($pushTestResponse->getContent(), true);

$notifications = \App\Models\PushNotification::query()
    ->where('id', '>', $beforeNotificationId)
    ->orderBy('id')
    ->get(['id', 'user_id', 'type', 'status', 'provider', 'data', 'error']);

$summary = [
    'run' => $run,
    'coach_id' => $coach->id,
    'active_client_ids' => [$client1->id, $client2->id, $client3->id, $client4->id],
    'inactive_client_id' => $inactiveClient->id,
    'group_id' => $group->id,
    'training_ids' => [
        'free' => $free->id,
        'assigned_direct' => $assignedDirect->id,
        'assigned_group' => $assignedGroup->id,
        'assigned_update_new_only' => $assignedForUpdate->id,
    ],
    'resolved_group_client_ids' => $resolvedGroup,
    'previous_update_recipient_ids' => $previousRecipients,
    'new_update_recipient_ids' => $newRecipients,
    'diff_update_recipient_ids' => array_values(array_diff($newRecipients, $previousRecipients)),
    'lookup_status_code' => $lookupResponse->getStatusCode(),
    'lookup_response' => $lookupData,
    'push_test_status_code' => $pushTestResponse->getStatusCode(),
    'push_test_response' => $pushTestData,
    'created_notifications_count' => $notifications->count(),
    'by_type' => $notifications->groupBy('type')->map->count(),
    'by_status' => $notifications->groupBy('status')->map->count(),
    'notifications' => $notifications->map(fn ($row) => [
        'id' => $row->id,
        'user_id' => $row->user_id,
        'type' => $row->type,
        'status' => $row->status,
        'provider' => $row->provider,
        'data' => $row->data,
        'error' => $row->error,
    ])->values(),
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
