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
  fitnessOutline,
} from 'ionicons/icons';

import {
  TrainingApiService,
  TrainingDetailDTO,
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
    addIcons({
      timeOutline,
      barbellOutline,
      flashOutline,
      fitnessOutline,
      arrowBack,
      playCircle,
    });
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
    this.editingSectionValue = '';
    this.editingSectionNotes = '';
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

  try {
    const res = await this.trainingApi.completeSection(this.assignmentId, section.id);

    if (res?.ok) {
      // refrescar para que cambie completed y progreso
      await this.loadDetails();
      return;
    }

    this.errorMsg = 'No se pudo completar la sección';
  } catch (e: any) {
    this.errorMsg = e?.message ?? 'Error completando sección';
  }
}
cancelResultEdit() {
  this.expandedSectionId = null;
  this.editingResultValue = '';
  this.editingResultNotes = '';
}

async saveSectionResult(section: TrainingSectionDTO) {
  console.log('[saveSectionResult] click', { sectionId: section?.id, assignmentId: this.assignmentId });

  if (!this.assignmentId) {
    console.warn('[saveSectionResult] STOP: no assignmentId');
    return;
  }

  const value = (this.editingResultValue ?? '').toString().trim();
  console.log('[saveSectionResult] value raw/trim', { raw: this.editingResultValue, value });

  if (!value) {
    console.warn('[saveSectionResult] STOP: empty value');
    this.errorMsg = 'Captura un resultado.';
    return;
  }

  this.savingResult = true;
  this.errorMsg = null;
if (!section.result_type) {
  this.errorMsg = 'Esta sección no tiene tipo de resultado configurado.';
  return;
}

  try {
    console.log('[saveSectionResult] calling API...');
    const res = await this.trainingApi.saveSectionResult(this.assignmentId, section.id, {
      
      training_assignment_id: this.assignmentId,
      result_type: section.result_type,
      value,
      notes: (this.editingResultNotes ?? '').trim() || null,
    });

    console.log('[saveSectionResult] API response', res);

    if (!res?.ok) {
      this.errorMsg = res?.message ?? 'No se pudo guardar el resultado';
      return;
    }

    await this.loadDetails();
    this.cancelResultEdit();
  } catch (e: any) {
    console.error('[saveSectionResult] ERROR', e);
    this.errorMsg = e?.message ?? 'Error guardando resultado';
  } finally {
    this.savingResult = false;
    console.log('[saveSectionResult] done');
  }
}

}
