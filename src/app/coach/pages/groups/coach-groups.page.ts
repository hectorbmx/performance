import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonButton,
  IonButtons,
  IonChip,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonMenuButton,
  IonModal,
  IonSearchbar,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
  IonTitle,
  IonToggle,
  IonToolbar,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  addOutline,
  barbellOutline,
  closeOutline,
  gridOutline,
  peopleOutline,
  refreshOutline,
  removeCircleOutline,
  saveOutline,
} from 'ionicons/icons';
import { CoachGroupDTO, CoachGroupPayload, CoachGroupsService } from 'src/app/services/coach-groups.service';

@Component({
  selector: 'app-coach-groups',
  standalone: true,
  templateUrl: './coach-groups.page.html',
  styleUrls: ['./coach-groups.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonButtons,
    IonChip,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonMenuButton,
    IonModal,
    IonSearchbar,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTextarea,
    IonTitle,
    IonToggle,
    IonToolbar,
  ],
})
export class CoachGroupsPage {
  groups: CoachGroupDTO[] = [];
  loading = false;
  saving = false;
  loadingMembers = false;
  updatingMember = false;
  isFormOpen = false;
  editingGroup: CoachGroupDTO | null = null;
  searchTerm = '';
  selectedClientId: number | null = null;

  form: CoachGroupPayload = this.emptyForm();

  constructor(
    private groupsApi: CoachGroupsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      addOutline,
      barbellOutline,
      closeOutline,
      gridOutline,
      peopleOutline,
      refreshOutline,
      removeCircleOutline,
      saveOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    this.loading = true;
    try {
      this.groups = await this.groupsApi.index({ q: this.searchTerm });
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los grupos.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async search(event: CustomEvent) {
    this.searchTerm = String(event.detail?.value ?? '');
    await this.load();
  }

  openCreate() {
    this.editingGroup = null;
    this.form = this.emptyForm();
    this.isFormOpen = true;
  }

  async openEdit(group: CoachGroupDTO) {
    this.editingGroup = group;
    this.form = {
      name: group.name,
      description: group.description ?? '',
      is_active: group.is_active,
    };
    this.isFormOpen = true;
    await this.loadGroupDetail(group.id);
  }

  async loadGroupDetail(groupId: number) {
    this.loadingMembers = true;
    this.selectedClientId = null;
    try {
      this.editingGroup = await this.groupsApi.show(groupId);
      this.form = {
        name: this.editingGroup.name,
        description: this.editingGroup.description ?? '',
        is_active: this.editingGroup.is_active,
      };
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los miembros.', 'danger');
    } finally {
      this.loadingMembers = false;
    }
  }

  async saveGroup() {
    if (!this.form.name.trim()) {
      await this.toast('El nombre del grupo es requerido.', 'warning');
      return;
    }

    this.saving = true;
    try {
      if (this.editingGroup) {
        const updated = await this.groupsApi.update(this.editingGroup.id, this.form);
        this.groups = this.groups.map((group) => group.id === updated.id ? updated : group);
        this.editingGroup = { ...this.editingGroup, ...updated };
        await this.toast('Grupo actualizado.', 'success');
      } else {
        const created = await this.groupsApi.store(this.form);
        this.groups = [created, ...this.groups];
        await this.toast('Grupo creado.', 'success');
      }

      this.isFormOpen = false;
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo guardar el grupo.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  async addMember() {
    if (!this.editingGroup || !this.selectedClientId) return;

    this.updatingMember = true;
    try {
      this.editingGroup = await this.groupsApi.addClient(this.editingGroup.id, Number(this.selectedClientId));
      this.syncGroupSummary(this.editingGroup);
      this.selectedClientId = null;
      await this.toast('Atleta agregado al grupo.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo agregar el atleta.', 'danger');
    } finally {
      this.updatingMember = false;
    }
  }

  async removeMember(clientId: number) {
    if (!this.editingGroup) return;

    this.updatingMember = true;
    try {
      this.editingGroup = await this.groupsApi.removeClient(this.editingGroup.id, clientId);
      this.syncGroupSummary(this.editingGroup);
      await this.toast('Atleta removido del grupo.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo remover el atleta.', 'danger');
    } finally {
      this.updatingMember = false;
    }
  }

  activeCount(): number {
    return this.groups.filter((group) => group.is_active).length;
  }

  initials(group: CoachGroupDTO): string {
    return (group.name || 'G').trim().charAt(0).toUpperCase();
  }

  groupMeta(group: CoachGroupDTO): string {
    return `${group.clients_count} atletas · ${group.training_assignments_count} entrenamientos`;
  }

  private emptyForm(): CoachGroupPayload {
    return {
      name: '',
      description: '',
      is_active: true,
    };
  }

  private syncGroupSummary(updated: CoachGroupDTO) {
    this.groups = this.groups.map((group) => group.id === updated.id ? {
      ...group,
      clients_count: updated.clients_count,
      training_assignments_count: updated.training_assignments_count,
      name: updated.name,
      description: updated.description,
      is_active: updated.is_active,
    } : group);
  }

  private async toast(message: string, color: 'success' | 'danger' | 'warning' | 'medium' = 'medium') {
    const toast = await this.toastCtrl.create({
      message,
      color,
      duration: 2000,
      position: 'top',
    });
    await toast.present();
  }
}
