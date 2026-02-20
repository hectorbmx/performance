<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\LibraryVideo;
use App\Models\TrainingTypeCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LibraryController extends Controller
{
    public function index()
    {
        $coachId = auth()->id();

        $types = TrainingTypeCatalog::query()
            ->where('coach_id', $coachId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name']);

        $videos = LibraryVideo::query()
            ->visibleForCoach($coachId)
            ->where('is_active', 1)
            ->latest()
            ->paginate(20);

        return view('coach.library.index', compact('types','videos'));
    }

    public function store(Request $request)
    {
        $coachId = auth()->id();

        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'youtube_url' => ['required','string'],
            'training_type_catalog_id' => [
                'nullable',
                Rule::exists('training_type_catalogs','id')
                    ->where(fn($q) => $q->where('coach_id', $coachId))
            ],
        ]);

        $youtubeId = $this->extractYoutubeId($data['youtube_url']);

        if (!$youtubeId) {
            return back()->withErrors(['youtube_url' => 'URL de YouTube inválida.']);
        }

        LibraryVideo::updateOrCreate(
            ['coach_id' => $coachId, 'youtube_id' => $youtubeId],
            [
                'name' => $data['name'],
                'youtube_url' => $data['youtube_url'],
                'training_type_catalog_id' => $data['training_type_catalog_id'] ?? null,
                'thumbnail_url' => "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
                'is_active' => true,
            ]
        );

        return back()->with('success', 'Video agregado correctamente.');
    }

    public function destroy(LibraryVideo $video)
    {
        abort_unless($video->coach_id === auth()->id(), 403);

        $video->delete();

        return back()->with('success', 'Video eliminado.');
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
    public function search(Request $request)
        {
            $coachId = auth()->id();
            $q = trim((string) $request->query('q', ''));

            if (mb_strlen($q) < 2) {
                return response()->json([]);
            }

            $videos = LibraryVideo::query()
                ->visibleForCoach($coachId)
                ->where('is_active', 1)
                ->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhere('youtube_id', $q);
                })
                ->orderBy('name')
                ->limit(10)
                ->get(['id','name','youtube_id']);

            return response()->json($videos);
        }


}
