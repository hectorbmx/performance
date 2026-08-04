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
} from 'ionicons/icons';
import {
  IonContent,
  IonHeader,
  IonTitle,
  IonToolbar,
  IonIcon,
  IonItem,
  IonLabel,
  IonInput,
  IonButton,
  LoadingController,
  AlertController,ToastController
} from '@ionic/angular/standalone';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Preferences } from '@capacitor/preferences';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    IonContent,
    IonHeader,
    IonTitle,
    IonToolbar,
    IonIcon,
    IonItem,
    IonLabel,
    IonInput,
    IonButton,
  ],
})
export class LoginPage {
  email: string = '';
  password: string = '';
  rememberSession: boolean = true;
  showPassword: boolean = false;
  
  constructor(
    private router: Router,
    private auth: AuthService,
    private loadingCtrl: LoadingController,
    private alertCtrl: AlertController,
    private api: ApiService,
    private toastCtrl: ToastController
  ) {
      addIcons({
      timeOutline,eyeOffOutline,
      barbellOutline,
      flashOutline,eyeOutline,
      fitnessOutline,
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

    const loggedIn = await this.auth.isLoggedIn();
    if (loggedIn) {
      await this.router.navigateByUrl(await this.getRedirectUrl(), { replaceUrl: true });
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


}
