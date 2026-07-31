import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { FormsModule } from '@angular/forms';


import {
  IonContent,
  IonHeader,
  IonTitle,
  IonToolbar,
  IonButtons,
  IonBackButton,
  IonSpinner,
  IonIcon,
} from '@ionic/angular/standalone';

import { addIcons } from 'ionicons';
import {
  arrowBack,
  timeOutline,
  barbellOutline,
  playCircle,
  flashOutline,
  checkmarkOutline,
  closeOutline,
  fitnessOutline, logoYoutube } from 'ionicons/icons';

import {
  TrainingApiService,
  TrainingDetailDTO,
  TrainingLiftingRowDTO,
  TrainingLiftingSetStatusDTO,
  TrainingSectionDTO,
} from '../../services/training-api.service';

@Component({
  selector: 'app-training-details',
  templateUrl: './training-details.page.html',
  styleUrls: ['./training-details.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    IonContent,
    IonHeader,
    IonTitle,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonSpinner,
    IonIcon,
  ],
})
export class TrainingDetailsPage implements OnInit {
  assignmentId: number | null = null;
  sessionId: number | null = null;
  isFree = false;

  loading = true;
  errorMsg: string | null = null;

  expandedSectionId: number | null = null;

  editingSectionValue: string = '';
  editingSectionNotes: string = '';
  
  editingResultValue: string = '';
  editingResultNotes: string = '';


  savingResult = false;
  savingLiftingKey: string | null = null;
  data: any;
  savingSectionId: number | null = null;

  // Este es el que usa el HTML (detailed)
  detail: TrainingDetailDTO | null = null;
  fallbackCover = 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=1200&q=80';

  
  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private trainingApi: TrainingApiService,
    private sanitizer: DomSanitizer,
    
  ) {
    addIcons({timeOutline,barbellOutline,flashOutline,fitnessOutline,logoYoutube,arrowBack,playCircle,checkmarkOutline,closeOutline,});
  }

async ngOnInit() {
  const assignmentParam = this.route.snapshot.paramMap.get('assignmentId');
  const sessionParam = this.route.snapshot.paramMap.get('sessionId');

  const assignmentId = assignmentParam ? Number(assignmentParam) : null;
  const sessionId = sessionParam ? Number(sessionParam) : null;

  // 1) Assigned
  if (assignmentId && !Number.isNaN(assignmentId)) {
    this.isFree = false;
    this.assignmentId = assignmentId;
    await this.loadDetails();
    return;
  }

// 2) Free
if (sessionId && !Number.isNaN(sessionId)) {
  this.isFree = true;
  this.sessionId = sessionId;

  await this.loadFreeDetails(); // ✅ crea assignment y luego carga details por assignment
  return;
}



  this.errorMsg = 'ID de entrenamiento inválido';
  this.loading = false;
}

toggleSectionResult(section: TrainingSectionDTO) {
    // Solo expandimos si acepta resultados y no está completada
    if (!section.accepts_results ) return;

    if (this.expandedSectionId === section.id) {
      this.expandedSectionId = null;
      this.editingSectionValue = '';
      this.editingSectionNotes = '';
      return;
    }

      this.expandedSectionId = section.id;
  this.editingResultValue = section.result?.value ?? '';
  this.editingResultNotes = section.result?.notes ?? '';
  }

  cancelSectionResultEdit(ev: Event) {
    ev.stopPropagation();
    this.expandedSectionId = null;
    this.editingSectionValue = '';
    this.editingSectionNotes = '';
  }

  async loadDetails() {
    if (!this.assignmentId) return;

    this.loading = true;
    this.errorMsg = null;
    this.detail = null;

    try {
      const res = await this.trainingApi.show(this.assignmentId);

      if (!res?.ok || !res.data) {
        this.errorMsg = 'No se pudo cargar el entrenamiento';
        return;
      }

      this.detail = res.data;
    } catch (e: any) {
      this.errorMsg = e?.message ?? 'Error cargando entrenamiento';
    } finally {
      this.loading = false;
    }
  }

