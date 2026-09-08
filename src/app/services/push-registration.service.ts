import { Injectable } from '@angular/core';
import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { FirebaseMessaging, type NotificationActionPerformedEvent, type NotificationReceivedEvent } from '@capacitor-firebase/messaging';
import { ToastController } from '@ionic/angular/standalone';
import { ApiService } from './api.service';
import { AuthService } from './auth.service';
import { NotificationNavigationService } from './notification-navigation.service';

@Injectable({ providedIn: 'root' })
export class PushRegistrationService {
  private loginListenerInstalled = false;
  private appStateListenerInstalled = false;
  private lastForegroundRefreshAt = 0;
  private readonly foregroundRefreshThrottleMs = 15000;

  constructor(
    private api: ApiService,
    private toastCtrl: ToastController,
    private auth: AuthService,
    private notificationNavigation: NotificationNavigationService,
  ) {}

  async init(): Promise<void> {
    try {
      if (!Capacitor.isNativePlatform()) {
        return;
      }

      this.installLoginListener();
      this.installAppStateListener();

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

  private installAppStateListener(): void {
    if (this.appStateListenerInstalled) {
      return;
    }

    App.addListener('appStateChange', (state) => {
      if (state.isActive) {
        this.refreshNotificationsFromForeground();
      }
    });

    this.appStateListenerInstalled = true;
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

  private async refreshNotificationsFromForeground(): Promise<void> {
    const now = Date.now();

    if (now - this.lastForegroundRefreshAt < this.foregroundRefreshThrottleMs) {
      return;
    }

    this.lastForegroundRefreshAt = now;

    try {
      if ((await this.auth.getActorType()) !== 'client') {
        return;
      }

      await this.auth.me();
    } catch (err) {
      console.warn('No se pudo refrescar app/me al volver a foreground', err);
    }
  }

  private async handlePushAction(event: NotificationActionPerformedEvent): Promise<void> {
    const data = event.notification?.data ?? {};
    console.log('Push accion ejecutada:', { actionId: event.actionId, data });

    await this.notificationNavigation.navigate(data);
  }
}
