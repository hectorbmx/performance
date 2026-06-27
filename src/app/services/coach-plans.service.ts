import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type CoachPlanPaymentProvider = 'manual' | 'stripe';
export type CoachPlanStatus = 'active' | 'inactive';

export interface CoachPlanDTO {
  id: number;
  name: string;
  description: string | null;
  price: number;
  currency: string;
  payment_provider: CoachPlanPaymentProvider;
  billing_cycle_days: number;
  reminder_days_before: number;
  grace_days: number;
  status: CoachPlanStatus;
  stripe_price_id: string | null;
}

export interface CoachPlanPayload {
  name: string;
  description?: string | null;
  price: number;
  currency: string;
  payment_provider: CoachPlanPaymentProvider;
  billing_cycle_days: number;
  reminder_days_before: number;
  grace_days: number;
  status: CoachPlanStatus;
}

@Injectable({ providedIn: 'root' })
export class CoachPlansService {
  constructor(private api: ApiService) {}

  async index(): Promise<CoachPlanDTO[]> {
    const res = await this.api.get<any>('coach/plans');
    return res?.data?.data ?? res?.data ?? [];
  }

  async store(payload: CoachPlanPayload): Promise<CoachPlanDTO> {
    const res = await this.api.post<any>('coach/plans', payload);
    return res.data;
  }
}
