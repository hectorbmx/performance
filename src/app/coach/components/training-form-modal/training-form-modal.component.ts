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
  closeOutline,
  micOutline,
  peopleOutline,
  saveOutline,
  trashOutline,
} from 'ionicons/icons';
import {
  CoachTrainingDTO,
  CoachTrainingLiftingBlockPayload,
  CoachTrainingLiftingRowPayload,
  CoachTrainingMetaDTO,
  CoachTrainingPayload,
  CoachTrainingSectionPayload,
  CoachTrainingsService,
} from 'src/app/services/coach-trainings.service';
import { CoachLibraryService, CoachLibraryVideoDTO } from 'src/app/services/coach-library.service';

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
    IonSpinner,
    IonTextarea,
    IonTitle,
    IonToolbar,
  ],
})
export class TrainingFormModalComponent {
  @Input() isOpen = false;
  @Input() editMode = false;
  @Input() initialTraining: CoachTrainingPayload | null = null;
  @Input() presetClientId: number | null = null;
  @Input() lockClientAssignment = false;
  @Output() closed = new EventEmitter<void>();
  @Output() created = new EventEmitter<CoachTrainingDTO>();
  @ViewChildren('trainingNotes') trainingNotes!: QueryList<IonTextarea>;
  @ViewChildren('sectionDescription') sectionDescriptions!: QueryList<IonTextarea>;

  meta: CoachTrainingMetaDTO | null = null;
  libraryVideos: CoachLibraryVideoDTO[] = [];
  librarySearch = '';
  assignmentPickerOpen = false;
  assignmentSearch = '';
  saving = false;
  form: CoachTrainingPayload = this.emptyForm();

  get modalTitle(): string {
    return this.editMode ? 'Editar entrenamiento' : 'Nuevo entrenamiento';
  }

