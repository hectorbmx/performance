import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from 'src/app/services/api.service';
import { addIcons } from 'ionicons';
import {
  arrowBack,
  timeOutline,
  barbellOutline,
  playCircle,eyeOffOutline,
  flashOutline,eyeOutline,
  fitnessOutline,
  fingerPrintOutline,
} from 'ionicons/icons';
import {
  IonContent,
  IonIcon,
  LoadingController,
  AlertController,ToastController
} from '@ionic/angular/standalone';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Preferences } from '@capacitor/preferences';
import { BiometricAuthService } from 'src/app/services/biometric-auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    IonContent,
    IonIcon,
  ],
})
export class LoginPage {
  email: string = '';
  password: string = '';
  rememberSession: boolean = true;
  showPassword: boolean = false;
  biometricAvailable = false;
  biometricLoginReady = false;
  biometricLabel = 'biometria';
  biometricBusy = false;
  
  constructor(
    private router: Router,
    private auth: AuthService,
    private loadingCtrl: LoadingController,
    private alertCtrl: AlertController,
    private api: ApiService,
    private toastCtrl: ToastController,
    private biometricAuth: BiometricAuthService
  ) {
      addIcons({
      timeOutline,eyeOffOutline,
      barbellOutline,
      flashOutline,eyeOutline,
      fitnessOutline,
      fingerPrintOutline,
      arrowBack,
      playCircle,
    });
  }

  async ionViewWillEnter() {
    const [savedEmail, rememberSession] = await Promise.all([
      Preferences.get({ key: 'login_email' }),
      Preferences.get({ key: 'remember_session' }),
    ]);

    if (!this.email && savedEmail.value) {
      this.email = savedEmail.value;
    }

    this.rememberSession = rememberSession.value !== '0';
    await this.loadBiometricState();

    const loggedIn = await this.auth.isLoggedIn();
    if (loggedIn) {
      await this.router.navigateByUrl(await this.getRedirectUrl(), { replaceUrl: true });
    }
  }

  async handleBiometricLogin() {
    if (this.biometricBusy) {
      return;
    }

    this.biometricBusy = true;

    try {
      const storedSession = await this.biometricAuth.getStoredSession();
      if (!storedSession) {
        this.biometricLoginReady = false;
        await this.showToast('Primero inicia sesion con correo y activa biometria.', 'warning');
        return;
      }

      const prompt = await this.biometricAuth.authenticate(`Usa ${this.biometricLabel} para entrar.`);
      if (!prompt.ok) {
        if (!prompt.cancelled) {
          await this.showToast(prompt.message ?? 'No se pudo validar la biometria.', 'danger');
        }
        return;
      }

      const loading = await this.loadingCtrl.create({
        message: 'Validando sesion...',
        backdropDismiss: false,
      });
      await loading.present();

      try {
        await this.auth.resumeWithBiometricSession(storedSession);
        if (storedSession.email) {
          await Preferences.set({ key: 'login_email', value: storedSession.email });
        }

        await loading.dismiss();
        await this.showToast('Sesion desbloqueada', 'success');
        await this.router.navigateByUrl(await this.getRedirectUrl(), { replaceUrl: true });
      } catch (err: any) {
        await loading.dismiss();
        await this.biometricAuth.clearSession();
        this.biometricLoginReady = false;
        await this.showToast(err?.message ?? 'Tu sesion expiro. Inicia sesion con correo.', 'warning');
      }
    } finally {
      this.biometricBusy = false;
    }
  }

