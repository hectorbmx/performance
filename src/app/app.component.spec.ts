import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { provideRouter } from '@angular/router';
import { AppComponent } from './app.component';
import { AuthService } from './services/auth.service';
import { WorkoutOfflineService } from './services/workout-offline.service';
import { PushRegistrationService } from './services/push-registration.service';

describe('AppComponent session notice',()=>{
  it('offers retry and local logout when the server cannot verify the session',async()=>{
    const auth={sessionNotice:signal('No se pudo verificar la sesión'),sessionState:signal('offline'),revalidateSession:jasmine.createSpy().and.resolveTo(),logout:jasmine.createSpy().and.resolveTo()};
    await TestBed.configureTestingModule({imports:[AppComponent],providers:[provideRouter([]),
      {provide:AuthService,useValue:auth},{provide:WorkoutOfflineService,useValue:{}},
      {provide:PushRegistrationService,useValue:{init:()=>undefined}},
    ]}).compileComponents();
    const fixture=TestBed.createComponent(AppComponent);fixture.detectChanges();
    const buttons=fixture.nativeElement.querySelectorAll('[role="status"] button');expect(buttons.length).toBe(2);
    buttons[0].click();expect(auth.revalidateSession).toHaveBeenCalled();
    auth.sessionState.set('anonymous');fixture.detectChanges();expect(fixture.nativeElement.querySelectorAll('[role="status"] button').length).toBe(0);
    fixture.destroy();
  });
});
