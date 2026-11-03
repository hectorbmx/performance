import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonModal,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { calendarOutline, refreshOutline } from 'ionicons/icons';
import { AthleteActivityCalendarComponent } from 'src/app/components/athlete-activity-calendar/athlete-activity-calendar.component';
import {
  ActivityCalendarDayDTO,
  ActivityCalendarDayStatus,
} from 'src/app/services/athlete-activity-calendar.service';
import {
  CoachAthleteTrainingDTO,
  CoachAthleteTrainingRecordsDTO,
  CoachAthletesService,
} from 'src/app/services/coach-athletes.service';

@Component({
  selector: 'app-coach-athlete-calendar',
  standalone: true,
  templateUrl: './coach-athlete-calendar.page.html',
  styleUrls: ['./coach-athlete-calendar.page.scss'],
  imports: [
    CommonModule,
    AthleteActivityCalendarComponent,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonModal,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
})
export class CoachAthleteCalendarPage {
  athleteId = 0;
  trainings: CoachAthleteTrainingDTO[] = [];
  loading = false;
  calendarMonth = this.currentMonth();
  selectedCalendarDate: string | null = null;
  selectedTraining: CoachAthleteTrainingDTO | null = null;
  selectedRecords: CoachAthleteTrainingRecordsDTO | null = null;
  recordsLoading = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private athletesApi: CoachAthletesService,
    private toastCtrl: ToastController,
  ) {
    addIcons({ calendarOutline, refreshOutline });
  }

  async ionViewWillEnter(): Promise<void> {
    this.athleteId = Number(this.route.snapshot.paramMap.get('id'));
    if (!this.athleteId) {
      await this.router.navigate(['/coach/athletes']);
      return;
    }

    await this.load();
  }

  async load(): Promise<void> {
    this.loading = true;
    try {
      this.trainings = await this.athletesApi.trainings(this.athleteId);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los entrenamientos.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  calendarDays(): ActivityCalendarDayDTO[] {
    const byDate = new Map<string, CoachAthleteTrainingDTO[]>();

    for (const training of this.trainings) {
      const date = training.scheduled_for;
      if (!date || !date.startsWith(this.calendarMonth)) continue;
      byDate.set(date, [...(byDate.get(date) ?? []), training]);
    }

    return Array.from(byDate.entries()).map(([date, items]) => {
      const completed = items.filter((item) => item.status === 'completed').length;
      const status: ActivityCalendarDayStatus = completed === items.length
        ? 'completed'
        : items.some((item) => item.status === 'scheduled' || item.status === 'in_progress')
          ? 'scheduled'
          : 'missed';

      return {
        date,
        status,
        assigned_count: items.length,
        assigned_completed_count: completed,
        assigned_in_progress_count: items.filter((item) => item.status === 'in_progress').length,
        free_completed_count: 0,
        has_activity: true,
      };
    });
  }

  changeCalendarMonth(month: string): void {
    this.calendarMonth = month;
    this.selectedCalendarDate = null;
  }

  selectCalendarDay(day: ActivityCalendarDayDTO): void {
    this.selectedCalendarDate = day.date;
  }

  selectedCalendarTrainings(): CoachAthleteTrainingDTO[] {
    if (!this.selectedCalendarDate) return [];
    return this.trainings.filter((training) => training.scheduled_for === this.selectedCalendarDate);
  }

  async openTrainingRecords(item: CoachAthleteTrainingDTO): Promise<void> {
    this.selectedTraining = item;
    this.selectedRecords = null;
    this.recordsLoading = true;

    try {
      this.selectedRecords = await this.athletesApi.trainingRecords(this.athleteId, item.assignment_id);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los registros.', 'danger');
      this.closeTrainingRecords();
    } finally {
      this.recordsLoading = false;
    }
  }

  closeTrainingRecords(): void {
    this.selectedTraining = null;
    this.selectedRecords = null;
  }

  setStatusLabel(status: string | null): string {
    if (status === 'completed') return 'Completada';
    if (status === 'failed') return 'Falló';
    if (status === 'skipped') return 'Omitida';
    return 'Sin registro';
  }

  trainingTitle(item: CoachAthleteTrainingDTO): string {
    return item.training?.title || 'Entrenamiento sin titulo';
  }

  trainingMeta(item: CoachAthleteTrainingDTO): string {
    return `${item.training?.sections_count ?? 0} secciones - ${item.scheduled_for || 'Sin fecha'}`;
  }

  private currentMonth(): string {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  }

  private async toast(message: string, color: 'danger' | 'medium' = 'medium'): Promise<void> {
    const toast = await this.toastCtrl.create({ message, color, duration: 2200, position: 'top' });
    await toast.present();
  }
}
