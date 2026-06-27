import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonMenuButton,
  IonModal,
  IonSegment,
  IonSegmentButton,
  IonSelect,
  IonSelectOption,
  IonTextarea,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { addOutline, cardOutline, personCircleOutline, refreshOutline } from 'ionicons/icons';
import { CoachPlanDTO, CoachPlanPayload, CoachPlansService } from 'src/app/services/coach-plans.service';

@Component({
  selector: 'app-coach-plans',
  standalone: true,
  templateUrl: './coach-plans.page.html',
  styleUrls: ['./coach-plans.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonMenuButton,
    IonModal,
    IonSegment,
    IonSegmentButton,
    IonSelect,
    IonSelectOption,
    IonTextarea,
    IonTitle,
    IonToolbar,
  ],
})
export class CoachPlansPage {
  loading = false;
  saving = false;
  isCreateOpen = false;
  plans: CoachPlanDTO[] = [];

  form: CoachPlanPayload = this.emptyForm();

  constructor(
    private plansApi: CoachPlansService,
    private toastCtrl: ToastController,
  ) {
    addIcons({ addOutline, cardOutline, personCircleOutline, refreshOutline });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    this.loading = true;
    try {
      this.plans = await this.plansApi.index();
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los planes.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  openCreate() {
    this.form = this.emptyForm();
    this.isCreateOpen = true;
  }

  async createPlan() {
    if (!this.form.name || this.form.price < 0 || this.form.billing_cycle_days < 1) {
      await this.toast('Revisa nombre, precio y duracion.', 'warning');
      return;
    }

    this.saving = true;
    try {
      const plan = await this.plansApi.store(this.form);
      this.plans = [plan, ...this.plans];
      this.isCreateOpen = false;
      await this.toast('Plan creado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo crear el plan.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  priceLabel(plan: CoachPlanDTO): string {
    return `$${Number(plan.price || 0).toFixed(2)} ${plan.currency || 'MXN'}`;
  }

  private emptyForm(): CoachPlanPayload {
    return {
      name: '',
      description: '',
      price: 0,
      currency: 'mxn',
      payment_provider: 'manual',
      billing_cycle_days: 30,
      reminder_days_before: 5,
      grace_days: 0,
      status: 'active',
    };
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 1800,
      position: 'top',
    });
    await toast.present();
  }
}
