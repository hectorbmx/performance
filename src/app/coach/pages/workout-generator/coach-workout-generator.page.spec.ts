import { TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { ToastController } from '@ionic/angular/standalone';
import { CatalogExercise } from '../../../services/workout-builder.types';
import { ApiService } from '../../../services/api.service';
import { CoachWorkoutGeneratorPage } from './coach-workout-generator.page';

describe('Coach generator permission confirmation',()=>{
  let page:CoachWorkoutGeneratorPage;let put:jasmine.Spy;let toast:jasmine.Spy;
  beforeEach(()=>{
    put=jasmine.createSpy();toast=jasmine.createSpy().and.resolveTo({present:async()=>undefined});
    TestBed.configureTestingModule({providers:[{provide:ApiService,useValue:{put}},{provide:ActivatedRoute,useValue:{}},{provide:ToastController,useValue:{create:toast}}]});
    page=TestBed.runInInjectionContext(()=>new CoachWorkoutGeneratorPage());page.athleteId=5;page.settings.generator_enabled=true;page.permissionsChanged();
  });
  it('confirms only after the save and distinguishes saved permission from server activation',async()=>{
    let commit!:(value:unknown)=>void;put.and.returnValue(new Promise(resolve=>commit=resolve));
    const saving=page.saveSettings();expect(toast).not.toHaveBeenCalled();expect(page.permissionsDirty).toBeTrue();
    commit({data:{generator_enabled:true,feature_enabled:false,executions_enabled:false,blocked_exercise_codes:[],blocked_demands:[]}});await saving;
    expect(page.permissionsDirty).toBeFalse();expect(page.settings.generator_enabled).toBeTrue();
    expect(toast.calls.mostRecent().args[0].color).toBe('success');expect(toast.calls.mostRecent().args[0].message).toContain('desactivado en el servidor');
    expect(put.calls.mostRecent().args[1].feature_enabled).toBeUndefined();
  });
  it('marks saved eligibility and distinguishes review, exclusion, demands and profile',async()=>{
    const profile={region:'lower_body',goal:'hypertrophy',level:'intermediate'};
    const base={id:1,code:'allowed',names:{es:'Sentadilla'},load_mode:'bodyweight',fatigue:'low',unilateral:false,variant_of_id:null,
      review_status:'reviewed',is_active:true,scope:'coach',can_edit:true,notes:null,
      terms:Object.entries(profile).map(([kind,code])=>({kind,code,role:'profile',names:{es:code}}))};
    page.catalog=[base,{...base,id:2,code:'pending',review_status:'pending'},{...base,id:3,code:'blocked'},
      {...base,id:4,code:'demand',terms:[...base.terms,{kind:'demand',code:'knee_motion',role:'demand',names:{es:'Rodilla'}}]},
      {...base,id:5,code:'wrong_profile',terms:[]}];
    put.and.resolveTo({data:{generator_enabled:true,feature_enabled:true,executions_enabled:true,profile,blocked_exercise_codes:['blocked'],blocked_demands:['knee_motion']}});
    await page.saveSettings();expect(page.enabledExerciseCount).toBe(1);expect(page.availability['allowed'].enabled).toBeTrue();
    expect(page.availability['pending'].reasons).toContain('Pendiente de revisión');expect(page.availability['blocked'].reasons).toContain('Excluido para este atleta');
    expect(page.availability['demand'].enabled).toBeFalse();expect(page.availability['wrong_profile'].enabled).toBeFalse();
    page.settings.blocked_exercise_codes.push('allowed');page.permissionsChanged();expect(page.availability['allowed'].enabled).toBeTrue();
  });
  function privateExercise():CatalogExercise{return {id:52,code:'coach_7_lunge',scope:'coach',can_edit:true,names:{es:'Zancadas caminando con mancuernas'},
    load_mode:'per_dumbbell',fatigue:'moderate',unilateral:true,variant_of_id:null,review_status:'pending',is_active:true,notes:null,terms:[]};}
  it('saves an explicit review confirmation and names the exercise in its success toast',async()=>{
    const exercise=privateExercise();page.open(exercise);page.reviewChanged(true);
    spyOn<any>(page,'loadData').and.resolveTo();put.and.resolveTo({data:{...exercise,review_status:'reviewed'}});
    await page.saveExercise();expect(put.calls.mostRecent().args[1].review_status).toBe('reviewed');
    const feedback=toast.calls.mostRecent().args[0];expect(feedback.color).toBe('success');expect(feedback.message).toContain('Zancadas caminando con mancuernas');expect(feedback.message).toContain('guardado y revisado');
  });
  it('does not describe a pending save as enabled or reviewed',async()=>{
    const exercise=privateExercise();page.open(exercise);spyOn<any>(page,'loadData').and.resolveTo();put.and.resolveTo({data:exercise});
    await page.saveExercise();expect(put.calls.mostRecent().args[1].review_status).toBe('pending');
    const feedback=toast.calls.mostRecent().args[0];expect(feedback.color).toBe('warning');expect(feedback.message).toContain('Aún no aparece al atleta');
  });
  it('keeps changes pending and shows an error if saving fails',async()=>{
    put.and.rejectWith(new Error('No se pudo guardar'));await page.saveSettings();
    expect(page.permissionsDirty).toBeTrue();expect(page.message).toBe('');expect(toast.calls.mostRecent().args[0].color).toBe('danger');
  });
});
