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
  IonItem,
  IonLabel,
  IonSpinner,
  IonRefresher,
  IonRefresherContent,
  IonIcon,
} from '@ionic/angular/standalone';

import { addIcons } from 'ionicons';
import {
  notificationsOutline,
  timeOutline,
  barbellOutline,
  play, walkOutline, flameOutline, flashOutline, calendarOutline } from 'ionicons/icons';

import { TrainingApiService, TrainingFeedItemDTO } from '../services/training-api.service';
import { ApiService } from '../services/api.service';
import { AuthService } from '../services/auth.service';

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
    CommonModule,
    IonContent,
    IonHeader,
    IonTitle,
    IonToolbar,
    IonList,
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

  constructor(
    private trainingApi: TrainingApiService,
    private api: ApiService,
    private auth: AuthService,
    private router: Router, // Inyectar Router
  ) {
    addIcons({notificationsOutline,timeOutline,play,barbellOutline,walkOutline,flameOutline,flashOutline,calendarOutline,});

    this.buildDays();
  }


  async ionViewWillEnter() {
    // 1) carga rápida desde storage
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

  // Método actualizado para navegar a training-details
  // async startToday() {
  //   if (!this.today?.assignment_id) {
  //     console.warn('No hay assignment_id disponible');
  //     return;
  //   }

  //   console.log('Navegando a entrenamiento:', this.today.assignment_id);
    
  //   await this.router.navigate(['/training-details', this.today.assignment_id]);
  // }
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

  // Método adicional para navegar desde upcoming workouts
  // async goToWorkout(assignmentId: number) {
  //   if (!assignmentId) return;
  //   await this.router.navigate(['/training-details', assignmentId]);
  // }
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

}