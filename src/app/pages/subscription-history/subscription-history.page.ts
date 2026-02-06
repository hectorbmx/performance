import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import {
  IonContent,
  IonHeader,
  IonTitle,
  IonToolbar,
  IonButtons,
  IonButton,
  IonIcon,
  IonSearchbar,
  IonChip,
  IonLabel,
} from '@ionic/angular/standalone';

import { addIcons } from 'ionicons';
import { chevronBackOutline, downloadOutline, cashOutline, hourglassOutline } from 'ionicons/icons';

type PaymentStatus = 'completed' | 'pending' | 'failed';

interface PaymentItem {
  id: number;
  title: string;
  amount: number;
  status: PaymentStatus;
  dateISO: string; // '2023-11-01T10:45:00'
  icon: 'cash' | 'hourglass';
}

type FilterKey = 'all' | 'this_month' | 'oct' | 'sep';

@Component({
  selector: 'app-subscription-history',
  templateUrl: './subscription-history.page.html',
  styleUrls: ['./subscription-history.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,

    IonContent,
    IonHeader,
    IonTitle,
    IonToolbar,
    IonButtons,
    IonButton,
    IonIcon,
    IonSearchbar,
    IonChip,
    IonLabel,
  ],
})
export class SubscriptionHistoryPage {
  totalPaid = 4250;
  activePlan = 'ELITE COACHING';
  growthText = '12% vs last year';

  search = '';
  activeFilter: FilterKey = 'all';

  filters: { key: FilterKey; label: string }[] = [
    { key: 'all', label: 'All Time' },
    { key: 'this_month', label: 'This Month' },
    { key: 'oct', label: 'October' },
    { key: 'sep', label: 'Sep' },
  ];

  payments: PaymentItem[] = [
    {
      id: 1,
      title: 'Premium Coaching',
      amount: 299,
      status: 'completed',
      dateISO: '2023-11-01T10:45:00',
      icon: 'cash',
    },
    {
      id: 2,
      title: 'Personal Training - 5 Sess.',
      amount: 450,
      status: 'completed',
      dateISO: '2023-10-15T14:15:00',
      icon: 'cash',
    },
    {
      id: 3,
      title: 'Nutrition Plan',
      amount: 99,
      status: 'pending',
      dateISO: '2023-10-08T09:30:00',
      icon: 'hourglass',
    },
  ];

  constructor() {
    addIcons({ chevronBackOutline, downloadOutline, cashOutline, hourglassOutline });
  }

  goBack() {
    history.back();
  }

  download() {
    // mock: luego aquí exportas PDF/CSV
    console.log('download payments...');
  }

  setFilter(key: FilterKey) {
    this.activeFilter = key;
  }

  get filteredPayments(): PaymentItem[] {
    const q = (this.search || '').trim().toLowerCase();

    let list = [...this.payments];

    // filtro por chip (mock)
    if (this.activeFilter === 'oct') {
      list = list.filter(p => new Date(p.dateISO).getMonth() === 9); // Oct = 9
    }
    if (this.activeFilter === 'sep') {
      list = list.filter(p => new Date(p.dateISO).getMonth() === 8); // Sep = 8
    }
    if (this.activeFilter === 'this_month') {
      const now = new Date();
      list = list.filter(p => {
        const d = new Date(p.dateISO);
        return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
      });
    }

    // búsqueda
    if (q) {
      list = list.filter(p => p.title.toLowerCase().includes(q));
    }

    // orden desc por fecha
    list.sort((a, b) => +new Date(b.dateISO) - +new Date(a.dateISO));
    return list;
  }

  formatMoney(n: number): string {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  formatDate(dateISO: string): string {
    const d = new Date(dateISO);
    return d.toLocaleString('en-US', {
      month: 'short',
      day: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  statusLabel(s: PaymentStatus): string {
    if (s === 'completed') return 'COMPLETED';
    if (s === 'pending') return 'PENDING';
    return 'FAILED';
  }
}
