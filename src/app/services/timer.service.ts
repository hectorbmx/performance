import { Injectable } from '@angular/core';
import { BehaviorSubject, interval, Subscription } from 'rxjs';

export enum TimerMode {
  STOPWATCH = 'stopwatch',
  COUNTDOWN = 'countdown',
  INTERVAL = 'interval'
}

export enum TimerState {
  IDLE = 'idle',
  RUNNING = 'running',
  PAUSED = 'paused',
  FINISHED = 'finished'
}

export interface IntervalConfig {
  rounds: number;
  workTime: number; // en segundos
  restTime: number; // en segundos
}

export interface TimerData {
  mode: TimerMode;
  state: TimerState;
  currentTime: number; // tiempo actual en segundos
  targetTime?: number; // tiempo objetivo (para countdown)
  displayTime: string; // tiempo formateado (HH:MM:SS o MM:SS)
  currentRound?: number;
  totalRounds?: number;
  isWorkPhase?: boolean; // true = trabajo, false = descanso
}

@Injectable({
  providedIn: 'root'
})
export class TimerService {
  private timerSubscription?: Subscription;
  private currentMode: TimerMode = TimerMode.STOPWATCH;
  private currentState: TimerState = TimerState.IDLE;
  private currentTime: number = 0;
  private targetTime: number = 0;
  
  // Para intervalos
  private intervalConfig?: IntervalConfig;
  private currentRound: number = 0;
  private isWorkPhase: boolean = true;
  private phaseTime: number = 0;

  // Observable para que los componentes se suscriban
  private timerData$ = new BehaviorSubject<TimerData>(this.getTimerData());

  constructor() {}

  /**
   * Obtiene el observable del timer
   */
  getTimerObservable() {
    return this.timerData$.asObservable();
  }

  /**
   * Inicia el cronómetro (cuenta hacia adelante)
   */
  startStopwatch() {
    this.currentMode = TimerMode.STOPWATCH;
    this.start();
  }

  /**
   * Inicia cuenta regresiva
   * @param seconds Segundos totales para la cuenta regresiva
   */
  startCountdown(seconds: number) {
    this.currentMode = TimerMode.COUNTDOWN;
    this.targetTime = seconds;
    this.currentTime = seconds;
    this.start();
  }

  /**
   * Inicia timer de intervalos
   * @param config Configuración de intervalos
   */
  startInterval(config: IntervalConfig) {
    this.currentMode = TimerMode.INTERVAL;
    this.intervalConfig = config;
    this.currentRound = 1;
    this.isWorkPhase = true;
    this.phaseTime = config.workTime;
    this.currentTime = config.workTime;
    this.start();
  }

  /**
   * Inicia el timer
   */
  private start() {
    if (this.currentState === TimerState.RUNNING) {
      return;
    }

    this.currentState = TimerState.RUNNING;
    this.emitTimerData();

    // Actualiza cada segundo
    this.timerSubscription = interval(1000).subscribe(() => {
      this.tick();
    });
  }

  /**
   * Pausa el timer
   */
  pause() {
    if (this.currentState !== TimerState.RUNNING) {
      return;
    }

    this.currentState = TimerState.PAUSED;
    this.stopInterval();
    this.emitTimerData();
  }

  /**
   * Reanuda el timer
   */
  resume() {
    if (this.currentState !== TimerState.PAUSED) {
      return;
    }

    this.start();
  }

  /**
   * Detiene y reinicia el timer
   */
  stop() {
    this.currentState = TimerState.IDLE;
    this.currentTime = 0;
    this.targetTime = 0;
    this.currentRound = 0;
    this.isWorkPhase = true;
    this.phaseTime = 0;
    this.stopInterval();
    this.emitTimerData();
  }

