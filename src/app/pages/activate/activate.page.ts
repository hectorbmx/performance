import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonicModule, LoadingController, ToastController, AlertController } from '@ionic/angular';

// import { ApiService } from '../../services/api'; // ajusta si tu path es distinto
import { ApiService } from '../../services/api.service';
import { addIcons } from 'ionicons';
import {
  arrowBack,
  timeOutline,chevronBackOutline,keypadOutline,lockClosedOutline,checkmarkCircleOutline,checkmarkOutline,
  barbellOutline,keyOutline,
  playCircle,eyeOffOutline,
  flashOutline,eyeOutline,
  fitnessOutline,
} from 'ionicons/icons';

type ActivateResponse = {
  ok: boolean;
  message: string;
};

@Component({
  selector: 'app-activate',
  templateUrl: './activate.page.html',
  styleUrls: ['./activate.page.scss'],
  standalone: true,
  imports: [IonicModule, CommonModule, FormsModule],
})
export class ActivatePage implements OnInit {
  email: string = '';
  activationCode: string = '';
  password: string = '';
  passwordConfirm: string = '';

  // UI helpers
  showPassword = false;
  showPasswordConfirm = false;

  constructor(
    private router: Router,
    private api: ApiService,
    private loadingCtrl: LoadingController,
    private toastCtrl: ToastController,
    private alertCtrl: AlertController
  ) {
      addIcons({
      timeOutline,eyeOffOutline,
      barbellOutline,
      flashOutline,eyeOutline,keyOutline,
      fitnessOutline,chevronBackOutline,keypadOutline,lockClosedOutline,checkmarkCircleOutline,checkmarkOutline,
      arrowBack,
      playCircle,
    });}

  ngOnInit() {
    // Traer email desde navigation state (viene del login)
    const nav = this.router.getCurrentNavigation();
    const emailFromState = nav?.extras?.state?.['email'];

    if (emailFromState) {
      this.email = String(emailFromState).trim();
    }
  }

  // Si el usuario entra directo a /activate sin state, no lo bloqueamos duro,
  // pero sí le pedimos el email.
  private async ensureEmail() {
    if (this.email?.trim()) return true;

    await this.showAlert('Falta el correo', 'Regresa al login o escribe tu correo para activar la cuenta.');
    return false;
  }

  private isValidActivationCode(code: string): boolean {
    return /^[0-9]{6}$/.test((code || '').trim());
  }

  async activateAccount() {
    const email = (this.email || '').trim();
    const activation_code = (this.activationCode || '').trim();
    const password = this.password || '';
    const password_confirmation = this.passwordConfirm || '';

    if (!(await this.ensureEmail())) return;

    // Validaciones front mínimas
    if (!this.isValidActivationCode(activation_code)) {
      await this.showAlert('Código inválido', 'El código debe tener 6 dígitos.');
      return;
    }

    if (!password || password.length < 8) {
      await this.showAlert('Contraseña inválida', 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (password !== password_confirmation) {
      await this.showAlert('Confirmación', 'Las contraseñas no coinciden.');
      return;
    }

    const loading = await this.loadingCtrl.create({
      message: 'Activando cuenta...',
      backdropDismiss: false,
    });
    await loading.present();

    try {
      const res = await this.api.post<ActivateResponse>('app/activate', {
        email,
        activation_code,
        password,
        password_confirmation,
      });

      if (!res?.ok) {
        throw new Error(res?.message || 'No se pudo activar la cuenta.');
      }

      await loading.dismiss();
      await this.showToast(res.message || 'Cuenta activada correctamente.', 'success');

      // Volver al login (evitar back a activate)
      await this.router.navigateByUrl('/login', { replaceUrl: true });

    } catch (err: any) {
      try { await loading.dismiss(); } catch {}

      // Laravel 422 suele venir como err.error.message en ApiService (según implementación)
      const msg =
        err?.error?.message ||
        err?.message ||
        'No se pudo activar la cuenta.';

      await this.showToast(msg, 'danger');
    }
  }

  toggleShowPassword() {
    this.showPassword = !this.showPassword;
  }

  toggleShowPasswordConfirm() {
    this.showPasswordConfirm = !this.showPasswordConfirm;
  }

  private async showToast(message: string, color: 'success' | 'warning' | 'danger' | 'primary' = 'primary') {
    const t = await this.toastCtrl.create({
      message,
      color,
      duration: 2200,
      position: 'bottom',
    });
    await t.present();
  }

  private async showAlert(header: string, message: string) {
    const a = await this.alertCtrl.create({
      header,
      message,
      buttons: ['OK'],
    });
    await a.present();
  }

  // Opcional: botón "Volver"
  goBackToLogin() {
    this.router.navigateByUrl('/login', { replaceUrl: true });
  }
}
