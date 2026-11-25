import { CoachTrainingPayload, CoachTrainingsService } from './coach-trainings.service';
import { ApiService } from './api.service';

describe('CoachTrainingsService', () => {
  it('preserves section, block and row identities in an edit request', async () => {
    const api = jasmine.createSpyObj<ApiService>('ApiService', ['postForm']);
    api.postForm.and.resolveTo({ data: { id: 10 } });
    const service = new CoachTrainingsService(api);
    const payload: CoachTrainingPayload = {
      id: 10, title: ' Rutina ', scheduled_at: '2026-11-13',
      level: 'intermediate', visibility: 'assigned', assigned_client_ids: [5],
      sections: [{ id: 21, name: ' Fuerza ', lifting_blocks: [{
        id: 31, workout_exercise_id: 51, exercise_name: 'Back Squat', rows: [{ id: 41, sets: 3, reps: 10 }],
      }] }, { name: 'Nueva sección' }],
    };
    await service.update(10, payload);
    const [url, form] = api.postForm.calls.mostRecent().args;
    expect(url).toBe('coach/trainings/10');
    expect(form.get('sections[0][id]')).toBe('21');
    expect(form.get('sections[0][lifting_blocks][0][id]')).toBe('31');
    expect(form.get('sections[0][lifting_blocks][0][workout_exercise_id]')).toBe('51');
    expect(form.get('sections[0][lifting_blocks][0][rows][0][id]')).toBe('41');
    expect(form.has('sections[1][id]')).toBeFalse();
    expect(payload.sections[0].name).toBe(' Fuerza ');
  });
});
