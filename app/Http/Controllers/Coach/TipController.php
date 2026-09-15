<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Enums\TipCategory;
use App\Enums\TipStatus;
use App\Http\Requests\Tips\SaveTipRequest;
use App\Models\Tip;
use App\Services\Tips\TipService;
use App\Services\Tips\TipImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\View\View;

class TipController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(TipStatus::values())],
            'category' => ['nullable', Rule::in(TipCategory::values())],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Tip::where('author_id', $request->user()->id);
        foreach (['status', 'category'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if ($q = trim($filters['q'] ?? '')) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->where(fn ($query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("body LIKE ? ESCAPE '!'", [$pattern]));
        }
        $tips = $query->latest('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('coach.tips.index', compact('tips', 'filters'));
    }

    public function create(): View
    {
        return view('coach.tips.edit', ['tip' => new Tip]);
    }

    public function show(Tip $tip): View
    {
        $this->authorize('update', $tip);

        return view('coach.tips.show', compact('tip'));
    }

    public function edit(Tip $tip)
    {
        $this->authorize('update', $tip);
        if (! in_array($tip->status, [TipStatus::DRAFT, TipStatus::REJECTED], true)) {
            return redirect()->route('coach.tips.show', $tip)->with('error', 'Retira o restaura la publicación antes de editarla.');
        }

        return view('coach.tips.edit', compact('tip'));
    }

    public function store(SaveTipRequest $request, TipService $service)
    {
        return $this->persist($request, $service);
    }

    public function update(SaveTipRequest $request, Tip $tip, TipService $service)
    {
        return $this->persist($request, $service, $tip->id);
    }

    private function persist(SaveTipRequest $request, TipService $service, ?int $id = null)
    {
        try {
            $tip = $service->save($request->user(), $request->validated(), $id, $request->file('image'), $request->boolean('remove_image'), $request->input('intent'));

            return redirect()->route('coach.tips.show', $tip)->with('success', $tip->status === TipStatus::PENDING_APPROVAL ? 'Tip enviado a revisión.' : 'Borrador guardado.');
        } catch (ConflictHttpException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function transition(Request $request, Tip $tip, string $action, TipService $service)
    {
        abort_unless(in_array($action, ['submit', 'withdraw', 'archive', 'restore'], true), 404);
        try {
            $service->transition($request->user(), $tip->id, $action);
            return redirect()->route('coach.tips.show', $tip)->with('success', 'Estado actualizado.');
        } catch (ConflictHttpException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }
            return back()->with('error', $exception->getMessage());
        }
    }

    public function image(Request $request, Tip $tip, TipImageService $images)
    {
        $this->authorize('update', $tip);

        return $images->responseForPanel($request->user(), $tip);
    }
}
