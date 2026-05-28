import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonContent,
  IonHeader,
  IonTitle,
  IonToolbar,
  IonButtons,
  IonButton,
  IonIcon,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { chevronBackOutline, cardOutline, calendarOutline, checkmarkCircleOutline } from 'ionicons/icons';
import { ApiService } from '../../services/api.service';

type MembershipStatus = 'active' | 'expired' | 'canceled' | string;
type BillingStatus = 'paid' | 'unpaid' | 'past_due' | 'canceled' | string;

interface Membership {
  id: number;
  plan_id: number;
  plan_name: string;
  price: number;
  currency: string;
  billing_cycle_days: number;
  status: MembershipStatus;
  billing_status: BillingStatus;
  starts_at: string | null;
  ends_at: string | null;
  next_renewal_at: string | null;
  grace_until: string | null;
  paid_at: string | null;
  is_stripe: boolean;
}

interface AvailablePlan {
  id: number;
  name: string;
  description: string | null;
  price: number;
  currency: string;
  billing_cycle_days: number;
  can_checkout: boolean;
}

interface MembershipsResponse {
  ok: boolean;
  current_membership: Membership | null;
  future_membership: Membership | null;
  memberships: Membership[];
  available_plans: AvailablePlan[];
}

@Component({
  selector: 'app-subscription-history',
  templateUrl: './subscription-history.page.html',
  styleUrls: ['./subscription-history.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonContent,
    IonHeader,
    IonTitle,
    IonToolbar,
    IonButtons,
    IonButton,
    IonIcon,
  ],
})
export class SubscriptionHistoryPage {
  loading = false;
  creatingPlanId: number | null = null;
  error = '';
  showPlans = false;

  currentMembership: Membership | null = null;
  futureMembership: Membership | null = null;
  memberships: Membership[] = [];
  availablePlans: AvailablePlan[] = [];

  constructor(private api: ApiService) {
    addIcons({ chevronBackOutline, cardOutline, calendarOutline, checkmarkCircleOutline });
  }

  ionViewWillEnter() {
    this.loadMemberships();
  }

  async loadMemberships() {
    this.loading = true;
    this.error = '';

    try {
      const res = await this.api.get<MembershipsResponse>('app/memberships');
      this.currentMembership = res.current_membership;
      this.futureMembership = res.future_membership;
      this.memberships = res.memberships ?? [];
      this.availablePlans = res.available_plans ?? [];
      this.showPlans = false;
    } catch (err: any) {
      this.error = err?.message || 'No se pudieron cargar tus membresias.';
    } finally {
      this.loading = false;
    }
  }

  async buyFuture(plan: AvailablePlan) {
    if (this.futureMembership || !plan.can_checkout) return;

    this.creatingPlanId = plan.id;
    this.error = '';

    try {
      const res: any = await this.api.post('app/memberships/future', {
        coach_client_plan_id: plan.id,
        checkout: true,
      });

      if (res?.checkout_url) {
        this.openCheckout(res.checkout_url);
        return;
      }

      await this.loadMemberships();
    } catch (err: any) {
      this.error = err?.message || 'No se pudo crear la membresia futura.';
    } finally {
      this.creatingPlanId = null;
    }
  }

  openPlans() {
    this.showPlans = true;
  }

  goBack() {
    history.back();
  }

  formatMoney(amount: number, currency = 'MXN'): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: currency || 'MXN',
    }).format(amount || 0);
  }

  formatDate(value: string | null): string {
    if (!value) return 'Sin fecha';

    const date = this.parseDate(value);
    if (!date) return 'Sin fecha';

    return date.toLocaleDateString('es-MX', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private parseDate(value: string | null | undefined): Date | null {
    if (!value) return null;

    const raw = String(value);
    const date = /^\d{4}-\d{2}-\d{2}$/.test(raw)
      ? new Date(`${raw}T00:00:00`)
      : new Date(raw);

    return Number.isNaN(date.getTime()) ? null : date;
  }

  private openCheckout(url: string) {
    const opened = window.open(url, '_blank', 'location=yes');
    if (!opened) {
      window.location.assign(url);
    }
  }

  billingLabel(status: BillingStatus): string {
    if (status === 'paid') return 'Pagada';
    if (status === 'unpaid') return 'Pendiente de pago';
    if (status === 'past_due') return 'Pago vencido';
    if (status === 'canceled') return 'Cancelada';
    return status;
  }

  renewalText(membership: Membership): string {
    if (membership.is_stripe) {
      return membership.next_renewal_at
        ? `Renovacion Stripe: ${this.formatDate(membership.next_renewal_at)}`
        : 'Renovacion Stripe activa';
    }

    return membership.ends_at
      ? `Vence: ${this.formatDate(membership.ends_at)}`
      : 'Sin vencimiento registrado';
  }
}
