import { Component, OnInit } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular/standalone';
import { PushRegistrationService } from './services/push-registration.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  imports: [IonApp, IonRouterOutlet],
})
export class AppComponent implements OnInit { // Añade implements OnInit por buena práctica
  private handlingMembershipExpired = false;

  constructor(
    private router: Router,
    private toastCtrl: ToastController,
    private pushRegistration: PushRegistrationService,
  ) {}

  ngOnInit() {
    window.addEventListener('app:membership-expired', this.handleMembershipExpired);
    this.pushRegistration.init();
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

}