  constructor(
    private trainingsApi: CoachTrainingsService,
    private libraryApi: CoachLibraryService,
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
      this.form = this.initialTraining ? this.copyTraining(this.initialTraining) : this.emptyForm();
      this.applyAssignmentPreset();
      if (!this.meta) {
        await this.loadMeta();
      }
    }
  }

  close() {
    this.closed.emit();
  }

  openAssignmentPicker(): void {
    this.assignmentSearch = '';
    this.assignmentPickerOpen = true;
  }

  closeAssignmentPicker(): void {
    this.assignmentPickerOpen = false;
  }

  filteredAssignmentClients(): CoachTrainingMetaDTO['clients'] {
    const clients = this.meta?.clients ?? [];
    const query = this.assignmentSearch.trim().toLowerCase();
    if (!query) return clients;

    return clients.filter((client) =>
      `${client.label} ${client.email ?? ''}`.toLowerCase().includes(query),
    );
  }

  isClientAssigned(clientId: number): boolean {
    return this.form.assigned_client_ids?.includes(clientId) ?? false;
  }

  toggleClientAssignment(clientId: number, selected: boolean): void {
    if (clientId === this.presetClientId) return;

    const current = this.form.assigned_client_ids ?? [];
    this.form.assigned_client_ids = selected
      ? Array.from(new Set([...current, clientId]))
      : current.filter((id) => id !== clientId);
  }

  selectedAssignmentClients(): CoachTrainingMetaDTO['clients'] {
    const selectedIds = new Set(this.form.assigned_client_ids ?? []);
    return (this.meta?.clients ?? []).filter((client) => selectedIds.has(client.id));
  }

  selectedAssignmentGroups(): CoachTrainingMetaDTO['groups'] {
    const selectedIds = new Set(this.form.assigned_group_ids ?? []);
    return (this.meta?.groups ?? []).filter((group) => selectedIds.has(group.id));
  }

  removeClientAssignment(clientId: number): void {
    if (clientId === this.presetClientId) return;
    this.form.assigned_client_ids = (this.form.assigned_client_ids ?? []).filter((id) => id !== clientId);
  }

  removeGroupAssignment(groupId: number): void {
    this.form.assigned_group_ids = (this.form.assigned_group_ids ?? []).filter((id) => id !== groupId);
  }

  addSection() {
    this.form.sections = [...this.form.sections, this.emptySection()];
  }

  removeSection(index: number) {
    if (this.form.sections.length <= 1) return;
    this.form.sections = this.form.sections.filter((_, i) => i !== index);
  }

  setSectionVideoFile(section: CoachTrainingSectionPayload, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    section.video_file = file;
  }

  setCoverImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.form.cover_image = input.files?.[0] ?? null;
  }

  isLiftingEnabled(section: CoachTrainingSectionPayload): boolean {
    return Array.isArray(section.lifting_blocks);
  }

  setLiftingEnabled(section: CoachTrainingSectionPayload, enabled: boolean): void {
    section.lifting_blocks = enabled ? [this.emptyLiftingBlock()] : undefined;
  }

  addLiftingBlock(section: CoachTrainingSectionPayload): void {
    section.lifting_blocks = [...(section.lifting_blocks ?? []), this.emptyLiftingBlock()];
  }

  removeLiftingBlock(section: CoachTrainingSectionPayload, index: number): void {
    section.lifting_blocks = (section.lifting_blocks ?? []).filter((_, blockIndex) => blockIndex !== index);
  }

  addLiftingRow(block: CoachTrainingLiftingBlockPayload): void {
    block.rows = [...(block.rows ?? []), this.emptyLiftingRow()];
  }

  removeLiftingRow(block: CoachTrainingLiftingBlockPayload, index: number): void {
    block.rows = (block.rows ?? []).filter((_, rowIndex) => rowIndex !== index);
  }

  unitOptions(section: CoachTrainingSectionPayload) {
    if (!this.meta || !section.result_type || section.result_type === 'none') return [];
    return this.meta.units.filter((unit) => unit.result_type === section.result_type);
  }

  resultTypeLabel(value: string): string {
    if (value === 'none') return 'Sin resultados';
    return value;
  }

  filteredLibraryVideos(): CoachLibraryVideoDTO[] {
    const query = this.librarySearch.trim().toLowerCase();
    if (!query) return this.libraryVideos;

    return this.libraryVideos.filter((video) => video.name.toLowerCase().includes(query));
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
      const training = this.editMode && this.form.id
        ? await this.trainingsApi.update(this.form.id, this.form)
        : await this.trainingsApi.store(this.form);
      this.created.emit(training);
      await this.toast(this.editMode ? 'Entrenamiento actualizado.' : 'Entrenamiento creado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo crear el entrenamiento.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  private async loadMeta() {
    try {
      const [meta, videos] = await Promise.all([
        this.trainingsApi.meta(),
        this.libraryApi.index(),
      ]);
      this.meta = meta;
      this.libraryVideos = videos;
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
    const additionalClients = Math.max((this.form.assigned_client_ids?.length ?? 1) - 1, 0);
    if (!client) return 'Se asignara al atleta seleccionado.';
    return additionalClients > 0
      ? `Se asignara a ${client.label} y ${additionalClients} atleta(s) mas.`
      : `Se asignara a ${client.label}.`;
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

    for (const section of this.form.sections) {
      if (section.video_file && section.video_file.type !== 'video/mp4') {
        return 'Los videos de seccion deben ser archivos MP4.';
      }

      if (section.video_file && section.video_file.size > 10 * 1024 * 1024) {
        return 'Cada video de seccion debe pesar como maximo 10 MB.';
      }
    }

    if (this.form.cover_image) {
      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(this.form.cover_image.type)) {
        return 'La portada debe ser JPG, PNG o WEBP.';
      }

      if (this.form.cover_image.size > 4 * 1024 * 1024) {
        return 'La portada debe pesar como maximo 4 MB.';
      }
    }

    for (const [sectionIndex, section] of this.form.sections.entries()) {
      if (!this.isLiftingEnabled(section)) continue;

      const blocks = section.lifting_blocks ?? [];
      if (blocks.length === 0) {
        return `Agrega al menos un ejercicio en la seccion ${sectionIndex + 1}.`;
      }

      for (const [blockIndex, block] of blocks.entries()) {
        if (!block.exercise_name.trim()) {
          return `El ejercicio ${blockIndex + 1} de la seccion ${sectionIndex + 1} necesita nombre.`;
        }

        const rows = block.rows ?? [];
        if (rows.length === 0) {
          return `Agrega al menos una serie para ${block.exercise_name}.`;
        }

        for (const row of rows) {
          if (!this.isPositiveNumber(row.reps) || !this.isPositiveNumber(row.sets)) {
            return `Reps y sets deben ser mayores que cero en ${block.exercise_name}.`;
          }

          if (row.percentage !== null && row.percentage !== undefined && row.percentage !== ''
            && (!this.isNumber(row.percentage) || Number(row.percentage) < 0 || Number(row.percentage) > 100)) {
            return `El porcentaje debe estar entre 0 y 100 en ${block.exercise_name}.`;
          }

          if (row.rest_seconds !== null && row.rest_seconds !== undefined && row.rest_seconds !== ''
            && (!this.isNumber(row.rest_seconds) || Number(row.rest_seconds) < 0)) {
            return `El descanso no puede ser negativo en ${block.exercise_name}.`;
          }
        }
      }
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

  private copyTraining(training: CoachTrainingPayload): CoachTrainingPayload {
    return {
      ...training,
      assigned_client_ids: [...(training.assigned_client_ids ?? [])],
      assigned_group_ids: [...(training.assigned_group_ids ?? [])],
      sections: training.sections.map((section) => ({
        ...section,
        library_video_ids: [...(section.library_video_ids ?? [])],
        lifting_blocks: section.lifting_blocks?.map((block) => ({
          ...block,
          rows: block.rows?.map((row) => ({ ...row })),
        })),
      })),
    };
  }

  private emptySection(): CoachTrainingSectionPayload {
    return {
      name: '',
      description: '',
      video_url: '',
      library_video_ids: [],
      result_type: 'none',
      unit_id: null,
    };
  }

  private emptyLiftingBlock(): CoachTrainingLiftingBlockPayload {
    return {
      exercise_catalog_id: null,
      exercise_name: '',
      notes: '',
      rows: [this.emptyLiftingRow()],
    };
  }

  private emptyLiftingRow(): CoachTrainingLiftingRowPayload {
    return {
      percentage: null,
      reps: 1,
      sets: 1,
      rest_seconds: null,
      notes: '',
    };
  }

  private isNumber(value: number | string): boolean {
    return value !== '' && Number.isFinite(Number(value));
  }

  private isPositiveNumber(value: number | string): boolean {
    return this.isNumber(value) && Number(value) > 0;
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
