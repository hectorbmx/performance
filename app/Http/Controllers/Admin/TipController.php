<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Http\Requests\Tips\SaveTipRequest;
use App\Models\Tip;
use App\Services\Tips\TipService;
use App\Services\Tips\TipImageService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TipController extends Controller
{
    public function index(Request $request): View
    {
        $tips = Tip::where('scope', TipScope::GLOBAL->value)
            ->where('author_id', $request->user()->id)->latest()->paginate(15);
        return view('admin.tips.index', ['tips' => $tips, 'pending' => false]);
    }

    public function pending(): View
    {
        $tips = Tip::with('author:id,name')->where('scope', TipScope::TENANT->value)
            ->where('status', TipStatus::PENDING_APPROVAL->value)->oldest('updated_at')->orderBy('id')->paginate(15);
        return view('admin.tips.index', ['tips' => $tips, 'pending' => true]);
    }

    public function create(): View
    {
        return view('admin.tips.edit', ['tip' => new Tip]);
    }

    public function show(Tip $tip): View
    {
        $this->authorize('view', $tip);
        $tip->load('author:id,name');
        return view('admin.tips.show', compact('tip'));
    }

    public function edit(Tip $tip)
    {
        $this->authorize('update', $tip);
        if ($tip->status !== TipStatus::DRAFT) {
            return redirect()->route('admin.tips.show', $tip)->with('error', 'Restaura la publicación a borrador antes de editarla.');
        }
        return view('admin.tips.edit', compact('tip'));
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
            return redirect()->route('admin.tips.show', $tip)->with('success', $tip->status === TipStatus::PUBLISHED ? 'Publicación global publicada.' : 'Borrador guardado.');
        } catch (ConflictHttpException $exception) {
            if ($request->expectsJson()) throw $exception;
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function transition(Request $request, Tip $tip, string $action, TipService $service)
    {
        abort_unless(in_array($action, ['publish', 'approve', 'reject', 'archive', 'restore'], true), 404);
        $this->authorize('view', $tip);
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:2000']]);
        try {
            $service->transition($request->user(), $tip->id, $action, $data['rejection_reason'] ?? null);
            return redirect()->route('admin.tips.show', $tip)->with('success', 'Estado actualizado.');
        } catch (ConflictHttpException $exception) {
            if ($request->expectsJson()) throw $exception;
            return back()->with('error', $exception->getMessage());
        }
    }

    public function image(Request $request, Tip $tip, TipImageService $images)
    {
        return $images->responseForPanel($request->user(), $tip);
    }
}
