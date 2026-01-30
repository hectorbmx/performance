import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { TimerService, TimerMode, TimerState, TimerData } from '../../services/timer.service';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-timer',
  templateUrl: './timer.page.html',
  styleUrls: ['./timer.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    IonicModule
  ]
})
export class TimerPage implements OnInit, OnDestroy {
  timerData?: TimerData;
  private timerSubscription?: Subscription;

  // Para configurar countdown
  countdownMinutes: number = 5;
  countdownSeconds: number = 0;

  // Para configurar intervalos
  intervalRounds: number = 3;
  intervalWorkMinutes: number = 0;
  intervalWorkSeconds: number = 20;
  intervalRestMinutes: number = 0;
  intervalRestSeconds: number = 10;

  // Enums para el template
  TimerMode = TimerMode;
  TimerState = TimerState;

  // UI State
  selectedMode: TimerMode = TimerMode.STOPWATCH;
  showConfig: boolean = false;

  constructor(private timerService: TimerService) {}

  ngOnInit() {
    // Suscribirse a los cambios del timer
    this.timerSubscription = this.timerService.getTimerObservable().subscribe(
      (data) => {
        this.timerData = data;
      }
    );
  }

  ngOnDestroy() {
    if (this.timerSubscription) {
      this.timerSubscription.unsubscribe();
    }
  }

  /**
   * Inicia el timer según el modo seleccionado
   */
  start() {
    switch (this.selectedMode) {
      case TimerMode.STOPWATCH:
        this.timerService.startStopwatch();
        break;

      case TimerMode.COUNTDOWN:
        const totalSeconds = (this.countdownMinutes * 60) + this.countdownSeconds;
        if (totalSeconds > 0) {
          this.timerService.startCountdown(totalSeconds);
        }
        break;

      case TimerMode.INTERVAL:
        const workSeconds = (this.intervalWorkMinutes * 60) + this.intervalWorkSeconds;
        const restSeconds = (this.intervalRestMinutes * 60) + this.intervalRestSeconds;
        
        if (this.intervalRounds > 0 && workSeconds > 0 && restSeconds > 0) {
          this.timerService.startInterval({
            rounds: this.intervalRounds,
            workTime: workSeconds,
            restTime: restSeconds
          });
        }
        break;
    }
    
    this.showConfig = false;
  }

  /**
   * Pausa el timer
   */
  pause() {
    this.timerService.pause();
  }

  /**
   * Reanuda el timer
   */
  resume() {
    this.timerService.resume();
  }

  /**
   * Detiene el timer
   */
  stop() {
    this.timerService.stop();
    this.showConfig = false;
  }

  /**
   * Reinicia el timer
   */
  reset() {
    this.timerService.reset();
  }

  /**
   * Cambia el modo del timer
   * CORREGIDO: Ahora permite cambiar entre modos correctamente
   */
  changeMode(mode: TimerMode) {
    // Siempre detener el timer actual primero
    this.timerService.stop();
    
    // Cambiar el modo seleccionado
    this.selectedMode = mode;
    
    // Ocultar configuración al cambiar de modo
    this.showConfig = false;
  }

  /**
   * Muestra/oculta configuración
   */
  toggleConfig() {
    if (this.timerData?.state === TimerState.IDLE || !this.timerData) {
      this.showConfig = !this.showConfig;
    }
  }

  /**
   * Obtiene el texto del botón principal
   */
  getMainButtonText(): string {
    if (!this.timerData || this.timerData.state === TimerState.IDLE) {
      return 'Iniciar';
    }
    
    if (this.timerData.state === TimerState.RUNNING) {
      return 'Pausar';
    }
    
    if (this.timerData.state === TimerState.PAUSED) {
      return 'Reanudar';
    }
    
    if (this.timerData.state === TimerState.FINISHED) {
      return 'Reintentar';
    }
    
    return 'Iniciar';
  }

  /**
   * Maneja el click del botón principal
   */
  handleMainButton() {
    if (!this.timerData || this.timerData.state === TimerState.IDLE) {
      this.start();
    } else if (this.timerData.state === TimerState.RUNNING) {
      this.pause();
    } else if (this.timerData.state === TimerState.PAUSED) {
      this.resume();
    } else if (this.timerData.state === TimerState.FINISHED) {
      this.reset();
      this.start();
    }
  }

  /**
   * Obtiene el color del timer según el estado
   */
  getTimerColor(): string {
    if (!this.timerData) return 'dark';
    
    if (this.timerData.state === TimerState.FINISHED) {
      return 'success';
    }
    
    if (this.timerData.mode === TimerMode.INTERVAL && this.timerData.isWorkPhase !== undefined) {
      return this.timerData.isWorkPhase ? 'primary' : 'secondary';
    }
    
    return 'primary';
  }

  /**
   * Obtiene el texto de fase para intervalos
   */
  getPhaseText(): string {
    if (this.timerData?.mode === TimerMode.INTERVAL) {
      return this.timerData.isWorkPhase ? 'TRABAJO' : 'DESCANSO';
    }
    return '';
  }
}