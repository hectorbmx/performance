import { Component, OnInit } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { PushNotifications, PermissionStatus, Token, PushNotificationSchema, ActionPerformed } from '@capacitor/push-notifications';
import { ApiService } from './services/api.service';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular/standalone';
import { AuthService } from './services/auth.service';
import { TrainingApiService } from './services/training-api.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  imports: [IonApp, IonRouterOutlet],
})
export class AppComponent implements OnInit { // Añade implements OnInit por buena práctica
  private handlingMembershipExpired = false;

  constructor(
    private api: ApiService,
    private router: Router,
    private toastCtrl: ToastController,
    private auth: AuthService,
    private trainingApi: TrainingApiService,
  ) {}

  ngOnInit() {
    window.addEventListener('app:membership-expired', this.handleMembershipExpired);
    this.initPush();
  }

  private handleMembershipExpired = async (event: Event) => {
    if (this.handlingMembershipExpired || this.router.url === '/login') {
      return;
    }

    this.handlingMembershipExpired = true;

    const detail = (event as CustomEvent<{ message?: string }>).detail;
    const toast = await this.toastCtrl.create({
      message: detail?.message || 'Tu membresia vencio. Renueva para continuar.',
      duration: 2600,
      position: 'top',
      color: 'warning',
      buttons: [{ text: 'OK', role: 'cancel' }],
    });

    await toast.present();
    await this.router.navigateByUrl('/subscription-history', { replaceUrl: true });

    setTimeout(() => {
      this.handlingMembershipExpired = false;
    }, 800);
  };

  async initPush() {
    try {
      if (!Capacitor.isNativePlatform()) {
        return;
      }

      // 1) Pedir permisos
      let permStatus: PermissionStatus = await PushNotifications.checkPermissions();

      if (permStatus.receive !== 'granted') {
        permStatus = await PushNotifications.requestPermissions();
      }

      if (permStatus.receive !== 'granted') {
        console.log('Push permission NOT granted');
        return;
      }

      // 2) Registrar con FCM
      await PushNotifications.register();

      // 3) Listener: Registro exitoso (Obtener token)
      PushNotifications.addListener('registration', (token: Token) => {
        this.registerPushToken(token.value);
        console.log('🔥 FCM TOKEN >>>', token.value);
        // TIP: Aquí es donde deberías enviar el token a tu API de Laravel
      });

      // 4) Listener: Error de registro
      PushNotifications.addListener('registrationError', (error) => {
        console.error('❌ FCM registration error:', error);
      });

      PushNotifications.addListener('pushNotificationReceived', (notification: PushNotificationSchema) => {
        this.handlePushReceived(notification);
      });

      PushNotifications.addListener('pushNotificationActionPerformed', (notification: ActionPerformed) => {
        this.handlePushAction(notification);
      });

    } catch (err) {
      console.error('❌ Push init error:', err);
    }
  }

  private async registerPushToken(token: string) {
    await Preferences.set({ key: 'pending_push_token', value: token });

    const authToken = await this.api.getToken();
    if (!authToken) return;

    try {
      await this.api.post('app/register-device', {
        token,
        platform: Capacitor.getPlatform(),
      });
      await Preferences.remove({ key: 'pending_push_token' });
    } catch (err) {
      console.warn('No se pudo registrar el token push', err);
    }
  }

  private async handlePushReceived(notification: PushNotificationSchema): Promise<void> {
    console.log('Push recibida:', notification);

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

  private async handlePushAction(notification: ActionPerformed): Promise<void> {
    const data = notification.notification?.data ?? {};

    if (data?.action !== 'open_training') {
      await this.router.navigateByUrl('/tabs/tab1');
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
      const assignmentId = resolved?.data?.assignment_id;

      if (assignmentId) {
        await this.router.navigate(['/training-details', assignmentId]);
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
}
