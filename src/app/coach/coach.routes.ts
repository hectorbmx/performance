import { Routes } from '@angular/router';
import { CoachShellPage } from './coach-shell/coach-shell.page';

export const routes: Routes = [
  {
    path: '',
    component: CoachShellPage,
    children: [
      {
        path: 'athletes',
        loadComponent: () =>
          import('./pages/athletes/coach-athletes.page').then((m) => m.CoachAthletesPage),
      },
      {
        path: 'athletes/:id',
        loadComponent: () =>
          import('./pages/athlete-detail/coach-athlete-detail.page').then((m) => m.CoachAthleteDetailPage),
      },
      {
        path: 'trainings',
        loadComponent: () =>
          import('./pages/trainings/coach-trainings.page').then((m) => m.CoachTrainingsPage),
      },
      {
        path: 'groups',
        loadComponent: () =>
          import('./pages/groups/coach-groups.page').then((m) => m.CoachGroupsPage),
      },
      {
        path: 'plans',
        loadComponent: () =>
          import('./pages/plans/coach-plans.page').then((m) => m.CoachPlansPage),
      },
      {
        path: 'subscriptions',
        loadComponent: () =>
          import('./pages/subscriptions/coach-subscriptions.page').then((m) => m.CoachSubscriptionsPage),
      },
      {
        path: 'library',
        loadComponent: () =>
          import('./pages/library/coach-library.page').then((m) => m.CoachLibraryPage),
      },
      {
        path: '',
        redirectTo: 'athletes',
        pathMatch: 'full',
      },
    ],
  },
];
