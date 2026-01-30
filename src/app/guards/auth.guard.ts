import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { CanMatchFn } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanMatchFn = async (route, segments) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  console.log('🔒 AuthGuard ejecutándose...');
  
  const isLoggedIn = await authService.isLoggedIn();
  console.log('🔒 Usuario logueado:', isLoggedIn);

  if (!isLoggedIn) {
    console.log('🔒 Acceso denegado. Redirigiendo a /login');
    await router.navigateByUrl('/login', { replaceUrl: true });
    return false;
  }

  console.log('🔒 Acceso permitido a:', segments.map(s => s.path).join('/'));
  return true;
};