  async handleLogin() {
    const email = (this.email || '').trim();
    const password = this.password || '';

    // =========================
    // Validación frontend
    // =========================
    if (!email || !password) {
      await this.showAlert(
        'Faltan datos',
        'Escribe tu correo y contraseña.'
      );
      return;
    }

    const loading = await this.loadingCtrl.create({
      message: 'Iniciando sesión...',
      backdropDismiss: false,
    });
    await loading.present();

    try {
      const res = await this.auth.login(email, password, this.rememberSession);
      console.log('Respuesta del login:', res);

      if (!res?.ok) {
        throw new Error('Credenciales inválidas.');
      }
      
      // Verificar que el token se guardó correctamente
      const token = await this.api.getToken();
      console.log('TOKEN GUARDADO:', token);

      if (!token) {
        throw new Error('No se pudo guardar el token de sesión.');
      }

      await Preferences.set({ key: 'login_email', value: email });
      await Preferences.set({ key: 'remember_session', value: this.rememberSession ? '1' : '0' });

      // Cerrar loading antes de navegar
      await loading.dismiss();

      await this.offerBiometricSession(token, res, email);

      await this.showToast('Sesión iniciada', 'success');


      // Login exitoso → área privada
      const redirectUrl = await this.getRedirectUrl();
      console.log('Navegando a:', redirectUrl);
      const navigated = await this.router.navigateByUrl(redirectUrl, { replaceUrl: true });
      console.log('Navegación exitosa:', navigated);

      if (!navigated) {
        throw new Error('No se pudo navegar a la página principal.');
      }
} catch (err: any) {
  console.error('Error en login:', err);

  try { await loading.dismiss(); } catch {}

  const message =
    err?.message ||
    err?.error?.message ||
    'No se pudo iniciar sesión.';

  // ✅ Caso: cuenta pendiente de activación
  if (err?.needsActivation) {
    await this.showToast('Cuenta pendiente. Revisa tu correo para crear tu contrasena o contacta a tu coach.', 'warning');

    return;
  }

  // ❌ Otros errores
  await this.showToast(message, 'danger');
}

  }

  togglePasswordVisibility() {
    this.showPassword = !this.showPassword;
  }

  async onRememberSessionChange() {
    await this.loadBiometricState();
  }

 private async showToast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      duration: 1800,
      position: 'top',
      color,
      buttons: [{ text: 'OK', role: 'cancel' }],
    });
    await toast.present();
  }
  // =========================
  // Helper UI
  // =========================
  private async showAlert(header: string, message: string) {
    const alert = await this.alertCtrl.create({
      header,
      message,
      buttons: ['OK'],
    });
    await alert.present();
  }

private async getRedirectUrl(): Promise<string> {
  const currentNavigation = this.router.getCurrentNavigation();
  const redirectUrl =
    currentNavigation?.extras?.state?.['redirectUrl'] ||
    history.state?.redirectUrl;

  return typeof redirectUrl === 'string' && redirectUrl.startsWith('/')
    ? redirectUrl
    : await this.auth.getDefaultRoute();
}

private async loadBiometricState(): Promise<void> {
  try {
    const availability = await this.biometricAuth.availability();
    const storedSession = await this.biometricAuth.getStoredSession();

    this.biometricAvailable = availability.available;
    this.biometricLabel = availability.label;
    this.biometricLoginReady = availability.available && !!storedSession;

    if (!this.email && storedSession?.email) {
      this.email = storedSession.email;
    }
  } catch (err) {
    console.warn('No se pudo revisar biometria', err);
    this.biometricAvailable = false;
    this.biometricLoginReady = false;
  }
}

private async offerBiometricSession(token: string, res: any, email: string): Promise<void> {
  if (!this.rememberSession) {
    return;
  }

  const availability = await this.biometricAuth.availability();
  const storedSession = await this.biometricAuth.getStoredSession();

  this.biometricAvailable = availability.available;
  this.biometricLabel = availability.label;
  this.biometricLoginReady = availability.available && !!storedSession;

  if (!availability.available || storedSession) {
    return;
  }

  const alert = await this.alertCtrl.create({
    header: `Usar ${this.biometricLabel}?`,
    message: `Quieres usar ${this.biometricLabel} para acceder la proxima vez?`,
    buttons: [
      {
        text: 'Ahora no',
        role: 'cancel',
      },
      {
        text: 'Si, activar',
        role: 'confirm',
      },
    ],
  });

  await alert.present();
  const result = await alert.onDidDismiss();

  if (result.role !== 'confirm') {
    return;
  }

  await this.enableBiometricSession(token, res, email);
}

private async enableBiometricSession(token: string, res: any, email: string): Promise<void> {
  const prompt = await this.biometricAuth.authenticate(`Activa ${this.biometricLabel} para futuros accesos.`);

  if (!prompt.ok) {
    if (!prompt.cancelled) {
      await this.showToast(prompt.message ?? 'No se pudo activar la biometria.', 'warning');
    }
    return;
  }

  const actorType = res.actor_type ?? (res.coach ? 'coach' : 'client');
  await this.biometricAuth.enableSession({
    token,
    actorType,
    email,
    savedAt: new Date().toISOString(),
  });

  this.biometricLoginReady = true;
  await this.showToast(`${this.biometricLabel} activado para futuros accesos`, 'success');
}


}
