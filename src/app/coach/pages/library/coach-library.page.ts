import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
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
  IonSegment,
  IonSegmentButton,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { addOutline, closeOutline, cloudUploadOutline, linkOutline, openOutline, refreshOutline, saveOutline, trashOutline, videocamOutline } from 'ionicons/icons';
import { CoachLibraryPayload, CoachLibraryService, CoachLibraryTypeDTO, CoachLibraryVideoDTO, CoachLibraryVideoSource } from 'src/app/services/coach-library.service';

@Component({
  selector: 'app-coach-library',
  standalone: true,
  templateUrl: './coach-library.page.html',
  styleUrls: ['./coach-library.page.scss'],
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
    IonSegment,
    IonSegmentButton,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
})
export class CoachLibraryPage {
  videos: CoachLibraryVideoDTO[] = [];
  types: CoachLibraryTypeDTO[] = [];
  loading = false;
  saving = false;
  deletingId: number | null = null;
  isFormOpen = false;
  searchTerm = '';
  selectedFileName = '';
  form: CoachLibraryPayload = this.emptyForm();

  constructor(
    private libraryApi: CoachLibraryService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addOutline,
      closeOutline,
      cloudUploadOutline,
      linkOutline,
      openOutline,
      refreshOutline,
      saveOutline,
      trashOutline,
      videocamOutline,
    });
  }

  async ionViewWillEnter() {
    await Promise.all([this.load(), this.loadMeta()]);
  }

  async load() {
    this.loading = true;
    try {
      this.videos = await this.libraryApi.index({ q: this.searchTerm });
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo cargar la biblioteca.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async loadMeta() {
    try {
      const meta = await this.libraryApi.meta();
      this.types = meta.types;
    } catch {
      this.types = [];
    }
  }

  async search(event: CustomEvent) {
    this.searchTerm = String(event.detail?.value ?? '');
    await this.load();
  }

  openCreate() {
    this.form = this.emptyForm();
    this.selectedFileName = '';
    this.isFormOpen = true;
  }

  changeSource(source: CoachLibraryVideoSource) {
    this.form.source = source;
    this.form.youtube_url = '';
    this.form.video_file = null;
    this.selectedFileName = '';
  }

  selectFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    this.form.video_file = file;
    this.selectedFileName = file?.name ?? '';
  }

  async saveVideo() {
    if (!this.form.name.trim()) {
      await this.toast('El nombre es requerido.', 'warning');
      return;
    }

    if (this.form.source === 'youtube' && !this.form.youtube_url?.trim()) {
      await this.toast('Agrega la URL de YouTube.', 'warning');
      return;
    }

    if (this.form.source === 'upload' && !this.form.video_file) {
      await this.toast('Selecciona un video del dispositivo.', 'warning');
      return;
    }

    this.saving = true;
    try {
      const video = await this.libraryApi.store(this.form);
      this.videos = [video, ...this.videos.filter((item) => item.id !== video.id)];
      this.isFormOpen = false;
      await this.toast('Video guardado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo guardar el video.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  async deleteVideo(video: CoachLibraryVideoDTO, event: Event) {
    event.stopPropagation();
    this.deletingId = video.id;
    try {
      await this.libraryApi.destroy(video.id);
      this.videos = this.videos.filter((item) => item.id !== video.id);
      await this.toast('Video eliminado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo eliminar el video.', 'danger');
    } finally {
      this.deletingId = null;
    }
  }

  openVideo(video: CoachLibraryVideoDTO, event?: Event) {
    event?.stopPropagation();
    const url = video.playback_url || video.youtube_url;
    if (!url) return;
    window.open(url, '_blank');
  }

  sourceLabel(video: CoachLibraryVideoDTO): string {
    return video.source === 'upload' ? 'Dispositivo' : 'YouTube';
  }

  private emptyForm(): CoachLibraryPayload {
    return {
      name: '',
      source: 'youtube',
      youtube_url: '',
      video_file: null,
      training_type_catalog_id: null,
    };
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
