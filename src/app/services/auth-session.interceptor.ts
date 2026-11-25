import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject, Injector } from '@angular/core';
import { catchError, from, mergeMap, throwError } from 'rxjs';
import { AuthService } from './auth.service';
import { AUTH_API_SCOPE } from './auth-storage';

export const authSessionInterceptor: HttpInterceptorFn = (request,next) => {
  const injector=inject(Injector);
  const token=request.headers.get('Authorization')?.match(/^Bearer (.+)$/)?.[1];
  const ownApi=request.url.startsWith(AUTH_API_SCOPE+'/');
  const login=/\/(app|coach)\/login(?:[?]|$)/.test(request.url);
  return next(request).pipe(catchError(error=>{
    if(error instanceof HttpErrorResponse && error.status===401 && token && ownApi && !login) {
      return from(injector.get(AuthService).handleUnauthorized(token)).pipe(
        catchError(()=>from(Promise.resolve())),
        mergeMap(()=>throwError(()=>error)),
      );
    }
    return throwError(()=>error);
  }));
};
