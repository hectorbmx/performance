import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonChip,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonModal,
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
  barbellOutline,
  cardOutline,
  calendarOutline,
  closeCircleOutline,
  closeOutline,
  createOutline,
  peopleOutline,
  refreshOutline,
  saveOutline,
  settingsOutline,
  warningOutline,
} from 'ionicons/icons';
import {
  CoachAthleteDTO,
  CoachAthleteMembershipDTO,
  CoachAthletePaymentPayload,
  CoachAthletePayload,
  CoachAthleteTrainingDTO,
  CoachAthleteTrainingSummary,
  CoachAthletesService,
} from 'src/app/services/coach-athletes.service';
import { CoachTrainingPayload, CoachTrainingsService } from 'src/app/services/coach-trainings.service';
import { CoachPlanDTO, CoachPlansService } from 'src/app/services/coach-plans.service';
import { TrainingFormModalComponent } from '../../components/training-form-modal/training-form-modal.component';

@Component({
  selector: 'app-coach-athlete-detail',
  standalone: true,
  templateUrl: './coach-athlete-detail.page.html',
  styleUrls: ['./coach-athlete-detail.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonBackButton,
    IonButton,
    IonButtons,
    IonChip,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonItem,
    IonLabel,
    IonModal,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    IonTextarea,
    IonTitle,
    IonToggle,
    IonToolbar,
    TrainingFormModalComponent,
  ],
})
export class CoachAthleteDetailPage {
  athlete: CoachAthleteDTO | null = null;
  failedAvatarUrl: string | null = null;
  trainings: CoachAthleteTrainingDTO[] = [];
  trainingSummary: CoachAthleteTrainingSummary | null = null;
  loading = false;
  loadingTrainings = false;
  selectedAssignment: CoachAthleteTrainingDTO | null = null;
  trainingDetail: CoachTrainingPayload | null = null;
  loadingTrainingDetail = false;
  trainingDetailError: string | null = null;
  private trainingDetailRequest = 0;
  saving = false;
  isEditOpen = false;
  isAssignTrainingOpen = false;
  isAssignPlanOpen = false;
  loadingPlans = false;
  assigningPlan = false;
  isPaymentOpen = false;
  recordingPayment = false;
  paymentMembership: CoachAthleteMembershipDTO | null = null;
  paymentError = '';
  paymentForm: CoachAthletePaymentPayload = {
    amount: null,
    discount: 0,
    payment_method: 'efectivo',
    payment_date: '',
    notes: '',
  };
  plans: CoachPlanDTO[] = [];
  planForm = {
    coach_client_plan_id: null as number | null,
    grace_days: null as number | null,
  };

