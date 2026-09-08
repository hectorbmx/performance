import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { TrainingApiService } from './training-api.service';
import type { AppNotificationDTO } from './auth.service';

export type NotificationNavigationData = {
  action?: string;
  source?: string;
  scheduled_for?: string | null;
  assignment_id?: unknown;
  training_session_id?: unknown;
};

@Injectable({ providedIn: 'root' })
export class NotificationNavigationService {
  constructor(
    private router: Router,
    private trainingApi: TrainingApiService,
  ) {}

  async navigateFromAppNotification(notification: AppNotificationDTO): Promise<void> {
    await this.navigate({
      ...(this.objectData(notification.meta)),
      action: notification.action ?? this.objectData(notification.meta).action,
    });
  }

  async navigate(data: unknown): Promise<void> {
    const payload = this.objectData(data);

    if (payload.action === 'open_membership') {
      await this.router.navigateByUrl('/subscription-history');
      return;
    }

    if (payload.action !== 'open_training') {
      await this.router.navigateByUrl('/tabs/tab1');
      return;
    }

    const assignmentId = this.numericValue(payload.assignment_id);

    if (assignmentId && payload.source !== 'free') {
      await this.router.navigate(['/training-details', assignmentId]);
      return;
    }

    const sessionId = this.numericValue(payload.training_session_id);

    if (!sessionId) {
      await this.router.navigateByUrl('/tabs/tab1');
      return;
    }

    if (payload.source === 'free') {
      await this.router.navigate(['/training-details/free', sessionId]);
      return;
    }

    try {
      const resolved = await this.trainingApi.resolveAssignment(sessionId, payload.scheduled_for ?? null);
      const resolvedAssignmentId = resolved?.data?.assignment_id;

      if (resolvedAssignmentId) {
        await this.router.navigate(['/training-details', resolvedAssignmentId]);
        return;
      }
    } catch (err) {
      console.warn('No se pudo resolver la asignacion desde la notificacion', err);
    }

    await this.router.navigateByUrl('/tabs/tab1');
  }

  private numericValue(value: unknown): number | null {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }

  private objectData(value: unknown): NotificationNavigationData {
    if (!value || typeof value !== 'object') {
      return {};
    }

    return value as NotificationNavigationData;
  }
}
