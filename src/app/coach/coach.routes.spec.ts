import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { routes } from './coach.routes';

@Component({standalone:true,template:'Perfil del atleta'}) class AthleteDetail {}

describe('Retired coach GYM permissions',()=>{
  it('redirects an old generator bookmark to the same athlete without loading the permissions panel',async()=>{
    const redirect=routes[0].children!.find(route=>route.path==='athletes/:id/generator')!;
    TestBed.configureTestingModule({providers:[provideRouter([{path:'coach',children:[redirect,{path:'athletes/:id',component:AthleteDetail}]}])]});
    const harness=await RouterTestingHarness.create();
    await harness.navigateByUrl('/coach/athletes/5/generator',AthleteDetail);
    expect(TestBed.inject(Router).url).toBe('/coach/athletes/5');
  });
});
