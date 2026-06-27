import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { readerOutline, refreshOutline } from 'ionicons/icons';
import { CoachSubscriptionDTO, CoachSubscriptionsService } from 'src/app/services/coach-subscriptions.service';

@Component({
  selector: 'app-coach-subscriptions',
  standalone: true,
  templateUrl: './coach-subscriptions.page.html',
  styleUrls: ['./coach-subscriptions.page.scss'],
  imports: [
    CommonModule,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonMenuButton,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
})
export class CoachSubscriptionsPage {
  loading = false;
  subscriptions: CoachSubscriptionDTO[] = [];

  constructor(
    private subscriptionsApi: CoachSubscriptionsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({ readerOutline, refreshOutline });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    this.loading = true;
    try {
      this.subscriptions = await this.subscriptionsApi.index();
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar las subscripciones.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  athleteName(item: CoachSubscriptionDTO): string {
    return item.client?.full_name || 'Atleta sin nombre';
  }

  planLine(item: CoachSubscriptionDTO): string {
    const price = `$${Number(item.plan.price || 0).toFixed(2)} ${item.plan.currency || 'MXN'}`;
    const provider = item.plan.payment_provider === 'stripe' ? 'Stripe' : 'Manual';
    return `${item.plan.name} · ${price} · ${provider}`;
  }

  dateLine(item: CoachSubscriptionDTO): string {
    if (!item.ends_at) {
      return 'Sin fecha de vencimiento';
    }

    if (item.is_in_grace && item.grace_until) {
      return `Vence ${item.ends_at} · gracia hasta ${item.grace_until}`;
    }

    return `Vence ${item.ends_at}`;
  }

  statusClass(item: CoachSubscriptionDTO): string {
    if (item.status_label === 'Pagada') return 'paid';
    if (item.status_label === 'Gracia') return 'grace';
    if (item.status_label === 'Vencida' || item.status_label === 'Cancelada') return 'expired';
    return 'pending';
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 1800,
      position: 'top',
    });
    await toast.present();
  }
}
