import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonContent,
  IonHeader,
  IonTitle,IonSegment,
  IonToolbar,IonButton,
  IonList,IonButtons,IonBackButton,
  IonItem,IonSegmentButton,
  IonLabel,IonNote,
  IonSpinner,
  IonRefresher,
  IonRefresherContent,
  IonIcon,
} from '@ionic/angular/standalone';

@Component({
  selector: 'app-tab2',
  templateUrl: 'tab2.page.html',
  styleUrls: ['tab2.page.scss'],
  standalone: true,
  imports: [
    CommonModule,IonButtons,
    IonContent,IonBackButton,
    IonHeader,IonButton,
    IonTitle,IonSegment,
    IonToolbar,IonSegmentButton,
    IonList,IonNote,
    IonItem,
    IonLabel,
    IonSpinner,
    IonRefresher,
    IonRefresherContent,
    IonIcon,
  ],
})
export class Tab2Page {

  constructor() {}

  // Podrías generar datos dinámicos aquí para el heatmap
  generateFakeHeatmap() {
    return new Array(24).fill(0).map(() => Math.floor(Math.random() * 4));
  }
}