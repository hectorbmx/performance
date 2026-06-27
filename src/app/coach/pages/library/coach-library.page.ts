import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonTitle,
  IonToolbar,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { addOutline, bookOutline } from 'ionicons/icons';

@Component({
  selector: 'app-coach-library',
  standalone: true,
  templateUrl: './coach-library.page.html',
  styleUrls: ['./coach-library.page.scss'],
  imports: [CommonModule, IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonMenuButton, IonTitle, IonToolbar],
})
export class CoachLibraryPage {
  videos = [
    { name: 'Back Squat Setup', tag: 'Weightlifting', source: 'YouTube' },
    { name: 'Warmup Flow', tag: 'Mobility', source: 'Upload' },
    { name: 'Row Technique', tag: 'Conditioning', source: 'YouTube' },
  ];

  constructor() {
    addIcons({ addOutline, bookOutline });
  }
}
