import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { IonContent, IonHeader, IonToolbar, IonTitle, ToastController } from '@ionic/angular/standalone';
import { ApiService } from '../../services/api.service';
import { AuthService, AthleteProfileDTO } from '../../services/auth.service';
import { WorkoutOfflineStore } from '../../services/workout-offline.store';
import { AUTH_API_SCOPE } from '../../services/auth-storage';
import { Preferences } from '@capacitor/preferences';

@Component({ standalone: true, selector: 'app-account',
  imports: [CommonModule, FormsModule, RouterLink, IonContent, IonHeader, IonToolbar, IonTitle],
  templateUrl: './account.page.html', styleUrls: ['./account.page.scss'],
})
export class AccountPage {
  firstName = ''; lastName = ''; password = ''; confirmation = false; deleting = false; busy = false; error = '';
  constructor(public auth: AuthService, private api: ApiService, private router: Router, private toasts: ToastController) {}
  async ionViewWillEnter(): Promise<void> {
    try {
      const res = await this.api.get<{data: AthleteProfileDTO}>('app/account/profile');
      this.firstName = res.data.first_name; this.lastName = res.data.last_name;
    } catch (err: any) { await this.failure(err); }
  }
  async save(): Promise<void> {
    if (this.busy) return; this.busy = true; this.error = '';
    try {
      await this.api.patch('app/account/profile', { first_name: this.firstName.trim(), last_name: this.lastName.trim() });
      await this.auth.me(); await this.toast('Perfil guardado.', 'success');
    } catch (err: any) { await this.failure(err); }
    finally { this.busy = false; }
  }
  async logout(): Promise<void> {
    await this.auth.logout(); await this.router.navigateByUrl('/login', {replaceUrl: true});
  }
  async deleteAccount(): Promise<void> {
    if (this.busy || !this.confirmation) return;
    this.busy = true; this.error = '';
    const userId = this.auth.user()?.id;
    try {
      const result = await this.api.request<{message: string}>('DELETE','app/account', { body: {password: this.password, confirm: this.confirmation} });
      // Server accepted the deletion. Only now remove this account's local records.
      this.auth.pendingWorkoutChanges.set(0);
      this.password = ''; await Preferences.remove({key: 'login_email'});
      await this.auth.logout();
      const store = new WorkoutOfflineStore();
      let cleanupFailed = false;
      try { await store.removeAthlete(AUTH_API_SCOPE, userId); }
      catch { cleanupFailed = true; }
      finally { await store.close(); }
      await this.router.navigateByUrl('/login', {replaceUrl: true});
      await this.toast(result.message, 'success');
      if (cleanupFailed) await this.toast('La solicitud fue aceptada y tu sesión está cerrada. No se pudieron limpiar los registros de este dispositivo; borra los datos locales de la app para retirarlos.', 'warning');
    } catch (err: any) { await this.failure(err); }
    finally { this.busy = false; }
  }
  private async failure(err: any): Promise<void> {
    const errors = err?.raw?.error?.errors;
    this.error = errors ? String(Object.values(errors).flat()[0]) : err?.message ?? 'No se pudo completar.';
    await this.toast(this.error,'danger');
  }
  private async toast(message: string, color: string): Promise<void> {
    const toast = await this.toasts.create({message,color,position:'top',duration:8000,buttons:['OK']}); await toast.present();
  }
}
