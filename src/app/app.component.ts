import { Component, OnInit } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular/standalone';
import { PushRegistrationService } from './services/push-registration.service';
import { App, URLOpenListenerEvent } from '@capacitor/app';
import { Preferences } from '@capacitor/preferences';

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
    void this.installDeepLinkListener();
  }

  private async installDeepLinkListener(): Promise<void> {
    const launchUrl = await App.getLaunchUrl();
    if (launchUrl?.url) {
      await this.handleDeepLinkUrl(launchUrl.url);
    }

    await App.addListener('appUrlOpen', async (event: URLOpenListenerEvent) => {
      await this.handleDeepLinkUrl(event.url);
    });
  }

  private async handleDeepLinkUrl(url: string): Promise<void> {
    const target = this.parseDeepLink(url);

    if (!target) {
      return;
    }

    if (target.email) {
      await Preferences.set({ key: 'login_email', value: target.email });
    }

    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }

  private parseDeepLink(url: string): { email?: string } | null {
    try {
      const parsed = new URL(url);
      const opensLogin =
        parsed.protocol === 'com.app33performance:' &&
        (parsed.hostname === 'login' || parsed.pathname.replace('/', '') === 'login');

      if (!opensLogin) {
        return null;
      }

      return {
        email: parsed.searchParams.get('email') || undefined,
      };
    } catch {
      return null;
    }
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
