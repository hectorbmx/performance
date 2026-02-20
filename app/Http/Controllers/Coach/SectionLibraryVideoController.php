<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\LibraryVideo;
use App\Models\TrainingSection;
use Illuminate\Http\Request;

class SectionLibraryVideoController extends Controller
{
    public function store(Request $request, TrainingSection $section)
    {
        abort_unless($section->trainingSession->coach_id === auth()->id(), 403);

        // TODO: ajusta esta autorización a tu modelo real:
        // abort_unless($section->trainingSession->coach_id === auth()->id(), 403);

        $data = $request->validate([
            'library_video_id' => ['required','integer','exists:library_videos,id'],
        ]);

        $video = LibraryVideo::query()->findOrFail($data['library_video_id']);

        // Solo permitir agregar videos globales (coach_id null) o del mismo coach
        abort_unless($video->coach_id === null || $video->coach_id === auth()->id(), 403);

        // order = siguiente
        $maxOrder = $section->libraryVideos()->max('training_section_library_videos.order') ?? 0;

        $section->libraryVideos()->syncWithoutDetaching([
            $video->id => ['order' => $maxOrder + 1]
        ]);
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'video' => [
                    'id' => $video->id,
                    'name' => $video->name,
                    'order' => $maxOrder + 1,
                ],
            ]);
        }
        return back()->with('success', 'Ejercicio agregado a la sección.');
    }

    public function destroy(Request $request, TrainingSection $section, LibraryVideo $video)
    {
        abort_unless($section->trainingSession->coach_id === auth()->id(), 403);

        // TODO: misma autorización del store
        // abort_unless($section->trainingSession->coach_id === auth()->id(), 403);

        $section->libraryVideos()->detach($video->id);
      
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }


        return back()->with('success', 'Ejercicio eliminado de la sección.');
    }
}
