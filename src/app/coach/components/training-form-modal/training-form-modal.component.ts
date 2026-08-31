import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output, QueryList, SimpleChanges, ViewChildren } from '@angular/core';
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
  IonModal,
  IonSelect,
  IonSelectOption,
  IonTextarea,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  addCircleOutline,
  barbellOutline,
  closeOutline,
  micOutline,
  peopleOutline,
  saveOutline,
  trashOutline,
} from 'ionicons/icons';
import {
  CoachTrainingDTO,
  CoachTrainingMetaDTO,
  CoachTrainingPayload,
  CoachTrainingSectionPayload,
  CoachTrainingsService,
} from 'src/app/services/coach-trainings.service';

@Component({
  selector: 'app-training-form-modal',
  standalone: true,
  templateUrl: './training-form-modal.component.html',
  styleUrls: ['./training-form-modal.component.scss'],
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
    IonModal,
    IonSelect,
    IonSelectOption,
    IonTextarea,
    IonTitle,
    IonToolbar,
  ],
})
export class TrainingFormModalComponent {
  @Input() isOpen = false;
  @Input() presetClientId: number | null = null;
  @Input() lockClientAssignment = false;
  @Output() closed = new EventEmitter<void>();
  @Output() created = new EventEmitter<CoachTrainingDTO>();
  @ViewChildren('trainingNotes') trainingNotes!: QueryList<IonTextarea>;
  @ViewChildren('sectionDescription') sectionDescriptions!: QueryList<IonTextarea>;

  meta: CoachTrainingMetaDTO | null = null;
  saving = false;
  form: CoachTrainingPayload = this.emptyForm();

  constructor(
    private trainingsApi: CoachTrainingsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addCircleOutline,
      barbellOutline,
      closeOutline,
      micOutline,
      peopleOutline,
      saveOutline,
      trashOutline,
    });
  }

  async ngOnChanges(changes: SimpleChanges) {
    if (changes['isOpen']?.currentValue) {
      this.form = this.emptyForm();
      this.applyAssignmentPreset();
      if (!this.meta) {
        await this.loadMeta();
      }
    }
  }

  close() {
    this.closed.emit();
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

  resultTypeLabel(value: string): string {
    if (value === 'none') return 'Sin resultados';
    return value;
  }

  async focusSectionDescription(index: number) {
    const textarea = this.sectionDescriptions?.get(index);
    await textarea?.setFocus();
  }

  async focusTrainingNotes() {
    const textarea = this.trainingNotes?.first;
    await textarea?.setFocus();
  }

  async createTraining() {
    const error = this.validateForm();
    if (error) {
      await this.toast(error, 'warning');
      return;
    }

    this.saving = true;
    try {
      const training = await this.trainingsApi.store(this.form);
      this.created.emit(training);
      await this.toast('Entrenamiento creado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo crear el entrenamiento.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  private async loadMeta() {
    try {
      this.meta = await this.trainingsApi.meta();
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar catalogos.', 'danger');
    }
  }

  shouldShowAssignmentSelectors(): boolean {
    return this.form.visibility === 'assigned' && !this.lockClientAssignment;
  }

  assignmentLabel(): string {
    if (!this.lockClientAssignment || !this.presetClientId) return 'Selecciona atletas o grupos para asignar.';

    const client = this.meta?.clients.find((item) => item.id === this.presetClientId);
    return client ? `Se asignara a ${client.label}.` : 'Se asignara al atleta seleccionado.';
  }

  private applyAssignmentPreset() {
    if (!this.presetClientId) return;

    this.form.visibility = 'assigned';
    this.form.assigned_client_ids = [this.presetClientId];
    this.form.assigned_group_ids = [];
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
