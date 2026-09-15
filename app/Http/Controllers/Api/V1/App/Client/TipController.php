<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Enums\TipCategory;
use App\Enums\TipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tips\ListAppTipsRequest;
use App\Http\Resources\AppTipDetailResource;
use App\Http\Resources\AppTipResource;
use App\Models\UserApp;
use App\Services\ClientMembershipAccessService;
use App\Services\Tips\TipVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TipController extends Controller
{
    public function categories(Request $request, TipVisibilityService $visibility): JsonResponse
    {
        $this->ensureUserApp($request);
        $this->ensureNotInactive($request->user(), $visibility);

        return response()->json([
            'ok' => true,
            'data' => collect(TipCategory::labels())
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function index(ListAppTipsRequest $request, TipVisibilityService $visibility): JsonResponse
    {
        $userApp = $this->ensureUserApp($request);
        $this->ensureNotInactive($userApp, $visibility);
        $filters = $request->filters();

        $query = $visibility->visibleQuery($userApp)
            ->when($filters['category'], fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereRaw('title like ? escape "\\"', [$like])
                        ->orWhereRaw('body like ? escape "\\"', [$like]);
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $tips = $query->paginate($filters['per_page']);

        return response()->json([
            'ok' => true,
            'data' => AppTipResource::collection($tips->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $tips->currentPage(),
                'per_page' => $tips->perPage(),
                'last_page' => $tips->lastPage(),
                'total' => $tips->total(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, TipVisibilityService $visibility, int $tip): JsonResponse
    {
        $userApp = $this->ensureUserApp($request);
        $this->ensureNotInactive($userApp, $visibility);
        $tip = $visibility->findVisible($userApp, $tip);

        return response()->json([
            'ok' => true,
            'data' => (new AppTipDetailResource($tip))->resolve($request),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function image(Request $request, TipVisibilityService $visibility, int $tip): StreamedResponse
    {
        $userApp = $this->ensureUserApp($request);
        $this->ensureNotInactive($userApp, $visibility);
        $tip = $visibility->findVisible($userApp, $tip);

        abort_unless($tip->image_disk === 'local' && $tip->image_path && Storage::disk('local')->exists($tip->image_path), 404);

        return Storage::disk('local')->response($tip->image_path, null, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function ensureUserApp(Request $request): UserApp
    {
        $user = $request->user();
        abort_unless($user instanceof UserApp, 403, 'client_auth_required');

        return $user;
    }

    private function ensureNotInactive(UserApp $userApp, TipVisibilityService $visibility): void
    {
        $access = $visibility->accessFor($userApp);

        abort_if($access->state === ClientMembershipAccessService::STATE_INACTIVE_USER, response()->json([
            'ok' => false,
            'code' => 'membership_expired',
            'access_state' => $access->state,
            'message' => $access->message,
        ], 403));
    }
}
