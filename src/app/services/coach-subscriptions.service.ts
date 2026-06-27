import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type CoachSubscriptionBillingStatus = 'paid' | 'unpaid' | 'past_due' | 'cancelled' | string;
export type CoachSubscriptionStatus = 'active' | 'cancelled' | 'expired' | string;
export type CoachSubscriptionPaymentProvider = 'manual' | 'stripe' | string;

export interface CoachSubscriptionDTO {
  id: number;
  client: null | {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
  };
  plan: {
    id: number;
    name: string;
    price: number;
    currency: string;
    billing_cycle_days: number;
    payment_provider: CoachSubscriptionPaymentProvider;
  };
  status: CoachSubscriptionStatus;
  billing_status: CoachSubscriptionBillingStatus;
  status_label: string;
  starts_at: string | null;
  ends_at: string | null;
  next_renewal_at: string | null;
  grace_until: string | null;
  paid_at: string | null;
  days_until_end: number | null;
  is_expired: boolean;
  is_in_grace: boolean;
  is_stripe: boolean;
  stripe_status: string | null;
}

@Injectable({ providedIn: 'root' })
export class CoachSubscriptionsService {
  constructor(private api: ApiService) {}

  async index(): Promise<CoachSubscriptionDTO[]> {
    const res = await this.api.get<any>('coach/subscriptions');
    return res?.data?.data ?? res?.data ?? [];
  }
}
