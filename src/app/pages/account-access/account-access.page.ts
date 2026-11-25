import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonContent, IonHeader, IonToolbar, IonTitle, ToastController } from '@ionic/angular/standalone';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true, selector: 'app-account-access',
  imports: [CommonModule, FormsModule, RouterLink, IonContent, IonHeader, IonToolbar, IonTitle],
  templateUrl: './account-access.page.html', styleUrls: ['../account/account.page.scss'],
})
export class AccountAccessPage {
  mode: 'register' | 'verify' | 'recover';
  email = ''; firstName = ''; lastName = ''; password = ''; confirmation = ''; code = '';
  privacy = false; busy = false; codeRequested = false; error = '';
  constructor(route: ActivatedRoute, private router: Router, private api: ApiService, private toasts: ToastController, private auth: AuthService) {
    this.mode = route.snapshot.data['mode'];
    this.email = route.snapshot.queryParamMap.get('email') ?? '';
  }
  get title(): string { return this.mode === 'register' ? 'Crear mi cuenta' : this.mode === 'verify' ? 'Confirmar correo' : 'Recuperar contraseña'; }
  async submit(): Promise<void> {
    if (this.busy) return;
    this.busy = true; this.error = '';
    try {
      const email = this.email.trim();
      if (this.mode === 'register') {
        await this.api.post('app/register', { email, first_name: this.firstName.trim(), last_name: this.lastName.trim(),
          password: this.password, password_confirmation: this.confirmation, privacy_accepted: this.privacy });
        this.password = this.confirmation = '';
        await this.router.navigate(['/verify-email'], { queryParams: { email }, replaceUrl: true });
        await this.toast('Cuenta creada. Confirma tu correo con el código.', 'success');
      } else if (this.mode === 'verify') {
        await this.api.post('app/email/verify', { email, code: this.code });
        await this.router.navigateByUrl('/login', { replaceUrl: true });
        await this.toast('Correo confirmado. Ya puedes iniciar sesión.', 'success');
      } else if (!this.codeRequested) {
        await this.requestCode();
      } else {
        await this.api.post('app/password/reset', { email, code: this.code, password: this.password, password_confirmation: this.confirmation });
        this.password = this.confirmation = '';
        await this.auth.logout();
        await this.router.navigateByUrl('/login', { replaceUrl: true });
        await this.toast('Contraseña actualizada. Inicia sesión de nuevo.', 'success');
      }
    } catch (err: any) {
      this.error = this.errorText(err); await this.toast(this.error, 'danger');
    } finally { this.busy = false; }
  }
  async resend(): Promise<void> {
    if (this.busy) return;
    this.busy = true; this.error = '';
    try { await this.requestCode(); }
    catch (err: any) { this.error = this.errorText(err); await this.toast(this.error, 'danger'); }
    finally { this.busy = false; }
  }
  private async requestCode(): Promise<void> {
    await this.api.post(this.mode === 'verify' ? 'app/email/resend' : 'app/password/forgot', { email: this.email.trim() });
    this.codeRequested = true;
    await this.toast('Si el correo corresponde a una cuenta válida, recibirás un código. Revisa también spam.', 'success');
  }
  private errorText(err: any): string {
    const errors = err?.raw?.error?.errors;
    return errors ? String(Object.values(errors).flat()[0]) : err?.message ?? 'No se pudo completar. Intenta de nuevo.';
  }
  private async toast(message: string, color: string): Promise<void> {
    const toast = await this.toasts.create({ message, color, position: 'top', duration: 6000, buttons: ['OK'] }); await toast.present();
  }
}
