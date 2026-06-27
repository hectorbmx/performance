import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonMenuButton,
  IonModal,
  IonSegment,
  IonSegmentButton,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  addCircleOutline,
  barbellOutline,
  calendarOutline,
  closeOutline,
  peopleOutline,
  personCircleOutline,
  refreshOutline,
  saveOutline,
  trashOutline,
} from 'ionicons/icons';
import {
  CoachTrainingDTO,
  CoachTrainingMetaDTO,
  CoachTrainingPayload,
  CoachTrainingSectionPayload,
  CoachTrainingsService,
  TrainingVisibility,
} from 'src/app/services/coach-trainings.service';

type TrainingFilter = 'week' | 'free' | 'assigned';

@Component({
  selector: 'app-coach-trainings',
  standalone: true,
  templateUrl: './coach-trainings.page.html',
  styleUrls: ['./coach-trainings.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonMenuButton,
    IonModal,
    IonSegment,
    IonSegmentButton,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTextarea,
    IonTitle,
    IonToolbar,
  ],
})
export class CoachTrainingsPage {
  trainings: CoachTrainingDTO[] = [];
  meta: CoachTrainingMetaDTO | null = null;
  loading = false;
  saving = false;
  isCreateOpen = false;
  filter: TrainingFilter = 'week';

  form: CoachTrainingPayload = this.emptyForm();

  constructor(
    private trainingsApi: CoachTrainingsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addCircleOutline,
      barbellOutline,
      calendarOutline,
      closeOutline,
      peopleOutline,
      personCircleOutline,
      refreshOutline,
      saveOutline,
      trashOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
    await this.loadMeta();
  }

  async load() {
    this.loading = true;
    try {
      const params = this.filter === 'free' || this.filter === 'assigned'
        ? { visibility: this.filter }
        : undefined;
      this.trainings = await this.trainingsApi.index(params);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los entrenamientos.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async loadMeta() {
    try {
      this.meta = await this.trainingsApi.meta();
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar catalogos.', 'danger');
    }
  }

  async changeFilter(event: CustomEvent) {
    this.filter = event.detail.value as TrainingFilter;
    await this.load();
  }

  async openCreate() {
    if (!this.meta) {
      await this.loadMeta();
    }
    this.form = this.emptyForm();
    this.isCreateOpen = true;
  }

  addSection() {
    this.form.sections = [...this.form.sections, this.emptySection()];
  }

  removeSection(index: number) {
    if (this.form.sections.length <= 1) return;
    this.form.sections = this.form.sections.filter((_, i) => i !== index);
  }

  unitOptions(section: CoachTrainingSectionPayload) {
    if (!this.meta || !section.result_type || section.result_type === 'none') return [];
    return this.meta.units.filter((unit) => unit.result_type === section.result_type);
  }

  async createTraining() {
    const error = this.validateForm();
    if (error) {
      await this.toast(error, 'warning');
      return;
    }

    this.saving = true;
    try {
      const created = await this.trainingsApi.store(this.form);
      this.trainings = [created, ...this.trainings];
      this.isCreateOpen = false;
      await this.toast('Entrenamiento creado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo crear el entrenamiento.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  visibilityLabel(training: CoachTrainingDTO): string {
    return training.visibility === 'free' ? 'Libre' : 'Asignado';
  }

  trainingDate(training: CoachTrainingDTO): string {
    return training.scheduled_at || 'Sin fecha';
  }

  assignmentsLabel(training: CoachTrainingDTO): string {
    return `${training.assignments_count || 0} atletas`;
  }

  resultTypeLabel(value: string): string {
    if (value === 'none') return 'Sin resultados';
    return value;
  }

  private validateForm(): string | null {
    if (!this.form.title.trim()) return 'El titulo es requerido.';
    if (!this.form.scheduled_at) return 'La fecha es requerida.';
    if (this.form.visibility === 'assigned') {
      const hasClients = (this.form.assigned_client_ids?.length ?? 0) > 0;
      const hasGroups = (this.form.assigned_group_ids?.length ?? 0) > 0;
      if (!hasClients && !hasGroups) return 'Asigna al menos un atleta o grupo.';
    }
    if (this.form.sections.some((section) => !section.name.trim())) {
      return 'Todas las secciones necesitan nombre.';
    }
    return null;
  }

  private emptyForm(): CoachTrainingPayload {
    return {
      title: '',
      scheduled_at: this.today(),
      duration_minutes: 60,
      level: 'beginner',
      goal: 'mixed',
      type: 'fitness',
      training_goal_catalog_id: null,
      training_type_catalog_id: null,
      visibility: 'assigned',
      notes: '',
      tag_color: '#2563eb',
      assigned_client_ids: [],
      assigned_group_ids: [],
      sections: [this.emptySection()],
    };
  }

  private emptySection(): CoachTrainingSectionPayload {
    return {
      name: '',
      description: '',
      video_url: '',
      result_type: 'none',
      unit_id: null,
    };
  }

  private today(): string {
    return new Date().toISOString().slice(0, 10);
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
