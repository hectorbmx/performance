import { Component, EnvironmentInjector, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../services/auth.service';
import { IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { barbellOutline, triangle, ellipse, square,homeOutline ,statsChartOutline,bookOutline, personOutline, person,timerOutline,newspaperOutline,videocamOutline} from 'ionicons/icons';

@Component({
  selector: 'app-tabs',
  templateUrl: 'tabs.page.html',
  styleUrls: ['tabs.page.scss'],
  imports: [CommonModule, IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel,],
})
export class TabsPage {
  public environmentInjector = inject(EnvironmentInjector);
  readonly auth=inject(AuthService);
  get hasCoachService():boolean{return !!this.auth.client()?.is_active&&this.auth.coachServiceAllowed()!==false;}

  constructor() {
    addIcons({ barbellOutline, triangle, ellipse, square,homeOutline ,statsChartOutline,personOutline,timerOutline,bookOutline,newspaperOutline,videocamOutline});
  }
}
