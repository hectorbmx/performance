import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
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
    path: 'user-profile',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/user-profile/user-profile.page').then( m => m.UserProfilePage)
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
    path: 'subscription-history',
    canMatch: [authGuard],
    loadComponent: () => import('./pages/subscription-history/subscription-history.page').then( m => m.SubscriptionHistoryPage)
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
    loadComponent: () => import('./pages/activate/activate.page').then( m => m.ActivatePage)
  },
  // ⚠️ IMPORTANTE: La ruta wildcard siempre debe ir AL FINAL
  {
    path: '**',
    redirectTo: 'login',
  },
  
];