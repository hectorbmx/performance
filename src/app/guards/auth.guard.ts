import { inject } from '@angular/core';
import { Router, CanMatchFn, RedirectCommand } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanMatchFn = async (_route, segments) => {
  const auth=inject(AuthService);const router=inject(Router);
  if(!await auth.isLoggedIn()) return new RedirectCommand(router.createUrlTree(['/login']), {
    replaceUrl:true,state:{redirectUrl:'/'+segments.map(segment=>segment.path).join('/')},
  });
  const actor=await auth.getActorType();
  if(segments[0]?.path==='coach' && actor!=='coach') return router.parseUrl(await auth.getDefaultRoute());
  if(actor==='client' && auth.context?.()?.client_id === null) {
    const first=segments[0]?.path;
    if(first==='tabs' && !['gym','tab2'].includes(segments[1]?.path))return router.parseUrl('/tabs/gym');
    if(first!=='tabs'&&!['account','timer','workout-builder','workout-recorder','workout-progress'].includes(first))return router.parseUrl('/tabs/gym');
  }
  if(actor==='coach' && segments[0]?.path==='account') return router.parseUrl(await auth.getDefaultRoute());
  if(actor==='coach' && ['tabs','workout-builder','workout-recorder','workout-progress'].includes(segments[0]?.path))
    return router.parseUrl(await auth.getDefaultRoute());
  return true;
};
