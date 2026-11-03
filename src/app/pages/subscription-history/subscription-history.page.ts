import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonContent,
  IonHeader,
  IonFooter,
  IonTitle,
  IonToolbar,
  IonButtons,
  IonButton,
  IonIcon,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { chevronBackOutline, cardOutline, calendarOutline, checkmarkCircleOutline, logOutOutline } from 'ionicons/icons';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';
import { Browser } from '@capacitor/browser';
import type { PluginListenerHandle } from '@capacitor/core';

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
  payment_provider: 'stripe' | 'manual' | string;
  billing_cycle_days: number;
  can_checkout: boolean;
}

interface MembershipsResponse {
  ok: boolean;
  access_state: string;
  can_access_service: boolean;
  access_message: string | null;
  current_membership: Membership | null;
  future_membership: Membership | null;
  pending_membership: Membership | null;
  expired_membership: Membership | null;
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
    IonFooter,
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
  pendingMembership: Membership | null = null;
  expiredMembership: Membership | null = null;
  accessState = '';
  accessMessage: string | null = null;
  memberships: Membership[] = [];
  availablePlans: AvailablePlan[] = [];
  loggingOut = false;
  private browserFinishedListener?: PluginListenerHandle;

  constructor(
    private api: ApiService,
    private auth: AuthService,
    private router: Router,
  ) {
    addIcons({ chevronBackOutline, cardOutline, calendarOutline, checkmarkCircleOutline, logOutOutline });
    Browser.addListener('browserFinished', () => {
      void this.loadMemberships();
    }).then((listener) => {
      this.browserFinishedListener = listener;
    });
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
      this.pendingMembership = res.pending_membership;
      this.expiredMembership = res.expired_membership;
      this.accessState = res.access_state;
      this.accessMessage = res.access_message;
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

    await this.startCheckout(plan, 'app/memberships/future', 'No se pudo crear la membresia futura.');
  }

  async renewMembership(plan: AvailablePlan) {
    if (this.currentMembership || this.pendingMembership || !plan.can_checkout) return;

    await this.startCheckout(plan, 'app/memberships/renewal-checkout', 'No se pudo iniciar la renovacion.');
  }

  async continuePendingPayment() {
    if (!this.pendingMembership || this.creatingPlanId !== null) return;

    const planId = this.pendingMembership.plan_id;
    const endpoint = this.currentMembership
      ? 'app/memberships/future'
      : 'app/memberships/renewal-checkout';

    this.creatingPlanId = planId;
    this.error = '';

    try {
      const res: any = await this.api.post(endpoint, {
        coach_client_plan_id: planId,
        checkout: true,
      });

      if (res?.checkout_url) {
        await this.openCheckout(res.checkout_url);
        return;
      }

      await this.loadMemberships();
    } catch (err: any) {
      this.error = err?.message || 'No se pudo continuar el pago.';
    } finally {
      this.creatingPlanId = null;
    }
  }

  private async startCheckout(plan: AvailablePlan, endpoint: string, fallbackMessage: string) {
    if (this.creatingPlanId !== null) return;

    this.creatingPlanId = plan.id;
    this.error = '';

    try {
      const res: any = await this.api.post(endpoint, {
        coach_client_plan_id: plan.id,
        checkout: true,
      });

      if (res?.checkout_url) {
        await this.openCheckout(res.checkout_url);
        return;
      }

      await this.loadMemberships();
    } catch (err: any) {
      this.error = err?.message || fallbackMessage;
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

  async logout() {
    if (this.loggingOut) return;

    this.loggingOut = true;

    try {
      await this.auth.logout();
    } finally {
      await this.router.navigate(['/login'], { replaceUrl: true });
      this.loggingOut = false;
    }
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

  private async openCheckout(url: string) {
    try {
      await Browser.open({ url });
    } catch {
      const opened = window.open(url, '_blank', 'location=yes');
      if (!opened) {
        window.location.assign(url);
      }
    }
  }

  billingLabel(status: BillingStatus): string {
    if (status === 'paid') return 'Pagada';
    if (status === 'unpaid') return 'Pendiente de pago';
    if (status === 'past_due') return 'Pago vencido';
    if (status === 'canceled') return 'Cancelada';
    if (status === 'cancelled') return 'Cancelada';
    if (status === 'pending_payment') return 'Pendiente de pago';
    return status;
  }

  isRenewalFlow(): boolean {
    return !this.currentMembership && !!this.expiredMembership;
  }

  planActionLabel(): string {
    return this.isRenewalFlow() ? 'Renovar membresia' : 'Comprar futura';
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

  planAvailabilityLabel(plan: AvailablePlan): string {
    if (plan.payment_provider === 'manual') return 'Pago con tu coach';
    return 'Pago no disponible';
  }
}
