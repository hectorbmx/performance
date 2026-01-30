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
    const loggedIn = await this.auth.isLoggedIn();
    if (loggedIn) {
      await this.router.navigateByUrl('/tabs', { replaceUrl: true });
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
      const res = await this.auth.login(email, password);
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

      // Cerrar loading antes de navegar
      await loading.dismiss();

      await this.showToast('Sesión iniciada', 'success');


      // Login exitoso → área privada
      console.log('Navegando a /tabs...');
      const navigated = await this.router.navigateByUrl('/tabs', { replaceUrl: true });
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
    await this.showToast(message, 'warning');

    // Redirección automática (delay corto para que se lea el toast)
    setTimeout(() => {
      this.router.navigateByUrl('/activate', {
        state: { email: (this.email || '').trim() }
      });
    }, 1000);

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

async goToActivacion() {
  console.log('[goToActivacion] click recibido');

  const email = (this.email || '').trim();
  console.log('[goToActivacion] email:', email);

  this.router.navigateByUrl('/activate', {
    state: { email }
  }).then(ok => console.log('[goToActivacion] navigate ok?', ok))
    .catch(err => console.error('[goToActivacion] navigate error', err));
}


}