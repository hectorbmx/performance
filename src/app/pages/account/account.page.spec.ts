import { signal } from '@angular/core';
import { AccountPage } from './account.page';
import { WorkoutOfflineStore } from '../../services/workout-offline.store';
import { AUTH_API_SCOPE } from '../../services/auth-storage';

describe('Account deletion acknowledgement',()=>{
  let page:AccountPage;let api:any;let auth:any;let router:any;let remove:jasmine.Spy;
  beforeEach(()=>{
    api={request:jasmine.createSpy().and.resolveTo({message:'Solicitud recibida.'})};
    auth={user:signal({id:3}),pendingWorkoutChanges:signal(2),logout:jasmine.createSpy().and.resolveTo()};
    router={navigateByUrl:jasmine.createSpy().and.resolveTo(true)};
    remove=spyOn(WorkoutOfflineStore.prototype,'removeAthlete').and.resolveTo();
    page=new AccountPage(auth,api,router,{create:async()=>({present:async()=>{}})} as any);
    page.password='Password123!';page.confirmation=true;
  });
  it('preserves local data and session when the server rejects or cannot acknowledge deletion',async()=>{
    api.request.and.rejectWith({message:'Sin conexión'});await page.deleteAccount();
    expect(remove).not.toHaveBeenCalled();expect(auth.logout).not.toHaveBeenCalled();expect(page.error).toBe('Sin conexión');
  });
  it('clears only the pinned athlete after server acceptance and closes the session first',async()=>{
    remove.and.callFake(async()=>{expect(auth.logout).toHaveBeenCalled();});
    await page.deleteAccount();
    expect(api.request).toHaveBeenCalledWith('DELETE','app/account',{body:{password:'Password123!',confirm:true}});
    expect(remove).toHaveBeenCalledWith(AUTH_API_SCOPE,3);expect(router.navigateByUrl).toHaveBeenCalledWith('/login',{replaceUrl:true});
    expect(page.password).toBe('');expect(auth.pendingWorkoutChanges()).toBe(0);
  });
  it('keeps the session closed even when this device refuses local cleanup',async()=>{
    remove.and.rejectWith(new Error('Storage unavailable'));await page.deleteAccount();
    expect(auth.logout).toHaveBeenCalled();expect(router.navigateByUrl).toHaveBeenCalledWith('/login',{replaceUrl:true});
  });
});
