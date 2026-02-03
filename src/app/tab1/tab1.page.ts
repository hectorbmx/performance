import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router'; // Importar Router
import {  } from '@angular/core';
import {
  IonContent,
  IonHeader,
  IonTitle,
  IonToolbar,
  IonList,
  IonItem,IonModal,
  IonLabel,
  IonSpinner,IonButtons,
  IonRefresher,IonButton,
  IonRefresherContent,
  IonIcon,IonText,
} from '@ionic/angular/standalone';
import { computed } from '@angular/core';
import { addIcons } from 'ionicons';
import {
  notificationsOutline,
  timeOutline,
  barbellOutline,
  play, walkOutline, flameOutline, flashOutline, calendarOutline } from 'ionicons/icons';

import { TrainingApiService, TrainingFeedItemDTO } from '../services/training-api.service';
import { ApiService } from '../services/api.service';
import { AuthService } from '../services/auth.service';
import type { AppNotificationDTO } from 'src/app/services/auth.service'; // ajusta ruta si aplica

/* =========================
   Tipos locales
========================= */
type CalendarDay = {
  date: string;
  dow: string;
  dom: string;
  isToday: boolean;
};

@Component({
  selector: 'app-tab1',
  standalone: true,
  imports: [
    CommonModule,IonText,
    IonContent,IonButton,
    IonHeader,IonButtons,
    IonTitle,
    IonToolbar,
    IonList,IonModal,
    IonItem,
    IonLabel,
    IonSpinner,
    IonRefresher,
    IonRefresherContent,
    IonIcon,
  ],
  templateUrl: 'tab1.page.html',
  styleUrls: ['tab1.page.scss'],
})
export class Tab1Page {
  isNotifOpen = false;
  notifCount = computed(() => this.auth.notifications().length);
  notifications = computed(() => this.auth.notifications());

  loading = false;
  errorMsg: string | null = null;
  fallbackCover = 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=80';

  items: TrainingFeedItemDTO[] = [];
  today: TrainingFeedItemDTO | null = null;
  upcoming: TrainingFeedItemDTO[] = [];
  mode: 'assigned' | 'free' = 'assigned';
  sessionId: number | null = null;
  days: CalendarDay[] = [];
  selectedDate: string | null = null;

  clientName = '';

  healthToday = {
    steps: 0,
    kcal: 0,
    activeMin: 0,
  };
goals = {
  steps: 10000,
  kcal: 600,
  activeMin: 45,
};

readonly CIRC = 2 * Math.PI * 40; // r=40

healthLoading = false;

  constructor(
    private trainingApi: TrainingApiService,
    private api: ApiService,
    private auth: AuthService,
    private router: Router, // Inyectar Router
  ) {
    addIcons({notificationsOutline,timeOutline,play,barbellOutline,walkOutline,flameOutline,flashOutline,calendarOutline,});

    this.buildDays();
  }
  openNotifications() {
    this.isNotifOpen = true;
  }
    iconFor(type: AppNotificationDTO['type']) {
    switch (type) {
      case 'danger': return 'alert-circle';
      case 'warning': return 'warning';
      default: return 'information-circle';
    }
  }
    colorFor(type: AppNotificationDTO['type']) {
    switch (type) {
      case 'danger': return 'danger';
      case 'warning': return 'warning';
      default: return 'primary';
    }
  }
  async ionViewWillEnter() {
    // 1) carga rápida desde storage
        await this.testHealthToday();
    await this.auth.hydrateFromStorage();
    this.clientName = this.auth.getClientDisplayName();

    // 2) refrescar datos "fuente de verdad"
    try {
      await this.auth.me();
      this.clientName = this.auth.getClientDisplayName();
    } catch {
      // aquí puedes decidir: ignorar o forzar logout/redirect
    }

    await this.load();
  }
  private async testHealthToday() {
  console.log('🧪 Health test: START');

  try {
    const availability = await Health.isAvailable();
    console.log('🧪 Health isAvailable ->', availability);

    if (!availability.available) {
      console.log('🧪 Health NOT available: STOP');
      return;
    }

    console.log('🧪 Requesting authorization...');
    const authRes = await Health.requestAuthorization({
      read: ['steps', 'calories'],
      write: [],
    });
    console.log('🧪 Authorization result ->', authRes);

    const startDate = this.startOfTodayISO();
    const endDate = this.endOfTodayISO();
    console.log('🧪 Range ->', { startDate, endDate });

    const stepsAgg = await Health.queryAggregated({
      dataType: 'steps',
      startDate,
      endDate,
      bucket: 'day',
      aggregation: 'sum',
    });
    console.log('🧪 Steps aggregated ->', stepsAgg);

    const caloriesAgg = await Health.queryAggregated({
      dataType: 'calories',
      startDate,
      endDate,
      bucket: 'day',
      aggregation: 'sum',
    });
    console.log('🧪 Calories aggregated ->', caloriesAgg);

    const stepsToday = stepsAgg.samples?.[0]?.value ?? 0;
    const caloriesToday = caloriesAgg.samples?.[0]?.value ?? 0;

    console.log('✅ Health TODAY -> steps:', stepsToday);
    console.log('✅ Health TODAY -> kcal:', caloriesToday);

    this.healthToday.steps = Math.round(stepsToday);
    this.healthToday.kcal = Math.round(caloriesToday);

    // Stage 1 “ACTIVE”: como hoy no estamos leyendo appleExerciseTime (aún),
    // lo dejamos en 0 por ahora o lo calculamos con workouts cuando lo activemos.
    this.healthToday.activeMin = 0;

  } catch (err) {
    console.error('❌ Health test ERROR:', err);
  } finally {
    console.log('🧪 Health test: END');
  }
}

