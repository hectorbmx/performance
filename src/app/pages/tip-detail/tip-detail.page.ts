import { CommonModule } from '@angular/common';
import { Component, inject, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  alertCircleOutline,
  calendarOutline,
  newspaperOutline,
  refreshOutline,
} from 'ionicons/icons';
import {
  AthleteTipDetailDTO,
  AthleteTipsService,
  AthleteTipType,
} from 'src/app/services/athlete-tips.service';

@Component({
  selector: 'app-tip-detail',
  standalone: true,
  imports: [
    CommonModule,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './tip-detail.page.html',
  styleUrls: ['./tip-detail.page.scss'],
})
export class TipDetailPage implements OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly tipsApi = inject(AthleteTipsService);
  private readonly toastCtrl = inject(ToastController);

  tip: AthleteTipDetailDTO | null = null;
  imageUrl: string | null = null;
  loading = false;
  errorMessage: string | null = null;

  readonly typeLabels: Record<AthleteTipType, string> = {
    tip: 'Consejo',
    note: 'Nota',
    news: 'Noticia',
  };

  constructor() {
    addIcons({
      alertCircleOutline,
      calendarOutline,
      newspaperOutline,
      refreshOutline,
    });
  }

  async ionViewWillEnter(): Promise<void> {
    await this.load();
  }

  ngOnDestroy(): void {
    this.clearImageUrl();
  }

  async load(): Promise<void> {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (!Number.isFinite(id) || id <= 0) {
      this.errorMessage = 'Este tip no está disponible.';
      return;
    }

    this.loading = true;
    this.errorMessage = null;
    this.tip = null;
    this.clearImageUrl();

    try {
      this.tip = await this.tipsApi.show(id);
      await this.loadImage(id);
    } catch (err: any) {
      this.errorMessage = this.messageForError(err);
      await this.toast(this.errorMessage);
    } finally {
      this.loading = false;
    }
  }

  goBack(): void {
    this.router.navigateByUrl('/tabs/tips');
  }

  typeLabel(type: AthleteTipType): string {
    return this.typeLabels[type] ?? 'Tip';
  }

  scopeLabel(tip: AthleteTipDetailDTO): string {
    return tip.scope === 'global' ? 'General' : 'De tu coach';
  }

  dateLabel(value: string | null): string {
    if (!value) {
      return '';
    }

    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(new Date(value));
  }

  expirationLabel(value: string | null): string | null {
    if (!value) {
      return null;
    }

    return `Expira ${this.dateLabel(value)}`;
  }

  private async loadImage(id: number): Promise<void> {
    if (!this.tip?.image_url) {
      return;
    }

    try {
      const blob = await this.tipsApi.imageBlob(id);
      this.imageUrl = this.tipsApi.objectUrlFor(blob);
    } catch (err) {
      console.warn('No se pudo cargar imagen de tip', id, err);
      this.clearImageUrl();
    }
  }

  private clearImageUrl(): void {
    this.tipsApi.revokeObjectUrl(this.imageUrl);
    this.imageUrl = null;
  }

  private messageForError(err: any): string {
    if (err?.status === 404) {
      return 'Este tip ya no está disponible.';
    }

    if (err?.status === 403) {
      return 'Tu sesión no tiene acceso a este contenido.';
    }

    return err?.message ?? 'No se pudo cargar el tip.';
  }

  private async toast(message: string): Promise<void> {
    const toast = await this.toastCtrl.create({
      message,
      color: 'danger',
      duration: 2200,
      position: 'top',
    });

    await toast.present();
  }
}
