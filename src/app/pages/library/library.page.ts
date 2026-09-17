import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonModal,
  IonSearchbar,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { closeOutline, playCircleOutline, refreshOutline, searchOutline, videocamOutline } from 'ionicons/icons';
import { AthleteLibraryService, AthleteLibraryTypeDTO, AthleteLibraryVideoDTO } from 'src/app/services/athlete-library.service';

@Component({
  selector: 'app-library',
  standalone: true,
  imports: [
    CommonModule,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonModal,
    IonSearchbar,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './library.page.html',
  styleUrls: ['./library.page.scss'],
})
export class LibraryPage {
  videos: AthleteLibraryVideoDTO[] = [];
  types: AthleteLibraryTypeDTO[] = [];
  loading = false;
  searchTerm = '';
  selectedTypeId: number | null = null;
  selectedVideo: AthleteLibraryVideoDTO | null = null;

  constructor(
    private libraryApi: AthleteLibraryService,
    private sanitizer: DomSanitizer,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      closeOutline,
      playCircleOutline,
      refreshOutline,
      searchOutline,
      videocamOutline,
    });
  }

  async ionViewWillEnter() {
    await Promise.all([this.load(), this.loadCatalog()]);
  }

  async load() {
    this.loading = true;

    try {
      this.videos = await this.libraryApi.index({
        q: this.searchTerm,
        training_type_catalog_id: this.selectedTypeId,
        per_page: 30,
      });
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo cargar la biblioteca.');
    } finally {
      this.loading = false;
    }
  }

  async search(event: CustomEvent) {
    this.searchTerm = String(event.detail?.value ?? '');
    await this.load();
  }

  async filterByType(event: CustomEvent) {
    const value = event.detail?.value;
    const parsed = Number(value);

    this.selectedTypeId = Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    await this.load();
  }

  openVideo(video: AthleteLibraryVideoDTO) {
    const url = video.playback_url || video.youtube_url;

    if (!url) {
      return;
    }

    if (Capacitor.isNativePlatform() && this.isYoutubeVideo(video)) {
      this.openExternalVideo(video);
      return;
    }

    this.selectedVideo = video;
  }

  closePlayer() {
    this.selectedVideo = null;
  }

  isYoutubeVideo(video: AthleteLibraryVideoDTO | null): boolean {
    if (!video) {
      return false;
    }

    return video.source === 'youtube' || !!video.youtube_url || !!video.youtube_id;
  }

  playerUrl(video: AthleteLibraryVideoDTO): string | SafeResourceUrl {
    if (this.isYoutubeVideo(video)) {
      return this.sanitizer.bypassSecurityTrustResourceUrl(this.toYoutubeEmbed(video.youtube_url || video.playback_url));
    }

    return video.playback_url || '';
  }

  sourceLabel(video: AthleteLibraryVideoDTO): string {
    const source = video.source === 'upload' ? 'Video' : 'YouTube';
    return video.type?.name ? `${video.type.name} · ${source}` : source;
  }

  private async loadCatalog() {
    try {
      this.types = await this.libraryApi.catalog();
    } catch (err) {
      console.warn('No se pudo cargar el catalogo de biblioteca', err);
      this.types = [];
    }
  }

  private async toast(message: string) {
    const toast = await this.toastCtrl.create({
      message,
      color: 'danger',
      duration: 2200,
      position: 'top',
    });

    await toast.present();
  }

  private async openExternalVideo(video: AthleteLibraryVideoDTO): Promise<void> {
    const url = video.youtube_url || video.playback_url;

    if (!url) {
      return;
    }

    await Browser.open({
      url,
      presentationStyle: 'fullscreen',
    });
  }

  private toYoutubeEmbed(url: string | null): string {
    if (!url) {
      return '';
    }

    try {
      const parsed = new URL(url);
      let id: string | null = null;

      if (parsed.hostname.includes('youtube.com')) {
        id = parsed.searchParams.get('v');

        const parts = parsed.pathname.split('/').filter(Boolean);
        if (!id && parts[0] === 'embed' && parts[1]) {
          id = parts[1];
        }

        if (!id && parts[0] === 'shorts' && parts[1]) {
          id = parts[1];
        }
      }

      if (parsed.hostname.includes('youtu.be')) {
        id = parsed.pathname.split('/').filter(Boolean)[0] ?? null;
      }

      if (id) {
        return this.youtubeEmbedUrl(id);
      }

      return url;
    } catch {
      return url;
    }
  }

  private youtubeEmbedUrl(id: string): string {
    const params = new URLSearchParams({
      playsinline: '1',
      rel: '0',
      origin: 'https://coach.training-flow.com',
    });

    return `https://www.youtube.com/embed/${encodeURIComponent(id)}?${params.toString()}`;
  }
}
