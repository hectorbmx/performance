import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonButtons, IonButton,
  IonContent, IonItem, IonLabel, IonInput, IonTextarea,
  ModalController
} from '@ionic/angular/standalone';

@Component({
  selector: 'app-weight-edit-modal',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    IonHeader, IonToolbar, IonTitle, IonButtons, IonButton,
    IonContent, IonItem, IonLabel, IonInput, IonTextarea
  ],
  templateUrl: './weight-edit-modal.component.html',
  styleUrls: ['./weight-edit-modal.component.scss']
})
export class WeightEditModalComponent {
  @Input() currentWeight: number | null = null;
  @Input() currentNotes: string | null = null;

  weight_kg: number | null = null;
  notes: string | null = null;
  saving = false;

  constructor(private modalCtrl: ModalController) {}

  ionViewWillEnter() {
    this.weight_kg = this.currentWeight ?? null;
    this.notes = this.currentNotes ?? null;
  }

  cancel() {
    return this.modalCtrl.dismiss(null, 'cancel');
  }

  async save() {
    if (this.weight_kg === null || isNaN(Number(this.weight_kg))) return;

    this.saving = true;

    const payload = {
      weight_kg: Number(this.weight_kg),
      notes: this.notes?.trim() ? this.notes.trim() : null,
    };

    await this.modalCtrl.dismiss(payload, 'save');
  }
}
