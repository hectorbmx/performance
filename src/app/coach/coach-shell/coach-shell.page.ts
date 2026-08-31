import { Component } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import {
  IonBadge,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonMenu,
  IonMenuToggle,
  IonTabBar,
  IonTabButton,
  IonTabs,
  MenuController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  barbellOutline,
  bookOutline,
  cardOutline,
  gridOutline,
  peopleOutline,
  readerOutline,
  logOutOutline,
  settingsOutline,
} from 'ionicons/icons';
import { AuthService } from 'src/app/services/auth.service';

@Component({
  selector: 'app-coach-shell',
  standalone: true,
  templateUrl: './coach-shell.page.html',
  styleUrls: ['./coach-shell.page.scss'],
  imports: [
    IonBadge,
    IonContent,
    IonIcon,
    IonItem,
    IonLabel,
    IonList,
    IonMenu,
    IonMenuToggle,
    IonTabBar,
    IonTabButton,
    IonTabs,
    RouterLink,
    RouterLinkActive,
  ],
})
export class CoachShellPage {
  loggingOut = false;

  constructor(
    private auth: AuthService,
    private menuCtrl: MenuController,
    private router: Router,
  ) {
    addIcons({
      barbellOutline,
      bookOutline,
      cardOutline,
      gridOutline,
      logOutOutline,
      peopleOutline,
      readerOutline,
      settingsOutline,
    });
  }

  async logout() {
    if (this.loggingOut) return;

    this.loggingOut = true;
    try {
      await this.auth.logout();
      await this.menuCtrl.close('coach-menu');
      await this.router.navigate(['/login'], { replaceUrl: true });
    } finally {
      this.loggingOut = false;
    }
  }
}
