import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { FirebaseMessaging, type NotificationActionPerformedEvent, type NotificationReceivedEvent } from '@capacitor-firebase/messaging';
import { ToastController } from '@ionic/angular/standalone';
import { ApiService } from './api.service';
import { AuthService } from './auth.service';
import { TrainingApiService } from './training-api.service';

type PushNotificationData = {
  action?: string;
  source?: string;
  scheduled_for?: string | null;
  assignment_id?: unknown;
  training_session_id?: unknown;
};

@Injectable({ providedIn: 'root' })
export class PushRegistrationService {
  private loginListenerInstalled = false;

  constructor(
    private api: ApiService,
    private router: Router,
    private toastCtrl: ToastController,
    private auth: AuthService,
    private trainingApi: TrainingApiService,
  ) {}

  async init(): Promise<void> {
    try {
      if (!Capacitor.isNativePlatform()) {
        return;
      }

      this.installLoginListener();

      await FirebaseMessaging.removeAllListeners();

      FirebaseMessaging.addListener('tokenReceived', (event) => {
        this.registerPushToken(event.token);
        console.log('FCM registration token >>>', event.token);
      });

      FirebaseMessaging.addListener('notificationReceived', (event: NotificationReceivedEvent) => {
        this.handlePushReceived(event);
      });

      FirebaseMessaging.addListener('notificationActionPerformed', (event: NotificationActionPerformedEvent) => {
        this.handlePushAction(event);
      });

      let permStatus = await FirebaseMessaging.checkPermissions();

      if (permStatus.receive !== 'granted') {
        permStatus = await FirebaseMessaging.requestPermissions();
      }

      if (permStatus.receive !== 'granted') {
        console.log('Push permission NOT granted');
        return;
      }

      const result = await FirebaseMessaging.getToken();
      await this.registerPushToken(result.token);
      console.log('FCM registration token >>>', result.token);
    } catch (err) {
      console.error('Push init error:', err);
    }
  }

  async registerPendingToken(): Promise<void> {
    const { value } = await Preferences.get({ key: 'pending_push_token' });
    if (!value) return;

    try {
      await this.api.post('app/register-device', {
        token: value,
        platform: Capacitor.getPlatform(),
      });
      await Preferences.remove({ key: 'pending_push_token' });
    } catch (err) {
      console.warn('No se pudo registrar el token push pendiente', err);
    }
  }

  private installLoginListener(): void {
    if (this.loginListenerInstalled) {
      return;
    }

    window.addEventListener('app:client-login', () => {
      this.registerPendingToken();
    });

    this.loginListenerInstalled = true;
  }

  private async registerPushToken(token: string): Promise<void> {
    await Preferences.set({ key: 'pending_push_token', value: token });

    const authToken = await this.api.getToken();
    if (!authToken) return;

    await this.registerPendingToken();
  }

  private async handlePushReceived(event: NotificationReceivedEvent): Promise<void> {
    const notification = event.notification;
    console.log('Push recibida:', event);

    try {
      await this.auth.me();
    } catch (err) {
      console.warn('No se pudo refrescar app/me despues de la push', err);
    }

    const toast = await this.toastCtrl.create({
      message: notification.title || notification.body || 'Nueva notificacion',
      duration: 2600,
      position: 'top',
      color: 'primary',
    });

    await toast.present();
  }

  private async handlePushAction(event: NotificationActionPerformedEvent): Promise<void> {
    const data = this.pushData(event.notification?.data);
    console.log('Push accion ejecutada:', { actionId: event.actionId, data });

    if (data?.action !== 'open_training') {
      await this.router.navigateByUrl('/tabs/tab1');
      return;
    }

    const assignmentId = this.numericValue(data.assignment_id);

    if (assignmentId && data.source !== 'free') {
      await this.router.navigate(['/training-details', assignmentId]);
      return;
    }

    const sessionId = this.numericValue(data.training_session_id);

    if (!sessionId) {
      await this.router.navigateByUrl('/tabs/tab1');
      return;
    }

    if (data.source === 'free') {
      await this.router.navigate(['/training-details/free', sessionId]);
      return;
    }

    try {
      const resolved = await this.trainingApi.resolveAssignment(sessionId, data.scheduled_for ?? null);
      const resolvedAssignmentId = resolved?.data?.assignment_id;

      if (resolvedAssignmentId) {
        await this.router.navigate(['/training-details', resolvedAssignmentId]);
        return;
      }
    } catch (err) {
      console.warn('No se pudo resolver la asignacion desde la push', err);
    }

    await this.router.navigateByUrl('/tabs/tab1');
  }

  private numericValue(value: unknown): number | null {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }

  private pushData(value: unknown): PushNotificationData {
    if (!value || typeof value !== 'object') {
      return {};
    }

    return value as PushNotificationData;
  }
}
