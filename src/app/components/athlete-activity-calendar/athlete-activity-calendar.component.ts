import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { IonIcon, IonSpinner } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { calendarOutline, chevronBackOutline, chevronForwardOutline } from 'ionicons/icons';
import {
  ActivityCalendarDayDTO,
  ActivityCalendarDayStatus,
} from 'src/app/services/athlete-activity-calendar.service';

type CalendarCell = {
  date: string | null;
  dayNumber: number | null;
  item: ActivityCalendarDayDTO | null;
  isToday: boolean;
};

@Component({
  selector: 'app-athlete-activity-calendar',
  standalone: true,
  imports: [
    CommonModule,
    IonIcon,
    IonSpinner,
  ],
  templateUrl: './athlete-activity-calendar.component.html',
  styleUrls: ['./athlete-activity-calendar.component.scss'],
})
export class AthleteActivityCalendarComponent {
  @Input() days: ActivityCalendarDayDTO[] = [];
  @Input() month = this.currentMonth();
  @Input() loading = false;
  @Input() selectedDate: string | null = null;

  @Output() monthChange = new EventEmitter<string>();
  @Output() daySelected = new EventEmitter<ActivityCalendarDayDTO>();

  readonly weekdays = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

  constructor() {
    addIcons({
      calendarOutline,
      chevronBackOutline,
      chevronForwardOutline,
    });
  }

  get monthLabel(): string {
    const date = this.monthDate();
    const label = new Intl.DateTimeFormat('es-MX', {
      month: 'long',
      year: 'numeric',
    }).format(date);

    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  get cells(): CalendarCell[] {
    const date = this.monthDate();
    const year = date.getFullYear();
    const monthIndex = date.getMonth();
    const firstDay = new Date(year, monthIndex, 1);
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const mondayOffset = (firstDay.getDay() + 6) % 7;
    const byDate = new Map(this.days.map((day) => [day.date, day]));
    const today = this.localDateKey(new Date());
    const cells: CalendarCell[] = [];

    for (let i = 0; i < mondayOffset; i++) {
      cells.push({ date: null, dayNumber: null, item: null, isToday: false });
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const key = this.localDateKey(new Date(year, monthIndex, day));
      cells.push({
        date: key,
        dayNumber: day,
        item: byDate.get(key) ?? null,
        isToday: key === today,
      });
    }

    while (cells.length % 7 !== 0) {
      cells.push({ date: null, dayNumber: null, item: null, isToday: false });
    }

    return cells;
  }

  previousMonth(): void {
    this.monthChange.emit(this.shiftMonth(-1));
  }

  nextMonth(): void {
    this.monthChange.emit(this.shiftMonth(1));
  }

  selectDay(cell: CalendarCell): void {
    if (!cell.item) {
      return;
    }

    this.daySelected.emit(cell.item);
  }

  statusLabel(status: ActivityCalendarDayStatus | null | undefined): string {
    switch (status) {
      case 'completed':
        return 'Entrenado';
      case 'missed':
        return 'No realizado';
      case 'scheduled':
        return 'Programado';
      case 'free_completed':
        return 'Libre';
      default:
        return 'Descanso';
    }
  }

  private shiftMonth(delta: number): string {
    const date = this.monthDate();
    date.setMonth(date.getMonth() + delta);

    return this.localMonthKey(date);
  }

  private monthDate(): Date {
    const match = /^(\d{4})-(\d{2})$/.exec(this.month || '');

    if (!match) {
      return new Date();
    }

    return new Date(Number(match[1]), Number(match[2]) - 1, 1);
  }

  private currentMonth(): string {
    return this.localMonthKey(new Date());
  }

  private localMonthKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  }

  private localDateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }
}