async loadFreeDetails() {
  if (!this.sessionId) return;

  this.loading = true;
  this.errorMsg = null;
  this.detail = null;

  try {
    const started = await this.trainingApi.startFreeSession(this.sessionId);

    if (!started?.ok || !started.data?.assignment_id) {
      this.errorMsg = 'No se pudo iniciar el entrenamiento libre';
      return;
    }

    // ✅ clave: ya tienes assignment real
    this.assignmentId = started.data.assignment_id;

    // ✅ ahora reuse del flujo normal
    await this.loadDetails();
  } catch (e: any) {
    this.errorMsg = e?.message ?? 'Error iniciando entrenamiento libre';
  } finally {
    this.loading = false;
  }
}


  // =========================
  // Helpers para el HTML
  // =========================
  trackBySectionId(_: number, s: TrainingSectionDTO) {
    return s.id;
  }

  trackByLiftingRowId(_: number, row: TrainingLiftingRowDTO) {
    return row.id;
  }

  trackBySetNumber(_: number, set: TrainingLiftingSetStatusDTO) {
    return set.set_number;
  }

  hasLifting(section: TrainingSectionDTO): boolean {
    return (section.lifting_blocks ?? []).some((block) => (block.rows ?? []).length > 0);
  }

  liftingPrescription(row: TrainingLiftingRowDTO): string {
    const intensity = row.percentage === null ? 'Sin %' : `${row.percentage}%`;
    return `${intensity} · ${row.sets} x ${row.reps}`;
  }

  restLabel(seconds: number | null): string {
    if (!seconds && seconds !== 0) return 'Sin descanso';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `${secs}s`;
  }

  liftingSetKey(rowId: number, setNumber: number): string {
    return `${rowId}:${setNumber}`;
  }

  isSectionSaving(sectionId: number): boolean {
    return this.savingSectionId === sectionId;
  }

  private applyProgress(progress: any) {
    if (!this.detail || !progress) return;

    const sectionsCompleted =
      progress.sections_completed ?? progress.sections_with_results ?? this.detail.progress.sections_completed;

    this.detail.progress = {
      sections_total: progress.sections_total ?? this.detail.progress.sections_total,
      sections_completed: sectionsCompleted,
      pct: progress.pct ?? this.detail.progress.pct,
    };
  }

  private applyAssignmentStatus(status: any) {
    if (!this.detail || !status) return;
    this.detail.status = status;
  }

  private refreshProgressFromSections() {
    if (!this.detail) return;

    const sectionsCompleted = this.detail.sections.filter((section) => section.completed || section.is_completed).length;
    const sectionsTotal = this.detail.progress.sections_total || this.detail.sections.length;

    this.detail.progress = {
      sections_total: sectionsTotal,
      sections_completed: sectionsCompleted,
      pct: sectionsTotal > 0 ? Math.round((sectionsCompleted / sectionsTotal) * 100) : 0,
    };
  }

  private syncSectionCompletion(section: TrainingSectionDTO, completed: boolean) {
    section.completed = completed;
    section.is_completed = completed;
    this.refreshProgressFromSections();
  }

  async saveLiftingSetStatus(row: TrainingLiftingRowDTO, set: TrainingLiftingSetStatusDTO, status: 'completed' | 'failed') {
    if (!this.assignmentId) return;

    const key = this.liftingSetKey(row.id, set.set_number);
    if (this.savingLiftingKey) return;

    const previousStatus = set.status;
    const previousActualReps = set.actual_reps;
    const previousFailureReason = set.failure_reason;

    this.savingLiftingKey = key;
    this.errorMsg = null;
    set.status = status;
    set.actual_reps = status === 'completed' ? row.reps : Math.max(0, row.reps - 1);
    set.failure_reason = status === 'failed' ? 'athlete_marked_failed' : null;

    try {
      const res = await this.trainingApi.saveLiftingSet(this.assignmentId, {
        lifting_row_id: row.id,
        set_number: set.set_number,
        status,
        actual_reps: set.actual_reps,
        failure_reason: set.failure_reason,
      });

      if (!res?.ok) {
        set.status = previousStatus;
        set.actual_reps = previousActualReps;
        set.failure_reason = previousFailureReason;
        this.errorMsg = res?.message ?? 'No se pudo registrar la serie';
        return;
      }

      const savedLog = res.data?.log;

      if (savedLog) {
        set.status = savedLog.status ?? status;
        set.actual_reps = savedLog.actual_reps ?? set.actual_reps;
        set.failure_reason = savedLog.failure_reason ?? set.failure_reason;
        set.notes = savedLog.notes ?? set.notes;
        set.logged_at = savedLog.logged_at ?? set.logged_at;
      }

      this.applyProgress(res.data?.progress);
      this.applyAssignmentStatus(res.data?.assignment?.status);
    } catch (e: any) {
      set.status = previousStatus;
      set.actual_reps = previousActualReps;
      set.failure_reason = previousFailureReason;
      this.errorMsg = e?.message ?? 'Error registrando la serie';
    } finally {
      this.savingLiftingKey = null;
    }
  }

  goBack() {
    this.router.navigate(['/tabs/tab1']);
  }
  youtubeEmbedUrl(url: string | null): SafeResourceUrl {
  const embed = this.toYoutubeEmbed(url);
  return this.sanitizer.bypassSecurityTrustResourceUrl(embed);
}

