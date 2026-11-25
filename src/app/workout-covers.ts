export const WORKOUT_COVERS = [
  { name: 'Snatch', src: 'assets/workout-covers/snatch.jpg' },
  { name: 'Clean', src: 'assets/workout-covers/clean.jpg' },
  { name: 'Back Squat', src: 'assets/workout-covers/back-squat.jpg' },
  { name: 'Front Squat', src: 'assets/workout-covers/front-squat.jpg' },
] as const;

export type WorkoutCover = (typeof WORKOUT_COVERS)[number];

export function workoutCoverForSession(id: number | null | undefined): string {
  const index = id != null && Number.isSafeInteger(id) && id >= 0 ? id % WORKOUT_COVERS.length : 0;
  return WORKOUT_COVERS[index].src;
}

export function useWorkoutCoverOnError(event: Event, fallback: string): void {
  const image = event.target as HTMLImageElement;
  if (image.getAttribute('src') !== fallback) image.src = fallback;
}
