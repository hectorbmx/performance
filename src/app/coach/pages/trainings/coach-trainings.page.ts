import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonSegment,
  IonSegmentButton,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  addCircleOutline,
  barbellOutline,
  calendarOutline,
  peopleOutline,
  personCircleOutline,
  refreshOutline,
} from 'ionicons/icons';
import {
  CoachTrainingDTO,
  CoachTrainingsService,
} from 'src/app/services/coach-trainings.service';
import { TrainingFormModalComponent } from '../../components/training-form-modal/training-form-modal.component';

type TrainingFilter = 'week' | 'free' | 'assigned';

@Component({
  selector: 'app-coach-trainings',
  standalone: true,
  templateUrl: './coach-trainings.page.html',
  styleUrls: ['./coach-trainings.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonMenuButton,
    IonSegment,
    IonSegmentButton,
    IonSpinner,
    IonTitle,
    IonToolbar,
    TrainingFormModalComponent,
  ],
})
export class CoachTrainingsPage {
  trainings: CoachTrainingDTO[] = [];
  loading = false;
  isCreateOpen = false;
  filter: TrainingFilter = 'week';

  constructor(
    private trainingsApi: CoachTrainingsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addCircleOutline,
      barbellOutline,
      calendarOutline,
      peopleOutline,
      personCircleOutline,
      refreshOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    this.loading = true;
    try {
      const params = this.filter === 'free' || this.filter === 'assigned'
        ? { visibility: this.filter }
        : undefined;
      this.trainings = await this.trainingsApi.index(params);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los entrenamientos.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async changeFilter(event: CustomEvent) {
    this.filter = event.detail.value as TrainingFilter;
    await this.load();
  }

  openCreate() {
    this.isCreateOpen = true;
  }

  closeCreate() {
    this.isCreateOpen = false;
  }

  onTrainingCreated(training: CoachTrainingDTO) {
    this.trainings = [training, ...this.trainings];
    this.isCreateOpen = false;
  }

  visibilityLabel(training: CoachTrainingDTO): string {
    return training.visibility === 'free' ? 'Libre' : 'Asignado';
  }

  trainingDate(training: CoachTrainingDTO): string {
    return training.scheduled_at || 'Sin fecha';
  }

  assignmentsLabel(training: CoachTrainingDTO): string {
    return `${training.assignments_count || 0} atletas`;
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 2200,
      position: 'top',
    });
    await toast.present();
  }
}
