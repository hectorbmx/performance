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
import { ToastController } from '@ionic/angular/standalone';

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
  weight_kg: number | null;
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
    FormsModule
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

  // Estado de expansión
  weightExpanded = false;
  expandedMetricId: number | null = null;

  // Valores de edición
  editingWeight: number | null = null;
  editingWeightNotes: string | null = null;
  editingMetricValue: number | null = null;
  editingMetricNotes: string | null = null;

  constructor(
    private profileService: ProfileService,
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
    });
  }

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

  // ==========================================
  // WEIGHT EXPANDABLE CARD
  // ==========================================
  
  toggleWeight() {
    this.weightExpanded = !this.weightExpanded;
    
    if (this.weightExpanded) {
      // Inicializar valores cuando se expande
      this.editingWeight = this.body?.latest?.weight_kg ?? null;
      this.editingWeightNotes = this.body?.latest?.notes ?? null;
      // Cerrar cualquier métrica abierta
      this.expandedMetricId = null;
    } else {
      // Limpiar valores cuando se colapsa
      this.resetWeightEdit();
    }
  }

  cancelWeightEdit(event: Event) {
    event.stopPropagation();
    this.resetWeightEdit();
    this.weightExpanded = false;
  }

  async saveWeight(event: Event) {
    event.stopPropagation();

    if (!this.editingWeight || this.editingWeight <= 0) {
      await this.showToast('Por favor ingresa un peso válido', 'warning');
      return;
    }

    try {
      const data = {
        weight_kg: this.editingWeight,
        notes: this.editingWeightNotes || null,
      };

      await this.profileService.addBodyRecord(data);
      await this.reloadProfile();
      
      this.weightExpanded = false;
      this.resetWeightEdit();
      
      await this.showToast('Peso guardado correctamente', 'success');
    } catch (err) {
      console.error(err);
      await this.showToast('Error al guardar el peso', 'danger');
    }
  }

  private resetWeightEdit() {
    this.editingWeight = null;
    this.editingWeightNotes = null;
  }

  // ==========================================
  // METRICS EXPANDABLE CARDS
  // ==========================================

  toggleMetric(metric: Metric) {
    if (this.expandedMetricId === metric.id) {
      // Si ya está expandido, colapsar
      this.expandedMetricId = null;
      this.resetMetricEdit();
    } else {
      // Expandir esta métrica
      this.expandedMetricId = metric.id;
      this.editingMetricValue = metric.last?.value ?? null;
      this.editingMetricNotes = metric.last?.notes ?? null;
      // Cerrar peso si está abierto
      this.weightExpanded = false;
    }
  }

  cancelMetricEdit(event: Event) {
    event.stopPropagation();
    this.expandedMetricId = null;
    this.resetMetricEdit();
  }

  async saveMetric(event: Event, metric: Metric) {
    event.stopPropagation();

    if (!this.editingMetricValue || this.editingMetricValue <= 0) {
      await this.showToast('Por favor ingresa un valor válido', 'warning');
      return;
    }

    try {
      const data = {
        training_metric_id: metric.id,
        value: this.editingMetricValue,
        notes: this.editingMetricNotes || null,
      };

      await this.profileService.addMetricRecord(data);
      await this.reloadProfile();
      
      this.expandedMetricId = null;
      this.resetMetricEdit();
      
      await this.showToast(`${metric.name} actualizado`, 'success');
    } catch (err) {
      console.error(err);
      await this.showToast('Error al guardar la métrica', 'danger');
    }
  }

  private resetMetricEdit() {
    this.editingMetricValue = null;
    this.editingMetricNotes = null;
  }

  // ==========================================
  // TOAST HELPER
  // ==========================================

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
}