  form: CoachAthletePayload = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    is_active: true,
  };

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private athletesApi: CoachAthletesService,
    private plansApi: CoachPlansService,
    private trainingsApi: CoachTrainingsService,
    private toastCtrl: ToastController,
  ) {
    addIcons({
      barbellOutline,
      cardOutline,
      calendarOutline,
      closeCircleOutline,
      closeOutline,
      createOutline,
      peopleOutline,
      refreshOutline,
      saveOutline,
      settingsOutline,
      warningOutline,
    });
  }

  async ionViewWillEnter() {
    await this.load();
  }

  async load() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!id) {
      await this.router.navigate(['/coach/athletes']);
      return;
    }

    this.loading = true;
    try {
      this.athlete = await this.athletesApi.show(id);
      this.failedAvatarUrl = null;
      this.form = {
        first_name: this.athlete.first_name ?? '',
        last_name: this.athlete.last_name ?? '',
        email: this.athlete.email ?? '',
        phone: this.athlete.phone ?? '',
        is_active: this.athlete.is_active,
      };
      await this.loadTrainings(id);
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo cargar el atleta.', 'danger');
    } finally {
      this.loading = false;
    }
  }

  async loadTrainings(id = this.athlete?.id) {
    if (!id) return;

    this.loadingTrainings = true;
    try {
      const { items, summary } = await this.athletesApi.trainingsWithSummary(id);
      this.trainings = items;
      this.trainingSummary = summary;
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudieron cargar los entrenamientos.', 'danger');
    } finally {
      this.loadingTrainings = false;
    }
  }

  async openTraining(item: CoachAthleteTrainingDTO): Promise<void> {
    this.selectedAssignment = item;
    this.trainingDetail = null;
    this.trainingDetailError = null;
    const request = ++this.trainingDetailRequest;
    if (!item.training?.id) {
      this.loadingTrainingDetail = false;
      this.trainingDetailError = 'Este entrenamiento ya no está disponible.';
      return;
    }
    this.loadingTrainingDetail = true;
    try {
      const detail = await this.trainingsApi.show(item.training.id);
      if (request === this.trainingDetailRequest) this.trainingDetail = detail;
    } catch (err: any) {
      if (request === this.trainingDetailRequest) {
        this.trainingDetailError = err?.message ?? 'No se pudo cargar el entrenamiento.';
      }
    } finally {
      if (request === this.trainingDetailRequest) this.loadingTrainingDetail = false;
    }
  }

  closeTraining(): void {
    ++this.trainingDetailRequest;
    this.selectedAssignment = null;
    this.trainingDetail = null;
    this.trainingDetailError = null;
    this.loadingTrainingDetail = false;
  }

  openEdit() {
    this.isEditOpen = true;
  }

  openAssignTraining() {
    this.isAssignTrainingOpen = true;
  }

  closeAssignTraining() {
    this.isAssignTrainingOpen = false;
  }

  async onTrainingAssigned() {
    this.isAssignTrainingOpen = false;
    await this.loadTrainings();
  }

  async openAssignPlan() {
    this.isAssignPlanOpen = true;

    if (this.plans.length === 0) {
      this.loadingPlans = true;
      try {
        this.plans = (await this.plansApi.index()).filter((plan) => plan.status === 'active');
      } catch (err: any) {
        await this.toast(err?.message ?? 'No se pudieron cargar los planes.', 'danger');
      } finally {
        this.loadingPlans = false;
      }
    }
  }

  closeAssignPlan() {
    this.isAssignPlanOpen = false;
    this.planForm = { coach_client_plan_id: null, grace_days: null };
  }

  async assignPlan() {
    if (!this.athlete || !this.planForm.coach_client_plan_id) {
      await this.toast('Selecciona un plan.', 'warning');
      return;
    }

    this.assigningPlan = true;
    try {
      await this.athletesApi.assignPlan(this.athlete.id, {
        coach_client_plan_id: this.planForm.coach_client_plan_id,
        grace_days: this.planForm.grace_days,
      });
      await this.load();
      this.closeAssignPlan();
      await this.toast('Plan asignado. Quedo pendiente de pago.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo asignar el plan.', 'danger');
    } finally {
      this.assigningPlan = false;
    }
  }

  async save() {
    if (!this.athlete || !this.form.first_name.trim()) {
      await this.toast('El nombre es requerido.', 'warning');
      return;
    }

    this.saving = true;
    try {
      this.athlete = await this.athletesApi.update(this.athlete.id, this.form);
      this.isEditOpen = false;
      await this.toast('Atleta actualizado.', 'success');
    } catch (err: any) {
      await this.toast(err?.message ?? 'No se pudo guardar el atleta.', 'danger');
    } finally {
      this.saving = false;
    }
  }

  get pendingPaymentMemberships(): CoachAthleteMembershipDTO[] {
    if (!this.athlete) return [];

    const memberships = new Map<number, CoachAthleteMembershipDTO>();
    for (const membership of [this.athlete.active_membership, this.athlete.future_membership, this.athlete.latest_membership]) {
      if (membership && membership.status !== 'cancelled' && ['unpaid', 'past_due'].includes(membership.billing_status)) {
        memberships.set(membership.id, membership);
      }
    }
    return [...memberships.values()];
  }

  openPayment() {
    const membership = this.pendingPaymentMemberships[0];
    if (!membership) return;

    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    this.paymentForm = {
      amount: null,
      discount: 0,
      payment_method: 'efectivo',
      payment_date: `${today.getFullYear()}-${month}-${day}`,
      notes: '',
    };
    this.selectPaymentMembership(membership.id);
    this.isPaymentOpen = true;
  }

  selectPaymentMembership(id: number) {
    this.paymentMembership = this.pendingPaymentMemberships.find((membership) => membership.id === id) ?? null;
    this.paymentForm.amount = this.paymentMembership?.price ?? null;
    this.paymentForm.discount = 0;
    this.paymentError = '';
  }

  canDismissPayment = async () => !this.recordingPayment;

  async recordPayment() {
    const athlete = this.athlete;
    const membership = this.paymentMembership;
    if (!athlete || !membership || this.recordingPayment) return;

    const { amount, discount, payment_date } = this.paymentForm;
    if (amount == null || !Number.isFinite(amount) || amount < 0 || (discount ?? 0) < 0 || (discount ?? 0) > amount || !payment_date) {
      this.paymentError = 'Indica un monto válido, un descuento que no supere el monto y la fecha de pago.';
      return;
    }

    this.recordingPayment = true;
    this.paymentError = '';
    let message: string;
    try {
      message = await this.athletesApi.recordPayment(athlete.id, membership.id, this.paymentForm);
      for (const item of [athlete.active_membership, athlete.future_membership, athlete.latest_membership]) {
        if (item?.id === membership.id) item.billing_status = 'paid';
      }
    } catch (err: any) {
      this.paymentError = err?.message ?? 'No se pudo registrar el pago.';
      return;
    } finally {
      this.recordingPayment = false;
    }

    this.isPaymentOpen = false;
    await this.toast(message, 'success');
  }

  initials(): string {
    return (this.athlete?.full_name || this.form.first_name || 'A').trim().charAt(0).toUpperCase();
  }

  statusLabel(): string {
    if (!this.athlete) return 'Cargando';
    if (!this.athlete.is_active) return 'Inactivo';
    return 'Activo';
  }

  get planMembership() {
    return this.athlete?.active_membership
      ?? this.athlete?.future_membership
      ?? this.athlete?.latest_membership
      ?? null;
  }

  get planDaysUntilExpiry(): number | null {
    const endsAt = this.planMembership?.ends_at;
    if (!endsAt) return null;

    const endDay = Date.parse(endsAt);
    if (!Number.isFinite(endDay)) return null;

    const today = new Date();
    const currentDay = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
    return Math.round((endDay - currentDay) / 86_400_000);
  }

  planName(): string {
    return this.athlete?.active_membership?.plan_name || 'Sin plan asignado';
  }

  planStatus(): string {
    const membership = this.athlete?.active_membership;
    if (!membership) return 'Sin membresia activa';
    return `${this.billingLabel(membership.billing_status)} - vence ${membership.ends_at || 'sin fecha'}`;
  }

  futurePlanStatus(): string {
    const membership = this.athlete?.future_membership;
    if (!membership) return 'Sin plan futuro agendado';
    return `${membership.plan_name} - inicia ${membership.starts_at || 'sin fecha'}`;
  }

  selectedPlanPreview(): string {
    const plan = this.plans.find((item) => item.id === this.planForm.coach_client_plan_id);
    if (!plan) return 'Selecciona un plan para calcular la proxima membresia.';

    const baseDate = this.athlete?.future_membership?.ends_at || this.athlete?.active_membership?.ends_at;
    const startsAt = baseDate ? this.addDays(baseDate, 1) : new Date();
    const endsAt = this.addDays(startsAt, plan.billing_cycle_days);

    return `Inicia ${this.formatDate(startsAt)} - termina ${this.formatDate(endsAt)} - queda pendiente de pago`;
  }

  appAccessLabel(): string {
    if (!this.athlete?.email) return 'Sin correo para acceso app';
    return this.athlete.app_account?.activated_at ? 'Cuenta app activada' : 'Cuenta app pendiente de activacion';
  }

  trainingTitle(item: CoachAthleteTrainingDTO): string {
    return item.training?.title || 'Entrenamiento sin titulo';
  }

  trainingMeta(item: CoachAthleteTrainingDTO): string {
    const sections = item.training?.sections_count ?? 0;
    const date = item.scheduled_for || 'Sin fecha';
    return `${sections} secciones - ${date}`;
  }

  trainingStatusClass(item: CoachAthleteTrainingDTO): string {
    if (item.status === 'completed') return 'done';
    if (item.status === 'in_progress') return 'progress';
    if (item.status === 'cancelled' || item.status === 'skipped') return 'muted';
    return 'scheduled';
  }

  openWorkoutProgress(): void { if (this.athlete) void this.router.navigate(['/coach/athletes',this.athlete.id,'progress']); }



  openCalendar(): void {
    if (this.athlete) {
      this.router.navigate(['/coach/athletes', this.athlete.id, 'calendar']);
    }
  }

  private addDays(date: string | Date, days: number): Date {
    const base = typeof date === 'string' ? new Date(`${date}T00:00:00`) : new Date(date);
    base.setDate(base.getDate() + days);
    return base;
  }

  private formatDate(date: Date): string {
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  private billingLabel(status: string): string {
    if (status === 'paid') return 'Pagado';
    if (status === 'unpaid') return 'Pendiente de pago';
    if (status === 'past_due') return 'Vencido';
    if (status === 'cancelled') return 'Cancelado';
    return status;
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
