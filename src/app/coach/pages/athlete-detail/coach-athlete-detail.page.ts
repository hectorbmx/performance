import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonChip,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonModal,
  IonSpinner,
  IonTitle,
  IonToggle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  barbellOutline,
  cardOutline,
  closeOutline,
  createOutline,
  peopleOutline,
  refreshOutline,
  saveOutline,
  settingsOutline,
} from 'ionicons/icons';
import {
  CoachAthleteDTO,
  CoachAthletePayload,
  CoachAthleteTrainingDTO,
  CoachAthletesService,
} from 'src/app/services/coach-athletes.service';

@Component({
  selector: 'app-coach-athlete-detail',
  standalone: true,
  templateUrl: './coach-athlete-detail.page.html',
  styleUrls: ['./coach-athlete-detail.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonBackButton,
    IonButton,
    IonButtons,
    IonChip,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonModal,
    IonSpinner,
    IonTitle,
    IonToggle,
    IonToolbar,
  ],
})
export class CoachAthleteDetailPage {
  athlete: CoachAthleteDTO | null = null;
  trainings: CoachAthleteTrainingDTO[] = [];
  loading = false;
  loadingTrainings = false;
  saving = false;
  isEditOpen = false;

  form: CoachAthletePayload = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    is_active: true,
  };

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private athletesApi: CoachAthletesService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      barbellOutline,
      cardOutline,
      closeOutline,
      createOutline,
      peopleOutline,
      refreshOutline,
      saveOutline,
      settingsOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!id) {
      await this.router.navigate(['/coach/athletes']);
      return;
    }

    this.loading = true;
    try {
      this.athlete = await this.athletesApi.show(id);
      this.form = {
        first_name: this.athlete.first_name ?? '',
        last_name: this.athlete.last_name ?? '',
        email: this.athlete.email ?? '',
        phone: this.athlete.phone ?? '',
        is_active: this.athlete.is_active,
      };
      await this.loadTrainings(id);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo cargar el atleta.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async loadTrainings(id = this.athlete?.id) {
    if (!id) return;

    this.loadingTrainings = true;
    try {
      this.trainings = await this.athletesApi.trainings(id);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los entrenamientos.', 'danger');
    } finally {
      this.loadingTrainings = false;
    }
  }

  openEdit() {
    this.isEditOpen = true;
  }

  async save() {
    if (!this.athlete || !this.form.first_name.trim()) {
      await this.toast('El nombre es requerido.', 'warning');
      return;
    }

    this.saving = true;
    try {
      this.athlete = await this.athletesApi.update(this.athlete.id, this.form);
      this.isEditOpen = false;
      await this.toast('Atleta actualizado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo guardar el atleta.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  initials(): string {
    return (this.athlete?.full_name || this.form.first_name || 'A').trim().charAt(0).toUpperCase();
  }

  statusLabel(): string {
    if (!this.athlete) return 'Cargando';
    if (!this.athlete.is_active) return 'Inactivo';
    return 'Activo';
  }

  planName(): string {
    return this.athlete?.active_membership?.plan_name || 'Sin plan asignado';
  }

  planStatus(): string {
    const membership = this.athlete?.active_membership;
    if (!membership) return 'Sin membresia activa';
    return `${this.billingLabel(membership.billing_status)} · vence ${membership.ends_at || 'sin fecha'}`;
  }

  appAccessLabel(): string {
    if (!this.athlete?.email) return 'Sin correo para acceso app';
    return this.athlete.app_account?.activated_at ? 'Cuenta app activada' : 'Cuenta app pendiente de activacion';
  }

  trainingTitle(item: CoachAthleteTrainingDTO): string {
    return item.training?.title || 'Entrenamiento sin titulo';
  }

  trainingMeta(item: CoachAthleteTrainingDTO): string {
    const sections = item.training?.sections_count ?? 0;
    const date = item.scheduled_for || 'Sin fecha';
    return `${sections} secciones · ${date}`;
  }

  trainingStatusClass(item: CoachAthleteTrainingDTO): string {
    if (item.status === 'completed') return 'done';
    if (item.status === 'in_progress') return 'progress';
    if (item.status === 'cancelled' || item.status === 'skipped') return 'muted';
    return 'scheduled';
  }

  private billingLabel(status: string): string {
    if (status === 'paid') return 'Pagado';
    if (status === 'unpaid') return 'Pendiente de pago';
    if (status === 'past_due') return 'Vencido';
    if (status === 'cancelled') return 'Cancelado';
    return status;
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 2000,
      position: 'top',
    });
    await toast.present();
  }
}
