import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
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
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  barbellOutline,
  bookOutline,
  cardOutline,
  gridOutline,
  peopleOutline,
  readerOutline,
  settingsOutline,
} from 'ionicons/icons';

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
  constructor() {
    addIcons({
      barbellOutline,
      bookOutline,
      cardOutline,
      gridOutline,
      peopleOutline,
      readerOutline,
      settingsOutline,
    });
  }
}
