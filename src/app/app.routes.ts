import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: 'register', data: {mode: 'register'}, loadComponent: () => import('./pages/account-access/account-access.page').then(m => m.AccountAccessPage) },
  { path: 'verify-email', data: {mode: 'verify'}, loadComponent: () => import('./pages/account-access/account-access.page').then(m => m.AccountAccessPage) },
  { path: 'recover-password', data: {mode: 'recover'}, loadComponent: () => import('./pages/account-access/account-access.page').then(m => m.AccountAccessPage) },
  { path: 'account', canMatch: [authGuard], loadComponent: () => import('./pages/account/account.page').then(m => m.AccountPage) },
  { path: 'privacy', loadComponent: () => import('./pages/account/privacy.page').then(m => m.PrivacyPage) },
  {
    path: '',
    redirectTo: 'login',
    pathMatch: 'full',
  },
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.page').then(m => m.LoginPage)
  },
  {
    path: 'tabs',
    canMatch: [authGuard],
    loadChildren: () => import('./tabs/tabs.routes').then(m => m.routes),
  },
  {
    path: 'coach',
    canMatch: [authGuard],
    loadChildren: () => import('./coach/coach.routes').then(m => m.routes),
  },
  {
    path: 'user-profile',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/user-profile/user-profile.page').then( m => m.UserProfilePage)
  },
  {
    path: 'workout-progress', canMatch: [authGuard],
    canDeactivate: [(component: { canDeactivate: () => boolean }) => component.canDeactivate()],
    loadComponent: () => import('./pages/workout-progress/workout-progress.page').then(m => m.WorkoutProgressPage),
  },
  {
    path: 'workout-builder', canMatch: [authGuard],
    canDeactivate: [(component: { canDeactivate: () => boolean }) => component.canDeactivate()],
    loadComponent: () => import('./pages/workout-builder/workout-builder.page').then(m => m.WorkoutBuilderPage),
  },
  {
    path: 'workout-recorder',
    canMatch: [authGuard],
    canDeactivate: [(component: { canDeactivate: () => boolean }) => component.canDeactivate()],
    loadComponent: () => import('./pages/workout-recorder/workout-recorder.page').then(m => m.WorkoutRecorderPage),
  },
  {
    path: 'training-details/:assignmentId',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/training-details/training-details.page').then(m => m.TrainingDetailsPage)
  },
  {
    path: 'training-details/free/:sessionId',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/training-details/training-details.page').then(m => m.TrainingDetailsPage),
  },
  {
    path: 'tips/:id',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/tip-detail/tip-detail.page').then(m => m.TipDetailPage),
  },
  {
    path: 'subscription-history',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/subscription-history/subscription-history.page').then( m => m.SubscriptionHistoryPage)
  },
  {
    path: 'health-history/:metric',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/health-history/health-history.page').then(m => m.HealthHistoryPage)
  },
  {
    path: 'activity-calendar',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/activity-calendar/activity-calendar.page').then(m => m.ActivityCalendarPage)
  },
  // Timer route - OPCIÓN 1: Sin autenticación (accesible sin login)
  // {
  //   path: 'timer',
  //   loadComponent: () => import('./pages/timer/timer.page').then(m => m.TimerPage)
  // },
  // Timer route - OPCIÓN 2: Con autenticación (requiere login) - Descomenta esta y comenta la de arriba
  {
    path: 'timer',
    canMatch: [authGuard],
    // loadComponent: () => import('./pages/timer/timer.page').then(m => m.TimerPage)
    loadComponent: () => import('./pages/timer/timer.page').then(m => m.TimerPage)
  },
  {
    path: 'activate',
    redirectTo: 'login',
  },
  // ⚠️ IMPORTANTE: La ruta wildcard siempre debe ir AL FINAL
  {
    path: '**',
    redirectTo: 'login',
  },

  
];
