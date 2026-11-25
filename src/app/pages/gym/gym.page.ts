import { CommonModule } from '@angular/common';
import { WorkoutOfflineService } from '../../services/workout-offline.service';
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { barbellOutline, addOutline, downloadOutline, trendingUpOutline, chevronForwardOutline } from 'ionicons/icons';

@Component({
  selector: 'app-gym', standalone: true,
  imports: [CommonModule, RouterLink, IonContent, IonIcon],
  templateUrl: './gym.page.html', styleUrls: ['./gym.page.scss'],
})
export class GymPage {
  async ionViewWillEnter():Promise<void>{await this.workouts.useScope('personal');}
  constructor(readonly workouts:WorkoutOfflineService) {
    addIcons({ barbellOutline, addOutline, downloadOutline, trendingUpOutline, chevronForwardOutline });
  }
}
