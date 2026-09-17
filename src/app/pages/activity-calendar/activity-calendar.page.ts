import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
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
  calendar: ActivityCalendarResponse | null = null;
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
      await this.load();
    }
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
