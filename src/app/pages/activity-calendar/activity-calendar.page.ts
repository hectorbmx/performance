import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { calendarOutline, refreshOutline } from 'ionicons/icons';
import { AthleteActivityCalendarComponent } from 'src/app/components/athlete-activity-calendar/athlete-activity-calendar.component';
import {
  ActivityCalendarDayDTO,
  ActivityCalendarResponse,
  AthleteActivityCalendarService,
  LiftingExerciseTrendDTO,
  LiftingPerformanceExerciseDTO,
  LiftingTrendBucketDTO,
} from 'src/app/services/athlete-activity-calendar.service';

@Component({
  selector: 'app-activity-calendar',
  standalone: true,
  imports: [
    CommonModule,
    AthleteActivityCalendarComponent,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './activity-calendar.page.html',
  styleUrls: ['./activity-calendar.page.scss'],
})
export class ActivityCalendarPage {
  private readonly activityCalendar = inject(AthleteActivityCalendarService);

  loading = false;
  errorMsg: string | null = null;
  performanceLoading = false;
  performanceErrorMsg: string | null = null;
  calendar: ActivityCalendarResponse | null = null;
  liftingExercises: LiftingPerformanceExerciseDTO[] = [];
  liftingTrendsByExercise: Record<string, LiftingExerciseTrendDTO> = {};
  selectedLiftingExerciseKey: string | null = null;
  selectedLiftingExerciseName: string | null = null;
  liftingTrendMode: 'weekly' | 'monthly' = 'weekly';
  selectedDay: ActivityCalendarDayDTO | null = null;
  month = this.currentMonth();

  constructor() {
    addIcons({
      calendarOutline,
      refreshOutline,
    });
  }

  async ionViewWillEnter(): Promise<void> {
    if (!this.calendar) {
      await this.refreshAll();
    }
  }

  async refreshAll(): Promise<void> {
    await Promise.all([this.load(), this.loadLiftingPerformance()]);
  }

  async load(month = this.month): Promise<void> {
    this.loading = true;
    this.errorMsg = null;

    try {
      const result = await this.activityCalendar.month(month);
      this.calendar = result;
      this.month = result.month;
      this.selectedDay = result.days.find(day => day.date === this.selectedDay?.date)
        ?? result.days.find(day => day.date === this.todayKey())
        ?? null;
    } catch (err: any) {
      this.errorMsg = err?.message ?? 'No se pudo cargar el calendario.';
    } finally {
      this.loading = false;
    }
  }

  async changeMonth(month: string): Promise<void> {
    this.selectedDay = null;
    await this.load(month);
  }

  async loadLiftingPerformance(): Promise<void> {
    this.performanceLoading = true;
    this.performanceErrorMsg = null;

    try {
      const result = await this.activityCalendar.liftingPerformance();
      this.liftingExercises = result.data?.has_data
        ? (result.data.by_exercise ?? []).slice(0, 8)
        : [];
      this.liftingTrendsByExercise = this.mapLiftingTrends(result.data?.trends_by_exercise ?? []);

      if (this.selectedLiftingExerciseKey && !this.liftingTrendsByExercise[this.selectedLiftingExerciseKey]) {
        this.selectedLiftingExerciseKey = null;
        this.selectedLiftingExerciseName = null;
      }

      if (!this.selectedLiftingExerciseKey && this.liftingExercises.length > 0) {
        this.selectLiftingExercise(this.liftingExercises[0]);
      }
    } catch (err: any) {
      this.liftingExercises = [];
      this.liftingTrendsByExercise = {};
      this.selectedLiftingExerciseKey = null;
      this.selectedLiftingExerciseName = null;
      this.performanceErrorMsg = err?.message ?? 'No se pudo cargar el rendimiento lifting.';
    } finally {
      this.performanceLoading = false;
    }
  }

  private mapLiftingTrends(trends: LiftingExerciseTrendDTO[]): Record<string, LiftingExerciseTrendDTO> {
    return trends.reduce<Record<string, LiftingExerciseTrendDTO>>((map, trend) => {
      if (trend.exercise_key) {
        map[trend.exercise_key] = trend;
      }

      return map;
    }, {});
  }

  selectLiftingExercise(exercise: LiftingPerformanceExerciseDTO): void {
    if (!exercise.exercise_key) {
      return;
    }

    this.selectedLiftingExerciseKey = exercise.exercise_key;
    this.selectedLiftingExerciseName = exercise.exercise_name;
  }

  selectLiftingExerciseByKey(exerciseKey: string | null): void {
    const exercise = this.liftingExercises.find(item => item.exercise_key === exerciseKey);

    if (exercise) {
      this.selectLiftingExercise(exercise);
    }
  }

  selectedLiftingExercise(): LiftingPerformanceExerciseDTO | null {
    return this.liftingExercises.find(exercise => exercise.exercise_key === this.selectedLiftingExerciseKey) ?? null;
  }

  isLiftingExerciseSelected(exercise: LiftingPerformanceExerciseDTO): boolean {
    return !!exercise.exercise_key && this.selectedLiftingExerciseKey === exercise.exercise_key;
  }

  selectedLiftingTrend(): LiftingExerciseTrendDTO | null {
    return this.selectedLiftingExerciseKey
      ? (this.liftingTrendsByExercise[this.selectedLiftingExerciseKey] ?? null)
      : null;
  }

  selectedLiftingBuckets(): LiftingTrendBucketDTO[] {
    const trend = this.selectedLiftingTrend();
    return trend ? (trend[this.liftingTrendMode] ?? []) : [];
  }

  maxSelectedLiftingReps(): number {
    const values = this.selectedLiftingBuckets().map(bucket => Number(bucket.reps_executed || 0));
    return Math.max(1, ...values);
  }

  liftingBarHeight(bucket: LiftingTrendBucketDTO): string {
    const value = Number(bucket.reps_executed || 0);
    return `${Math.max(8, (value / this.maxSelectedLiftingReps()) * 100)}%`;
  }

  setLiftingTrendMode(mode: 'weekly' | 'monthly'): void {
    this.liftingTrendMode = mode;
  }

  selectDay(day: ActivityCalendarDayDTO): void {
    this.selectedDay = day;
  }

  statusLabel(day: ActivityCalendarDayDTO | null): string {
    switch (day?.status) {
      case 'completed':
        return 'Entrenaste';
      case 'missed':
        return 'No realizado';
      case 'scheduled':
        return 'Programado';
      case 'free_completed':
        return 'Libre realizado';
      default:
        return 'Descanso';
    }
  }

  statusPillLabel(day: ActivityCalendarDayDTO | null): string {
    switch (day?.status) {
      case 'completed':
        return 'Entrenado';
      case 'missed':
        return 'Pendiente';
      case 'scheduled':
        return 'Programado';
      case 'free_completed':
        return 'Libre';
      default:
        return 'Descanso';
    }
  }

  detailText(day: ActivityCalendarDayDTO | null): string {
    if (!day) {
      return 'Selecciona un dia para ver el detalle.';
    }

    if (day.status === 'completed') {
      if (day.free_completed_count > 0 && day.assigned_count > 0) {
        return 'Tuviste entrenamiento asignado y tambien actividad libre registrada.';
      }

      return 'Tuviste actividad registrada en un entrenamiento asignado.';
    }

    if (day.status === 'missed') {
      return 'Tenias entrenamiento asignado y no hay actividad registrada.';
    }

    if (day.status === 'scheduled') {
      return 'Hay entrenamiento programado, todavia sin actividad registrada.';
    }

    if (day.status === 'free_completed') {
      return 'No tenias asignado, pero hiciste un entrenamiento libre.';
    }

    return 'No habia entrenamiento asignado ni actividad libre.';
  }

  assignedActivityCount(day: ActivityCalendarDayDTO | null): number {
    if (!day) {
      return 0;
    }

    return day.assigned_completed_count + day.assigned_in_progress_count;
  }

  formatKg(value: number | null | undefined): string {
    return new Intl.NumberFormat('es-MX', {
      maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
  }

  formatIntensity(value: number | null | undefined): string {
    if (value === null || value === undefined) {
      return '-';
    }

    return `${Number(value).toFixed(1)}%`;
  }

  friendlyDate(value: string | null | undefined): string {
    if (!value) {
      return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
    }).format(new Date(`${value}T12:00:00`));
  }

  private currentMonth(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  }

  private todayKey(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  }
}
