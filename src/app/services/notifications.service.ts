import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface AppNotification {
  id: string;
  type: 'info' | 'warning' | 'danger';
  title: string;
  message: string;
  action?: string;
  meta?: any;
}

@Injectable({
  providedIn: 'root',
})
export class NotificationsService {
  private notificationsSubject = new BehaviorSubject<AppNotification[]>([]);
  notifications$ = this.notificationsSubject.asObservable();

  set(notifications: AppNotification[]) {
    this.notificationsSubject.next(notifications ?? []);
  }

  get(): AppNotification[] {
    return this.notificationsSubject.value;
  }

  count(): number {
    return this.notificationsSubject.value.length;
  }

  clear() {
    this.notificationsSubject.next([]);
  }
}