 private startOfTodayISO(): string {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d.toISOString();
  }

  private endOfTodayISO(): string {
    const d = new Date();
    d.setHours(23, 59, 59, 999);
    return d.toISOString();
  }

  private buildDays() {
    const now = new Date();
    const start = new Date(now);
    start.setDate(now.getDate() - 2);

    const list: CalendarDay[] = [];

    for (let i = 0; i < 7; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);

      const yyyy = d.getFullYear();
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const dd = String(d.getDate()).padStart(2, '0');

      const iso = `${yyyy}-${mm}-${dd}`;
      const isToday =
        d.getDate() === now.getDate() &&
        d.getMonth() === now.getMonth() &&
        d.getFullYear() === now.getFullYear();

      list.push({
        date: iso,
        dow: d.toLocaleDateString('en-US', { weekday: 'short' }),
        dom: String(d.getDate()),
        isToday,
      });
    }

    this.days = list;
    this.selectedDate = list.find(d => d.isToday)?.date ?? null;
  }

  selectDay(date: string) {
    this.selectedDate = date;
    this.computeTodayUpcoming();
  }

private computeTodayUpcoming() {
  const sorted = [...this.items].sort((a, b) => {
    const ad = a.scheduled_for ?? '9999-12-31';
    const bd = b.scheduled_for ?? '9999-12-31';
    return ad.localeCompare(bd);
  });

  const baseDate = this.selectedDate; // YYYY-MM-DD

  // 1) TODAY: SOLO match exacto del día seleccionado
  this.today = baseDate
    ? (sorted.find(x => x.scheduled_for === baseDate) ?? null)
    : null;

  // 2) UPCOMING: SOLO días futuros respecto a la fecha seleccionada
  // (si baseDate no existe, upcoming vacío)
  this.upcoming = baseDate
    ? sorted
        .filter(x => !!x.scheduled_for && x.scheduled_for > baseDate)
        .slice(0, 2)
    : [];
}



  async load(event?: any) {
  this.loading = true;
  this.errorMsg = null;

  try {
    const res = await this.trainingApi.index();
    this.items = res.data ?? [];

    this.computeTodayUpcoming();

    console.log('selectedDate:', this.selectedDate);
    console.log('first dates:', this.items.slice(0,5).map(i => i.scheduled_for));
    console.log('today found:', this.today);

  } catch (e: any) {
    this.errorMsg = e?.message ?? 'Error cargando entrenamientos';
  } finally {
    this.loading = false;
    if (event?.target) event.target.complete();
  }
}


async startToday() {
  const assignmentId = this.today?.assignment_id ?? null;
  const sessionId = this.today?.training_session?.id ?? null;

  if (assignmentId) {
    await this.router.navigate(['/training-details', assignmentId]);
    return;
  }

  if (sessionId) {
    await this.router.navigate(['/training-details/free', sessionId]);
    return;
  }

  console.warn('No hay assignment_id ni training_session.id disponible');
}


  async goToWorkout(item: TrainingFeedItemDTO) {
  const assignmentId = item?.assignment_id ?? null;
  const sessionId = item?.training_session?.id ?? null;

  if (assignmentId) {
    await this.router.navigate(['/training-details', assignmentId]);
    return;
  }

  if (sessionId) {
    await this.router.navigate(['/training-details/free', sessionId]);
    return;
  }

  console.warn('No hay assignment_id ni training_session.id disponible');
}

  onImgError(ev: Event) {
  (ev.target as HTMLImageElement).src = this.fallbackCover;
}
progressPct(value: number, goal: number) {
  if (!goal) return 0;
  return Math.max(0, Math.min(1, value / goal));
}

dashOffset(value: number, goal: number) {
  const pct = this.progressPct(value, goal);
  return this.CIRC * (1 - pct);
}


}

