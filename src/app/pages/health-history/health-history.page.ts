import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  flameOutline,
  flashOutline,
  refreshOutline,
  walkOutline,
} from 'ionicons/icons';
import {
  DailyHealthMetric,
  HealthMetricKey,
  HealthMetricsService,
} from '../../services/health-metrics.service';

type MetricConfig = {
  key: HealthMetricKey;
  title: string;
  label: string;
  unit: string;
  icon: string;
  goal: number;
};

@Component({
  selector: 'app-health-history',
  standalone: true,
  imports: [
    CommonModule,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './health-history.page.html',
  styleUrls: ['./health-history.page.scss'],
})
export class HealthHistoryPage {
  loading = false;
  errorMsg: string | null = null;
  items: DailyHealthMetric[] = [];

  readonly configs: Record<HealthMetricKey, MetricConfig> = {
    steps: {
      key: 'steps',
      title: 'Pasos',
      label: 'STEPS',
      unit: '',
      icon: 'walk-outline',
      goal: 10000,
    },
    calories: {
      key: 'calories',
      title: 'Calorias quemadas',
      label: 'BURNED',
      unit: 'kcal',
      icon: 'flame-outline',
      goal: 600,
    },
    active_minutes: {
      key: 'active_minutes',
      title: 'Minutos activos',
      label: 'ACTIVE',
      unit: 'min',
      icon: 'flash-outline',
      goal: 45,
    },
  };

  metric: HealthMetricKey = 'steps';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private healthMetrics: HealthMetricsService,
  ) {
    addIcons({ flameOutline, flashOutline, refreshOutline, walkOutline });
  }

  get config(): MetricConfig {
    return this.configs[this.metric];
  }

  get last7(): DailyHealthMetric[] {
    return this.items.slice(-7);
  }

  get total7(): number {
    return this.last7.reduce((sum, item) => sum + this.valueFor(item), 0);
  }

  get average7(): number {
    return this.last7.length ? Math.round(this.total7 / this.last7.length) : 0;
  }

  get best7(): DailyHealthMetric | null {
    return this.bestOf(this.last7);
  }

  get best30(): DailyHealthMetric[] {
    return [...this.items]
      .sort((a, b) => this.valueFor(b) - this.valueFor(a))
      .slice(0, 5);
  }

  get max7(): number {
    return Math.max(...this.last7.map(item => this.valueFor(item)), this.config.goal, 1);
  }

  async ionViewWillEnter() {
    const requested = this.route.snapshot.paramMap.get('metric') as HealthMetricKey | null;
    this.metric = requested && requested in this.configs ? requested : 'steps';
    await this.load();
  }

  async load() {
    this.loading = true;
    this.errorMsg = null;

    try {
      this.items = await this.healthMetrics.getHistory(30);
    } catch (err: any) {
      this.errorMsg = err?.message ?? 'No se pudo cargar el historial.';
    } finally {
      this.loading = false;
    }
  }

  goBack() {
    this.router.navigate(['/tabs/tab1']);
  }

  valueFor(item: DailyHealthMetric): number {
    return item[this.metric] ?? 0;
  }

  barHeight(item: DailyHealthMetric): string {
    const pct = Math.max(8, Math.round((this.valueFor(item) / this.max7) * 100));
    return `${pct}%`;
  }

  formatValue(value: number): string {
    const rounded = Math.round(value);
    return this.config.unit ? `${rounded.toLocaleString()} ${this.config.unit}` : rounded.toLocaleString();
  }

  shortDay(date: string): string {
    const parsed = new Date(`${date}T00:00:00`);
    return parsed.toLocaleDateString('es-MX', { weekday: 'short' });
  }

  friendlyDate(date: string): string {
    const parsed = new Date(`${date}T00:00:00`);
    return parsed.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
  }

  progressPct(value: number): number {
    if (!this.config.goal) return 0;
    return Math.min(100, Math.round((value / this.config.goal) * 100));
  }

  private bestOf(items: DailyHealthMetric[]): DailyHealthMetric | null {
    if (!items.length) return null;
    return items.reduce((best, item) => this.valueFor(item) > this.valueFor(best) ? item : best);
  }
}
