import { TestBed } from '@angular/core/testing';
import { AthleteActivityCalendarService } from './athlete-activity-calendar.service';
import { ApiService } from './api.service';

describe('AthleteActivityCalendarService', () => {
  let service: AthleteActivityCalendarService;
  let api: jasmine.SpyObj<ApiService>;

  beforeEach(() => {
    api = jasmine.createSpyObj<ApiService>('ApiService', ['get']);

    TestBed.configureTestingModule({
      providers: [
        AthleteActivityCalendarService,
        { provide: ApiService, useValue: api },
      ],
    });

    service = TestBed.inject(AthleteActivityCalendarService);
  });

  it('loads the current month without query params', async () => {
    api.get.and.resolveTo(calendarResponse('2026-09'));

    const result = await service.month();

    expect(api.get).toHaveBeenCalledWith('app/activity-calendar', undefined);
    expect(result.month).toBe('2026-09');
  });

  it('loads a specific month using trimmed query params', async () => {
    api.get.and.resolveTo(calendarResponse('2026-10'));

    const result = await service.month(' 2026-10 ');

    expect(api.get).toHaveBeenCalledWith('app/activity-calendar', { month: '2026-10' });
    expect(result.days[0].status).toBe('completed');
  });
});

function calendarResponse(month: string) {
  return {
    ok: true,
    month,
    range: {
      from: `${month}-01`,
      to: `${month}-30`,
    },
    summary: {
      completed_days: 1,
      missed_days: 0,
      scheduled_days: 0,
      free_completed_days: 0,
      rest_days: 29,
      training_days: 1,
      activity_days: 1,
    },
    days: [
      {
        date: `${month}-01`,
        status: 'completed',
        assigned_count: 1,
        assigned_completed_count: 1,
        assigned_in_progress_count: 0,
        free_completed_count: 0,
        has_activity: true,
      },
    ],
  };
}
