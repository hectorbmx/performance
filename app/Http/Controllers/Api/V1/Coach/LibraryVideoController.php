<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\LibraryVideo;
use App\Models\TrainingTypeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LibraryVideoController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 20), 50);

        $query = LibraryVideo::query()
            ->visibleForCoach($coachId)
            ->where('is_active', true)
            ->with('type:id,name')
            ->latest();

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('youtube_id', 'like', "%{$q}%");
            });
        }

        return response()->json([
            'ok' => true,
            'data' => $query->paginate($perPage)->through(fn (LibraryVideo $video) => $this->videoPayload($video)),
        ]);
    }

    public function meta(Request $request)
    {
        $coachId = $request->user()->id;

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => TrainingTypeCatalog::query()
                    ->where('coach_id', $coachId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'source' => ['required', Rule::in(['youtube', 'upload'])],
            'youtube_url' => ['required_if:source,youtube', 'nullable', 'string', 'max:255'],
            'video_file' => ['required_if:source,upload', 'nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-m4v,video/webm', 'max:51200'],
            'training_type_catalog_id' => [
                'nullable',
                'integer',
                Rule::exists('training_type_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
        ]);

        if ($data['source'] === 'youtube') {
            $youtubeId = $this->extractYoutubeId((string) $data['youtube_url']);

            if (!$youtubeId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'URL de YouTube invalida.',
                    'errors' => ['youtube_url' => ['URL de YouTube invalida.']],
                ], 422);
            }

            $video = LibraryVideo::updateOrCreate(
                ['coach_id' => $coachId, 'youtube_id' => $youtubeId],
                [
                    'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
                    'source' => 'youtube',
                    'name' => $data['name'],
                    'youtube_url' => $data['youtube_url'],
                    'video_path' => null,
                    'thumbnail_url' => "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
                    'is_active' => true,
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Video agregado correctamente.',
                'data' => $this->videoPayload($video->load('type:id,name')),
            ], 201);
        }

        $path = $request->file('video_file')->store("library-videos/coach-{$coachId}", 'public');

        $video = LibraryVideo::create([
            'coach_id' => $coachId,
            'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
            'source' => 'upload',
            'name' => $data['name'],
            'youtube_url' => '',
            'youtube_id' => 'upload-' . uniqid(),
            'video_path' => $path,
            'thumbnail_url' => null,
            'is_active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Video subido correctamente.',
            'data' => $this->videoPayload($video->load('type:id,name')),
        ], 201);
    }

    public function destroy(Request $request, LibraryVideo $video)
    {
        abort_unless((int) $video->coach_id === (int) $request->user()->id, 403);

        if ($video->video_path) {
            Storage::disk('public')->delete($video->video_path);
        }

        $video->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Video eliminado.',
        ]);
    }

    private function videoPayload(LibraryVideo $video): array
    {
        return [
            'id' => $video->id,
            'name' => $video->name,
            'source' => $video->source ?? 'youtube',
            'youtube_url' => $video->youtube_url ?: null,
            'youtube_id' => $video->youtube_id,
            'video_path' => $video->video_path,
            'thumbnail_url' => $video->thumbnail_url,
            'playback_url' => $video->video_path
                ? url(Storage::disk('public')->url($video->video_path))
                : $video->youtube_url,
            'training_type_catalog_id' => $video->training_type_catalog_id,
            'type' => $video->type ? [
                'id' => $video->type->id,
                'name' => $video->type->name,
            ] : null,
            'created_at' => optional($video->created_at)->toIso8601String(),
        ];
    }

    private function extractYoutubeId(string $url): ?string
    {
        if (preg_match('~youtu\.be/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['v'])) return $q['v'];
        }

        if (preg_match('~youtube\.com/shorts/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];
        if (preg_match('~youtube\.com/embed/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];

        return null;
    }
}
