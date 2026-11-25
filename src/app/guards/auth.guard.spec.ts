import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { authGuard } from './auth.guard';
import { AuthService } from '../services/auth.service';
@Component({standalone:true,template:'Entrenamientos'}) class ProtectedPage {}
@Component({standalone:true,template:'Login'}) class LoginPage {}
@Component({standalone:true,template:'Atleta'}) class AthletePage {}
describe('Auth route entry',()=>{
  let auth:any;
  beforeEach(()=>{
    auth={isLoggedIn:jasmine.createSpy().and.resolveTo(false),getActorType:jasmine.createSpy().and.resolveTo('coach'),getDefaultRoute:jasmine.createSpy().and.resolveTo('/tabs')};
    TestBed.configureTestingModule({providers:[{provide:AuthService,useValue:auth},provideRouter([
      {path:'coach',canMatch:[authGuard],children:[{path:'trainings',component:ProtectedPage}]},
      {path:'tabs',component:AthletePage},{path:'login',component:LoginPage},
    ])]});
  });
  it('redirects a direct reload of a private URL to login when validation rejects the session',async()=>{
    const harness=await RouterTestingHarness.create();await harness.navigateByUrl('/coach/trainings',LoginPage);
    expect(TestBed.inject(Router).url).toBe('/login');
  });
  it('opens coach pages only after validation succeeds',async()=>{
    auth.isLoggedIn.and.resolveTo(true);const harness=await RouterTestingHarness.create();await harness.navigateByUrl('/coach/trainings',ProtectedPage);
    expect(auth.isLoggedIn).toHaveBeenCalled();
  });
  it('keeps a valid athlete out of coach pages',async()=>{
    auth.isLoggedIn.and.resolveTo(true);auth.getActorType.and.resolveTo('client');
    const harness=await RouterTestingHarness.create();await harness.navigateByUrl('/coach/trainings',AthletePage);
    expect(TestBed.inject(Router).url).toBe('/tabs');
  });
  it('routes an independent account away from tenant screens',async()=>{
    auth.isLoggedIn.and.resolveTo(true);auth.getActorType.and.resolveTo('client');
    auth.context=()=>({user_app_id:9,client_id:null,coach_id:null});auth.getDefaultRoute.and.resolveTo('/tabs/gym');
    TestBed.inject(Router).resetConfig([
      {path:'training-details/:id',canMatch:[authGuard],component:ProtectedPage},
      {path:'tabs',children:[{path:'gym',component:AthletePage}]},
    ]);
    const harness=await RouterTestingHarness.create();await harness.navigateByUrl('/training-details/2',AthletePage);
    expect(TestBed.inject(Router).url).toBe('/tabs/gym');
  });
});
