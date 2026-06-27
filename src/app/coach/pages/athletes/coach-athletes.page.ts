import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonButton,
  IonButtons,
  IonChip,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonMenuButton,
  IonModal,
  IonSearchbar,
  IonSpinner,
  IonTitle,
  IonToggle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  addOutline,
  calendarOutline,
  closeOutline,
  peopleOutline,
  refreshOutline,
  saveOutline,
  searchOutline,
} from 'ionicons/icons';
import {
  CoachAthleteDTO,
  CoachAthletePayload,
  CoachAthletesService,
} from 'src/app/services/coach-athletes.service';

@Component({
  selector: 'app-coach-athletes',
  standalone: true,
  templateUrl: './coach-athletes.page.html',
  styleUrls: ['./coach-athletes.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonButtons,
    IonChip,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonMenuButton,
    IonModal,
    IonSearchbar,
    IonSpinner,
    IonTitle,
    IonToggle,
    IonToolbar,
  ],
})
export class CoachAthletesPage {
  athletes: CoachAthleteDTO[] = [];
  loading = false;
  saving = false;
  isFormOpen = false;
  editingAthlete: CoachAthleteDTO | null = null;
  searchTerm = '';

  form: CoachAthletePayload = this.emptyForm();

  constructor(
    private athletesApi: CoachAthletesService,
    private router: Router,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addOutline,
      calendarOutline,
      closeOutline,
      peopleOutline,
      refreshOutline,
      saveOutline,
      searchOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    this.loading = true;
    try {
      this.athletes = await this.athletesApi.index({ q: this.searchTerm });
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los atletas.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async search(event: CustomEvent) {
    this.searchTerm = String(event.detail?.value ?? '');
    await this.load();
  }

  openCreate() {
    this.editingAthlete = null;
    this.form = this.emptyForm();
    this.isFormOpen = true;
  }

  openEdit(athlete: CoachAthleteDTO) {
    this.router.navigate(['/coach/athletes', athlete.id]);
  }

  async saveAthlete() {
    if (!this.form.first_name.trim()) {
      await this.toast('El nombre es requerido.', 'warning');
      return;
    }

    this.saving = true;
    try {
      if (this.editingAthlete) {
        const updated = await this.athletesApi.update(this.editingAthlete.id, this.form);
        this.athletes = this.athletes.map((item) => item.id === updated.id ? updated : item);
        await this.toast('Atleta actualizado.', 'success');
      } else {
        const result = await this.athletesApi.store(this.form);
        this.athletes = [result.athlete, ...this.athletes];
        const code = result.activation_code ? ` Codigo: ${result.activation_code}` : '';
        await this.toast(`Atleta creado.${code}`, 'success');
      }

      this.isFormOpen = false;
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo guardar el atleta.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  activeCount(): number {
    return this.athletes.filter((athlete) => athlete.is_active).length;
  }

  initials(athlete: CoachAthleteDTO): string {
    return (athlete.full_name || athlete.first_name || 'A').trim().charAt(0).toUpperCase();
  }

  statusLabel(athlete: CoachAthleteDTO): string {
    if (!athlete.is_active) return 'Inactivo';
    return 'Activo';
  }

  statusClass(athlete: CoachAthleteDTO): string {
    const status = this.statusLabel(athlete);
    if (status === 'Activo') return 'active';
    if (status === 'Inactivo') return 'inactive';
    return 'warn';
  }

  planLabel(athlete: CoachAthleteDTO): string {
    return athlete.active_membership?.plan_name || 'Sin plan';
  }

  nextLabel(athlete: CoachAthleteDTO): string {
    return athlete.active_membership?.ends_at || 'Sin asignar';
  }

  summaryLabel(athlete: CoachAthleteDTO): string {
    const plan = this.planLabel(athlete);
    const ends = athlete.active_membership?.ends_at ? ` · vence ${athlete.active_membership.ends_at}` : '';
    return `${plan}${ends}`;
  }

  private emptyForm(): CoachAthletePayload {
    return {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      is_active: true,
    };
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 2200,
      position: 'top',
    });
    await toast.present();
  }
}
