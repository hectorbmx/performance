import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonButtons, IonButton,
  IonContent, IonItem, IonLabel, IonInput, IonTextarea,
  ModalController
} from '@ionic/angular/standalone';

@Component({
  selector: 'app-metric-edit-modal',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    IonHeader, IonToolbar, IonTitle, IonButtons, IonButton,
    IonContent, IonItem, IonLabel, IonInput, IonTextarea
  ],
  templateUrl: './metric-edit-modal.component.html',
  styleUrls: ['./metric-edit-modal.component.scss']
})
export class MetricEditModalComponent {
  @Input() metric!: any; // { code, name, unit, last? }
  @Input() currentValue: number | null = null;

  value: number | null = null;
  notes: string | null = null;
  saving = false;

  constructor(private modalCtrl: ModalController) {}

  ionViewWillEnter() {
    this.value = this.currentValue ?? null;
  }

  cancel() {
    return this.modalCtrl.dismiss(null, 'cancel');
  }

  async save() {
    if (this.value === null || this.value === undefined || isNaN(Number(this.value))) return;

    this.saving = true;

    // solo regresamos payload; el page hace el call al API
    const payload = {
      metric_code: this.metric.code,
      value: Number(this.value),
      notes: this.notes?.trim() ? this.notes.trim() : null,
    };

    await this.modalCtrl.dismiss(payload, 'save');
  }
}
