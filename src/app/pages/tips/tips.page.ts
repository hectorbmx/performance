import { CommonModule } from '@angular/common';
import { Component, inject, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonButton,
  IonIcon,
  IonContent,
  IonRefresher,
  IonRefresherContent,
  IonSearchbar,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  ToastController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  alertCircleOutline,
  chevronForwardOutline,
  newspaperOutline,
  refreshOutline,
  searchOutline,
} from 'ionicons/icons';
import {
  AthleteTipCardDTO,
  AthleteTipCategoryDTO,
  AthleteTipCategoryKey,
  AthleteTipsService,
  AthleteTipType,
} from 'src/app/services/athlete-tips.service';

@Component({
  selector: 'app-tips',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    IonButton,
    IonContent,
    IonIcon,
    IonRefresher,
    IonRefresherContent,
    IonSearchbar,
    IonSelect,
    IonSelectOption,
    IonSpinner,
  ],
  templateUrl: './tips.page.html',
  styleUrls: ['./tips.page.scss'],
})
export class TipsPage implements OnDestroy {
  private readonly tipsApi = inject(AthleteTipsService);
  private readonly router = inject(Router);
  private readonly toastCtrl = inject(ToastController);

  tips: AthleteTipCardDTO[] = [];
  categories: AthleteTipCategoryDTO[] = [];
  imageUrls: Record<number, string> = {};
  loading = false;
  loadingMore = false;
  errorMessage: string | null = null;
  searchTerm = '';
  selectedCategory: AthleteTipCategoryKey | null = null;
  selectedType: AthleteTipType | null = null;
  currentPage = 1;
  lastPage = 1;
  total = 0;

  readonly types: Array<{ key: AthleteTipType; label: string }> = [
    { key: 'tip', label: 'Consejos' },
    { key: 'note', label: 'Notas' },
    { key: 'news', label: 'Noticias' },
  ];

  constructor() {
    addIcons({
      alertCircleOutline,
      chevronForwardOutline,
      newspaperOutline,
      refreshOutline,
      searchOutline,
    });
  }

  async ionViewWillEnter(): Promise<void> {
    if (this.categories.length === 0) {
      await this.loadCategories();
    }

    if (this.tips.length === 0) {
      await this.loadTips({ reset: true });
    }
  }

  ngOnDestroy(): void {
    this.clearImageUrls();
  }

  async refresh(event?: CustomEvent): Promise<void> {
    await this.loadTips({ reset: true, silent: true });
    (event?.target as HTMLIonRefresherElement | undefined)?.complete();
  }

  async search(event: CustomEvent): Promise<void> {
    this.searchTerm = String(event.detail?.value ?? '');
    await this.loadTips({ reset: true });
  }

  async filterByCategory(event: CustomEvent): Promise<void> {
    this.selectedCategory = (event.detail?.value || null) as AthleteTipCategoryKey | null;
    await this.loadTips({ reset: true });
  }

  async filterByType(event: CustomEvent): Promise<void> {
    this.selectedType = (event.detail?.value || null) as AthleteTipType | null;
    await this.loadTips({ reset: true });
  }

  async loadMore(): Promise<void> {
    if (this.loadingMore || this.currentPage >= this.lastPage) {
      return;
    }

    await this.loadTips({ page: this.currentPage + 1 });
  }

  async retry(): Promise<void> {
    await this.loadTips({ reset: true });
  }

  openTip(tip: AthleteTipCardDTO): void {
    this.router.navigate(['/tips', tip.id]);
  }

  typeLabel(type: AthleteTipType): string {
    return this.types.find(item => item.key === type)?.label ?? 'Tip';
  }

  scopeLabel(tip: AthleteTipCardDTO): string {
    return tip.scope === 'global' ? 'General' : 'De tu coach';
  }

  dateLabel(value: string | null): string {
    if (!value) {
      return '';
    }

    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(new Date(value));
  }

  expirationLabel(value: string | null): string | null {
    if (!value) {
      return null;
    }

    return `Expira ${this.dateLabel(value)}`;
  }

  get hasMore(): boolean {
    return this.currentPage < this.lastPage;
  }

  private async loadCategories(): Promise<void> {
    try {
      this.categories = await this.tipsApi.categories();
    } catch (err) {
      console.warn('No se pudieron cargar categorias de tips', err);
      this.categories = [];
    }
  }

  private async loadTips(options: { reset?: boolean; page?: number; silent?: boolean } = {}): Promise<void> {
    const page = options.reset ? 1 : options.page ?? this.currentPage;

    if (options.reset) {
      this.loading = !options.silent;
      this.errorMessage = null;
      this.clearImageUrls();
    } else {
      this.loadingMore = true;
    }

    try {
      const result = await this.tipsApi.index({
        q: this.searchTerm,
        category: this.selectedCategory,
        type: this.selectedType,
        page,
        per_page: 20,
      });

      this.currentPage = result.meta.current_page;
      this.lastPage = result.meta.last_page;
      this.total = result.meta.total;
      this.tips = options.reset ? result.data : [...this.tips, ...result.data];
      await this.loadImagesFor(result.data);
    } catch (err: any) {
      const message = err?.message ?? 'No se pudieron cargar los tips.';
      this.errorMessage = message;
      if (!options.silent) {
        await this.toast(message);
      }
    } finally {
      this.loading = false;
      this.loadingMore = false;
    }
  }

  private async loadImagesFor(tips: AthleteTipCardDTO[]): Promise<void> {
    await Promise.all(tips.map(async tip => {
      if (!tip.image_url || this.imageUrls[tip.id]) {
        return;
      }

      try {
        const blob = await this.tipsApi.imageBlob(tip.id);
        this.imageUrls[tip.id] = this.tipsApi.objectUrlFor(blob);
      } catch (err) {
        console.warn('No se pudo cargar imagen de tip', tip.id, err);
      }
    }));
  }

  private clearImageUrls(): void {
    Object.values(this.imageUrls).forEach(url => this.tipsApi.revokeObjectUrl(url));
    this.imageUrls = {};
  }

  private async toast(message: string): Promise<void> {
    const toast = await this.toastCtrl.create({
      message,
      color: 'danger',
      duration: 2200,
      position: 'top',
    });

    await toast.present();
  }
}