  /**
   * Reinicia el timer con la misma configuración
   */
  reset() {
    this.stopInterval();
    
    switch (this.currentMode) {
      case TimerMode.STOPWATCH:
        this.currentTime = 0;
        break;
      case TimerMode.COUNTDOWN:
        this.currentTime = this.targetTime;
        break;
      case TimerMode.INTERVAL:
        if (this.intervalConfig) {
          this.currentRound = 1;
          this.isWorkPhase = true;
          this.phaseTime = this.intervalConfig.workTime;
          this.currentTime = this.intervalConfig.workTime;
        }
        break;
    }

    this.currentState = TimerState.IDLE;
    this.emitTimerData();
  }

  /**
   * Lógica de tick (cada segundo)
   */
  private tick() {
    switch (this.currentMode) {
      case TimerMode.STOPWATCH:
        this.currentTime++;
        break;

      case TimerMode.COUNTDOWN:
        this.currentTime--;
        if (this.currentTime <= 0) {
          this.currentTime = 0;
          this.finish();
        }
        break;

      case TimerMode.INTERVAL:
        this.handleIntervalTick();
        break;
    }

    this.emitTimerData();
  }

  /**
   * Maneja el tick para modo intervalo
   */
  private handleIntervalTick() {
    if (!this.intervalConfig) return;

    this.currentTime--;

    if (this.currentTime <= 0) {
      // Cambiar de fase o ronda
      if (this.isWorkPhase) {
        // Termina trabajo, empieza descanso
        this.isWorkPhase = false;
        this.currentTime = this.intervalConfig.restTime;
      } else {
        // Termina descanso
        this.currentRound++;
        
        if (this.currentRound > this.intervalConfig.rounds) {
          // Terminaron todas las rondas
          this.finish();
          return;
        }
        
        // Nueva ronda de trabajo
        this.isWorkPhase = true;
        this.currentTime = this.intervalConfig.workTime;
      }
    }
  }

  /**
   * Finaliza el timer
   */
  private finish() {
    this.currentState = TimerState.FINISHED;
    this.stopInterval();
    this.emitTimerData();
    
    // Opcional: reproducir sonido o vibración aquí
    this.playFinishSound();
  }

  /**
   * Detiene el intervalo
   */
  private stopInterval() {
    if (this.timerSubscription) {
      this.timerSubscription.unsubscribe();
      this.timerSubscription = undefined;
    }
  }

  /**
   * Formatea segundos a HH:MM:SS o MM:SS
   */
  private formatTime(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
      return `${this.pad(hours)}:${this.pad(minutes)}:${this.pad(secs)}`;
    } else {
      return `${this.pad(minutes)}:${this.pad(secs)}`;
    }
  }

  /**
   * Añade cero a la izquierda
   */
  private pad(num: number): string {
    return num.toString().padStart(2, '0');
  }

  /**
   * Obtiene los datos actuales del timer
   */
  private getTimerData(): TimerData {
    const data: TimerData = {
      mode: this.currentMode,
      state: this.currentState,
      currentTime: this.currentTime,
      displayTime: this.formatTime(this.currentTime)
    };

    if (this.currentMode === TimerMode.COUNTDOWN) {
      data.targetTime = this.targetTime;
    }

    if (this.currentMode === TimerMode.INTERVAL && this.intervalConfig) {
      data.currentRound = this.currentRound;
      data.totalRounds = this.intervalConfig.rounds;
      data.isWorkPhase = this.isWorkPhase;
    }

    return data;
  }

  /**
   * Emite los datos actuales
   */
  private emitTimerData() {
    this.timerData$.next(this.getTimerData());
  }

  /**
   * Reproduce sonido al finalizar (opcional)
   */
  private playFinishSound() {
    // Vibración si está disponible
    if ('vibrate' in navigator) {
      navigator.vibrate([200, 100, 200]);
    }
    
    // Aquí podrías añadir un audio
    // const audio = new Audio('assets/sounds/finish.mp3');
    // audio.play();
  }

  /**
   * Obtiene el estado actual
   */
  getCurrentState(): TimerState {
    return this.currentState;
  }

  /**
   * Obtiene el modo actual
   */
  getCurrentMode(): TimerMode {
    return this.currentMode;
  }
}