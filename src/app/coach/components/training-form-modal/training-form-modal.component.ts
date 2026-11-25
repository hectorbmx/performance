import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, NgZone, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Capacitor } from '@capacitor/core';
import { SpeechRecognition } from '@capgo/capacitor-speech-recognition';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonFooter,
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
  cameraOutline,
  closeOutline,
  micOutline,
  peopleOutline,
  saveOutline,
  stopCircleOutline,
  trashOutline,
  videocamOutline,
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
import { WORKOUT_COVERS, WorkoutCover } from 'src/app/workout-covers';

interface BrowserSpeechRecognition extends EventTarget {
  lang: string;
  start(): void;
  stop(): void;
  abort(): void;
}

type DictationTarget = 'notes' | CoachTrainingSectionPayload;

interface DictationSession {
  target: DictationTarget;
  stop?: () => void | Promise<void>;
  cancel?: () => void | Promise<void>;
}

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
    IonFooter,
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
  dictation: DictationSession | null = null;

  meta: CoachTrainingMetaDTO | null = null;
  videoPicker: { section: CoachTrainingSectionPayload; url: string; file: File | null; error: string | null } | null = null;
  libraryPicker: { section: CoachTrainingSectionPayload; selected: Set<number> } | null = null;
  libraryVideos: CoachLibraryVideoDTO[] = [];
  librarySearch = '';
  libraryLoading = false;
  libraryError = false;
  libraryHasMore = false;
  private libraryPage = 1;
  private libraryRequest = 0;
  assignmentPickerOpen = false;
  assignmentSearch = '';
  saving = false;
  readonly workoutCovers = WORKOUT_COVERS;
  selectedCover: WorkoutCover | null = null;
  form: CoachTrainingPayload = this.emptyForm();

  get modalTitle(): string {
    return this.editMode ? 'Editar entrenamiento' : 'Nuevo entrenamiento';
  }

  constructor(
    private trainingsApi: CoachTrainingsService,
    private libraryApi: CoachLibraryService,
    private toastCtrl: ToastController,
    private zone: NgZone,
  ) {
    addIcons({
      addCircleOutline,
      barbellOutline,
      cameraOutline,
      closeOutline,
      micOutline,
      peopleOutline,
      saveOutline,
      stopCircleOutline,
      trashOutline,
      videocamOutline,
    });
  }

  async ngOnChanges(changes: SimpleChanges) {
    if (changes['isOpen']) this.cancelDictation();
    if (changes['isOpen']?.currentValue) {
      this.form = this.initialTraining ? this.copyTraining(this.initialTraining) : this.emptyForm();
      this.selectedCover = null;
      this.applyAssignmentPreset();
      if (!this.meta) {
        await this.loadMeta();
      }
    }
  }

  close() {
    this.cancelDictation();
    this.videoPicker = null;
    this.closeLibraryPicker();
    this.closed.emit();
  }

  ngOnDestroy(): void {
    this.cancelDictation();
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
    if (this.dictation?.target === this.form.sections[index]) this.cancelDictation();
    this.form.sections = this.form.sections.filter((_, i) => i !== index);
  }

  openVideoPicker(section: CoachTrainingSectionPayload): void {
    this.videoPicker = { section, url: section.video_url ?? '', file: section.video_file ?? null, error: null };
  }

  setSectionVideoFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !this.videoPicker) return;

    this.videoPicker.error = this.sectionVideoError(file);
    if (!this.videoPicker.error) this.videoPicker.file = file;
  }

  applyVideoPicker(): void {
    if (!this.videoPicker || this.videoPicker.error) return;
    const { section, url, file } = this.videoPicker;
    section.video_url = url;
    section.video_file = file;
    this.videoPicker = null;
  }

  openLibraryPicker(section: CoachTrainingSectionPayload): void {
    this.libraryPicker = { section, selected: new Set(section.library_video_ids ?? []) };
    void this.searchLibrary('');
  }

  closeLibraryPicker(): void {
    this.libraryPicker = null;
    this.libraryRequest++;
  }

  searchLibrary(query: string): Promise<void> {
    this.librarySearch = query;
    this.libraryVideos = [];
    this.libraryHasMore = false;
    this.libraryPage = 0;
    return this.loadLibraryPage();
  }

  async loadLibraryPage(): Promise<void> {
    if (!this.libraryPicker) return;
    const request = ++this.libraryRequest;
    const page = this.libraryPage + 1;
    this.libraryLoading = true;
    this.libraryError = false;
    try {
      const result = await this.libraryApi.page({ q: this.librarySearch.trim(), page });
      if (request !== this.libraryRequest) return;
      this.libraryVideos = [...this.libraryVideos, ...result.items];
      this.libraryHasMore = result.hasMore;
      this.libraryPage = page;
    } catch {
      if (request === this.libraryRequest) this.libraryError = true;
    } finally {
      if (request === this.libraryRequest) this.libraryLoading = false;
    }
  }

  toggleLibraryVideo(id: number, checked: boolean): void {
    if (checked) this.libraryPicker?.selected.add(id);
    else this.libraryPicker?.selected.delete(id);
  }

  applyLibraryPicker(): void {
    if (!this.libraryPicker) return;
    this.libraryPicker.section.library_video_ids = [...this.libraryPicker.selected];
    this.closeLibraryPicker();
  }

  setCoverImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.form.cover_image = input.files?.[0] ?? null;
    if (this.form.cover_image) this.selectedCover = null;
  }

  selectCover(cover: WorkoutCover | null, input: HTMLInputElement): void {
    this.selectedCover = cover;
    this.form.cover_image = null;
    input.value = '';
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

  async toggleDictation(target: DictationTarget): Promise<void> {
    if (this.saving) return;
    if (this.dictation) {
      if (this.dictation.target === target) {
        const stop = this.dictation.stop;
        this.dictation.stop = undefined;
        try {
          await stop?.();
        } catch {
          this.cancelDictation();
          await this.toast('No se pudo finalizar el dictado.', 'warning');
        }
      }
      return;
    }

    const session: DictationSession = { target };
    this.dictation = session;
    try {
      const transcript = Capacitor.isNativePlatform()
        ? await this.recognizeNative(session)
        : await this.recognizeBrowser(session);
      if (this.dictation !== session) return;
      const text = transcript.trim();
      if (!text) {
        await this.toast('No se detectó voz. Intenta de nuevo.', 'warning');
        return;
      }
      if (target === 'notes') {
        this.form.notes = [this.form.notes?.trimEnd(), text].filter(Boolean).join(' ');
      } else {
        target.description = [target.description?.trimEnd(), text].filter(Boolean).join(' ');
      }
    } catch (error) {
      if (this.dictation === session) {
        const message = error instanceof Error ? error.message : 'No se pudo completar el dictado. Intenta de nuevo.';
        await this.toast(message, 'warning');
      }
    } finally {
      if (this.dictation === session) this.dictation = null;
    }
  }

  private async recognizeNative(session: DictationSession): Promise<string> {
    const { available } = await SpeechRecognition.available();
    if (this.dictation !== session) return '';
    if (!available) throw new Error('El reconocimiento de voz no está disponible en este dispositivo.');

    const permission = await SpeechRecognition.requestPermissions();
    if (this.dictation !== session) return '';
    if (permission.speechRecognition !== 'granted') {
      throw new Error('Permite el acceso al micrófono y al reconocimiento de voz en los ajustes de la app.');
    }

    session.stop = () => SpeechRecognition.stop();
    session.cancel = () => SpeechRecognition.forceStop();
    try {
      const { matches } = await SpeechRecognition.start({ language: 'es-MX', maxResults: 1 });
      return matches?.[0] ?? '';
    } catch {
      throw new Error('No se pudo reconocer la voz. Intenta de nuevo.');
    }
  }

  private recognizeBrowser(session: DictationSession): Promise<string> {
    const browser = window as Window & {
      SpeechRecognition?: new () => BrowserSpeechRecognition;
      webkitSpeechRecognition?: new () => BrowserSpeechRecognition;
    };
    const Recognition = browser.SpeechRecognition ?? browser.webkitSpeechRecognition;
    if (!Recognition) {
      return Promise.reject(new Error('Este navegador no admite el dictado desde la app.'));
    }

    return new Promise((resolve, reject) => {
      const recognition = new Recognition();
      recognition.lang = 'es-MX';
      let transcript = '';
      recognition.addEventListener('result', (event) => {
        const { results } = event as Event & { results: SpeechRecognitionResultList };
        transcript = Array.from(results, (result) => result[0].transcript).join(' ');
      });
      recognition.addEventListener('end', () => this.zone.run(() => resolve(transcript)));
      recognition.addEventListener('error', (event) => {
        const { error } = event as Event & { error: string };
        this.zone.run(() => {
          if (error === 'no-speech') {
            resolve('');
            return;
          }
          reject(new Error(error === 'not-allowed' || error === 'service-not-allowed'
            ? 'Permite el acceso al micrófono para usar el dictado.'
            : 'No se pudo reconocer la voz. Intenta de nuevo.'));
        });
      });
      session.stop = () => recognition.stop();
      session.cancel = () => recognition.abort();
      recognition.start();
    });
  }

  cancelDictation(): void {
    const session = this.dictation;
    this.dictation = null;
    void Promise.resolve().then(() => session?.cancel?.()).catch(() => undefined);
  }

  async createTraining() {
    if (this.dictation || this.saving) return;
    const error = this.validateForm();
    if (error) {
      await this.toast(error, 'warning');
      return;
    }

    this.saving = true;
    try {
      const payload = { ...this.form };
      if (!payload.cover_image && (this.selectedCover || !this.editMode)) {
        const cover = this.selectedCover ?? WORKOUT_COVERS[Math.floor(Math.random() * WORKOUT_COVERS.length)];
        const response = await fetch(cover.src);
        if (!response.ok) throw new Error('No se pudo cargar la portada del entrenamiento.');

        const blob = await response.blob();
        const filename = cover.src.slice(cover.src.lastIndexOf('/') + 1);
        payload.cover_image = new File([blob], filename, { type: 'image/jpeg' });
      }

      const training = this.editMode && this.form.id
        ? await this.trainingsApi.update(this.form.id, payload)
        : await this.trainingsApi.store(payload);
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
      const error = section.video_file && this.sectionVideoError(section.video_file);
      if (error) return error;
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

  private sectionVideoError(file: File): string | null {
    const compatibleType = file.type === 'video/mp4' || file.type === 'video/quicktime'
      || (!file.type && /\.(mp4|mov)$/i.test(file.name));
    if (!compatibleType) return 'Selecciona un video MP4 o MOV.';
    if (file.size > 10 * 1024 * 1024) return 'El video debe pesar como máximo 10 MB.';
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