private toYoutubeEmbed(url: string | null): string {
  if (!url) return '';

  try {
    const u = new URL(url);

    // youtube.com/watch?v=XXXX
    if (u.hostname.includes('youtube.com')) {
      const v = u.searchParams.get('v');
      if (v) return `https://www.youtube.com/embed/${v}`;

      // youtube.com/embed/XXXX
      const parts = u.pathname.split('/').filter(Boolean);
      if (parts[0] === 'embed' && parts[1]) return `https://www.youtube.com/embed/${parts[1]}`;
    }

    // youtu.be/XXXX
    if (u.hostname.includes('youtu.be')) {
      const id = u.pathname.replace('/', '');
      if (id) return `https://www.youtube.com/embed/${id}`;
    }

    // si no se reconoce, intenta usar tal cual
    return url;
  } catch {
    return url;
  }
}


  // =========================
  // Acciones (conectar después)
  // =========================
  async onStart() {
    if (!this.assignmentId) return;

    try {
      const res = await this.trainingApi.start(this.assignmentId);
      if (res?.ok) {
        // recargar para refrescar status
        await this.loadDetails();
      }
    } catch (e: any) {
      this.errorMsg = e?.message ?? 'Error iniciando entrenamiento';
    }
  }

  async onComplete() {
    if (!this.assignmentId) return;

    try {
      const res = await this.trainingApi.complete(this.assignmentId);
      if (res?.ok) {
        await this.loadDetails();
      }
    } catch (e: any) {
      this.errorMsg = e?.message ?? 'Error completando entrenamiento';
    }
  }
  onImgError(ev: Event) {
  (ev.target as HTMLImageElement).src = this.fallbackCover;
}

  onAddResult(section: TrainingSectionDTO) {
  // abre/cierra estilo acordeón
  if (this.expandedSectionId === section.id) {
    this.cancelResultEdit();
    return;
  }
 

  this.expandedSectionId = section.id;

  // si ya existe un result, precarga
  this.editingResultValue = section.result?.value ?? '';
  this.editingResultNotes = section.result?.notes ?? '';
}


 async onMarkCompleted(section: TrainingSectionDTO) {
  if (!this.assignmentId) return;
  if (this.isSectionSaving(section.id)) return;

  this.savingSectionId = section.id;
  this.errorMsg = null;
  try {
    const res = await this.trainingApi.completeSection(this.assignmentId, section.id);

    if (res?.ok) {
      this.syncSectionCompletion(section, true);
      this.applyProgress(res.data?.progress);
      this.applyAssignmentStatus(res.data?.status);
      return;
    }

    this.errorMsg = 'No se pudo completar la sección';
  } catch (e: any) {
    this.errorMsg = e?.message ?? 'Error completando sección';
  } finally {
    this.savingSectionId = null;
  }
}
cancelResultEdit() {
  this.expandedSectionId = null;
  this.editingResultValue = '';
  this.editingResultNotes = '';
}
async saveSectionResult(section: TrainingSectionDTO) {
  if (!this.assignmentId) return;
  if (this.isSectionSaving(section.id)) return;
  
  // Forzamos a TS a tratarlo como número para la API
  const assignmentId = this.assignmentId as number;

  const value = (this.editingResultValue ?? '').toString().trim();
  if (!value) {
    this.errorMsg = 'Captura un resultado.';
    return;
  }

  if (!section.result_type) {
    this.errorMsg = 'Esta sección no tiene tipo de resultado configurado.';
    this.savingResult = false;
    return;
  }

  this.savingResult = true;
  this.savingSectionId = section.id;
  this.errorMsg = null;

  try {
    const res = await this.trainingApi.saveSectionResult(assignmentId, section.id, {
      training_assignment_id: assignmentId,
      result_type: section.result_type,
      value,
      notes: (this.editingResultNotes ?? '').trim() || null,
    });

    if (!res?.ok) {
      this.errorMsg = res?.message ?? 'No se pudo guardar el resultado';
      return;
    }

    const saved = res.data ?? {};
    const savedNotes = saved.notes ?? ((this.editingResultNotes ?? '').trim() || null);
    const recordedAt = saved.recorded_at ?? saved.updated_at ?? new Date().toISOString();

    section.result = {
      value: saved.value ?? value,
      unit: saved.unit ?? section.unit ?? null,
      notes: savedNotes,
      recorded_at: recordedAt,
      completed_at: recordedAt,
    };

    this.syncSectionCompletion(section, true);
    this.applyProgress(saved.progress);
    this.applyAssignmentStatus(saved.status);

    // 2. Verificamos progreso usando la estructura de tu JSON
    const progress = this.detail?.progress;

    if (progress?.pct === 100 && this.detail?.status !== 'completed') {
      await this.completeTraining();
    }

    this.cancelResultEdit();
  } catch (e: any) {
    console.error('[saveSectionResult] ERROR', e);
    this.errorMsg = e?.message ?? 'Error guardando resultado';
  } finally {
    this.savingResult = false;
    this.savingSectionId = null;
  }
}
async completeTraining() {
  if (!this.assignmentId) return;

  try {
    console.log('¡Entrenamiento completado! Actualizando estado...');
    const res = await this.trainingApi.complete(this.assignmentId as number);
    
    if (res.ok && this.detail) {
      this.detail.status = 'completed'; 
      console.log('Estado actualizado a completed en la UI');
    }
  } catch (e) {
    console.error('No se pudo marcar como completado', e);
  }
}
onEditResult(section: TrainingSectionDTO) {
  if (!section.accepts_results) return;

  this.expandedSectionId = section.id;
  this.editingResultValue = section.result?.value ?? '';
  this.editingResultNotes = section.result?.notes ?? '';
}



}
