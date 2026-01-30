import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { addIcons } from 'ionicons';
import { 
  personOutline, 
  fitnessOutline, 
  barbellOutline, 
  createOutline,
  saveOutline,
  trendingUpOutline
} from 'ionicons/icons';
import { ProfileService } from 'src/app/services/profile.service';
import { IonicModule } from '@ionic/angular';

import { ModalController } from '@ionic/angular';
import { MetricEditModalComponent } from 'src/app/components/metric-edit-modal/metric-edit-modal.component';
import { ToastController } from '@ionic/angular/standalone';
import { addOutline } from 'ionicons/icons';
import { WeightEditModalComponent } from 'src/app/components/weight-edit-modal/weight-edit-modal.component';

interface User {
  id: number;
  email: string;
  client_id: number;
  is_active: boolean;
}

interface Client {
  id: number;
  first_name?: string;
  last_name?: string;
  email: string;
  phone?: string;
  coach_id: number;
  is_active: boolean;
}

interface HealthProfile {
  state?: string | null;
  city?: string | null;
  zip_code?: string | null;
  birth_date?: string | null;
  gender?: string | null;
  height_cm?: number | null;
}

interface BodyLatest {
  weight_kg: number;
  recorded_at?: string;
  source?: string;
  notes?: string | null;
}

interface Body {
  latest?: BodyLatest | null;
}

interface MetricLast {
  value: number;
  recorded_at?: string;
  source?: string;
  notes?: string | null;
}

interface Metric {
  id: number;
  code: string;
  name: string;
  unit: string;
  type: string;
  is_required: boolean;
  sort_order: number;
  last: MetricLast | null;
}

@Component({
  selector: 'app-user-profile',
  templateUrl: './user-profile.page.html',
  styleUrls: ['./user-profile.page.scss'],
  standalone: true,
  imports: [
    IonicModule,
  
    CommonModule, 
    FormsModule,
 
  ]
})
export class UserProfilePage implements OnInit {

  loading = true;
  profile: any = null;
  error: string | null = null;

  // Propiedades tipadas para el template
  user: User | null = null;
  client: Client | null = null;
  healthProfile: HealthProfile | null = null;
  body: Body | null = null;
  metrics: Metric[] = [];
  

  constructor(
    private profileService: ProfileService,
    private modalCtrl: ModalController,
    private toastCtrl: ToastController
  ) {
    // Registrar los iconos necesarios
    addIcons({
      'person-outline': personOutline,
      'fitness-outline': fitnessOutline,
      'barbell-outline': barbellOutline,
      'create-outline': createOutline,
      'save-outline': saveOutline,
      'trending-up-outline': trendingUpOutline,
       'add-outline': addOutline,
    });
  }

  // async ngOnInit() {
  //   try {
  //     this.profile = await this.profileService.getMyProfile();
      
  //     // Asignar datos a las propiedades tipadas
  //     if (this.profile) {
  //       this.user = this.profile.user;
  //       this.client = this.profile.client;
  //       this.healthProfile = this.profile.health_profile;
  //       this.body = this.profile.body;
  //       this.metrics = this.profile.metrics || [];
  //     }
  //   } catch (err) {
  //     console.error(err);
  //     this.error = 'No se pudo cargar el perfil';
  //   } finally {
  //     this.loading = false;
  //   }
  // }
  async ngOnInit() {
  this.loading = true;

  try {
    await this.reloadProfile();
  } catch (err) {
    console.error(err);
    this.error = 'No se pudo cargar el perfil';
  } finally {
    this.loading = false;
  }
}


  // Obtener iniciales del nombre
  getInitials(): string {
    if (!this.client) return '';
    const firstName = this.client.first_name || '';
    const lastName = this.client.last_name || '';
    const firstInitial = firstName.charAt(0) || '';
    const lastInitial = lastName.charAt(0) || '';
    return `${firstInitial}${lastInitial}`.toUpperCase() || 'A';
  }

  // Obtener nombre completo
  get fullName(): string {
    if (!this.client) return '';
    const firstName = this.client.first_name || '';
    const lastName = this.client.last_name || '';
    return `${firstName} ${lastName}`.trim();
  }

  // Formatear fecha de nacimiento
  formatDate(dateString?: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = date.getDate();
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                    'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} ${month} ${year}`;
  }

  // Métodos de edición
  editAvatar() {
    console.log('Editar avatar');
    // TODO: Implementar edición de avatar
  }

 
private applyProfile(res: any) {
  this.profile = res;

  this.user = res?.user ?? null;
  this.client = res?.client ?? null;
  this.healthProfile = res?.health_profile ?? null;
  this.body = res?.body ?? null;
  this.metrics = res?.metrics ?? [];
}

private async reloadProfile() {
  const res = await this.profileService.getMyProfile();
  this.applyProfile(res);
}

 
 
  logout() {
    console.log('Cerrar sesión');
    // TODO: Implementar logout
  }

async openEditMetric(metric: Metric) {
  const modal = await this.modalCtrl.create({
    component: MetricEditModalComponent,
    componentProps: {
      metric,
      currentValue: metric?.last?.value ?? null,
    },
    breakpoints: [0, 0.5, 0.85],
    initialBreakpoint: 0.5,
  });

  await modal.present();

  const { data, role } = await modal.onWillDismiss();

  if (role !== 'save' || !data) return;

  try {
    await this.profileService.addMetricRecord(data);
    await this.reloadProfile();

    await this.showToast(`${metric.name} actualizado`, 'success');

  } catch (err) {
    console.error(err);
    await this.showToast('Error al guardar la métrica', 'danger');
  }
}

private async showToast(
  message: string,
  color: 'success' | 'danger' | 'warning' | 'medium' = 'medium'
) {
  const toast = await this.toastCtrl.create({
    message,
    duration: 1800,
    position: 'bottom',
    color,
  });

  await toast.present();
}
async openWeightModal() {
  const latest = this.body?.latest ?? null;

  const modal = await this.modalCtrl.create({
    component: WeightEditModalComponent,
    componentProps: {
      currentWeight: latest?.weight_kg ?? null,
      currentNotes: latest?.notes ?? null,
    },
    breakpoints: [0, 0.5, 0.85],
    initialBreakpoint: 0.5,
  });

  await modal.present();

  const { data, role } = await modal.onWillDismiss();

  if (role !== 'save' || !data) return;

  try {
    await this.profileService.addBodyRecord(data);
    await this.reloadProfile();
    await this.showToast('Peso guardado correctamente', 'success');
  } catch (err) {
    console.error(err);
    await this.showToast('Error al guardar el peso', 'danger');
  }
}


}