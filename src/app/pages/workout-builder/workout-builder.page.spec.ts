import { ToastController } from '@ionic/angular/standalone';
import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { Router } from '@angular/router';
import { WorkoutBuilderPage } from './workout-builder.page';
import { WorkoutOfflineService } from '../../services/workout-offline.service';
import { BuilderExercise } from '../../services/workout-builder.types';

describe('WorkoutBuilderPage',()=>{
  let page:WorkoutBuilderPage;let save:jasmine.Spy;let app:jasmine.Spy;let start:jasmine.Spy;let router:jasmine.SpyObj<Router>;
  beforeEach(()=>{
    save=jasmine.createSpy().and.resolveTo();app=jasmine.createSpy().and.resolveTo({data:{candidates:[],message:null}});start=jasmine.createSpy().and.resolveTo('generated');
    router=jasmine.createSpyObj<Router>('Router',['navigate']);router.navigate.and.resolveTo(true);
    TestBed.configureTestingModule({providers:[{provide:ToastController,useValue:{create:async()=>({present:async()=>undefined,dismiss:async()=>undefined,onDidDismiss:()=>new Promise(()=>{})})}},{provide:Router,useValue:router},{provide:WorkoutOfflineService,useValue:{useScope:async()=>{},owner:signal(null),state:signal(null),saveBuilderDraft:save,appRequest:app,startGenerated:start}}]});
    page=TestBed.runInInjectionContext(()=>new WorkoutBuilderPage());TestBed.flushEffects();
  });
  function exercise(id:number):BuilderExercise{return {exercise_id:id,name:'Ejercicio '+id,load_mode:'total_barbell',sets:3,reps_min:8,reps_max:12,target_rir:2,rest_seconds:90,unit:'kg',increment:'2.50',chosen_load:'75',notes:'',suggestion_id:null};}
  it('completes pull to refresh even on failure and preserves the draft',async()=>{
    const draft=page.draft;draft.title='Mi borrador';draft.equipment=['barbell'];
    spyOn(page,'load').and.rejectWith(new Error('Offline'));const complete=jasmine.createSpy().and.resolveTo();
    await expectAsync(page.onRefresh({target:{complete}} as any)).toBeRejected();expect(complete).toHaveBeenCalled();
    expect(page.draft).toBe(draft);expect(page.draft.equipment).toEqual(['barbell']);
  });
  it('asks for every required item without adding equipment or exercises on cancellation',async()=>{
    page.catalog=[{id:1,names:{es:'Sentadilla'},terms:[{kind:'equipment',code:'barbell',names:{es:'Barra'}},{kind:'equipment',code:'rack',names:{es:'Rack'}}]} as any];
    await page.add({id:1,code:'squat',names:{es:'Sentadilla'},score:0,reasons:[]});
    expect(page.equipmentExercise?.id).toBe(1);expect(app).not.toHaveBeenCalled();expect(save).not.toHaveBeenCalled();
    page.equipmentChecks['barbell']=true;expect(page.equipmentConfirmed).toBeFalse();await page.confirmEquipment();expect(app).not.toHaveBeenCalled();
    page.equipmentExercise=null;expect(page.draft.equipment).toEqual([]);expect(page.draft.exercises).toEqual([]);
  });
  it('adds a bodyweight exercise directly without a redundant equipment dialog or invented load',async()=>{
    page.catalog=[{id:1,names:{es:'Sin carga'},load_mode:'bodyweight',terms:[{kind:'equipment',code:'bodyweight',names:{es:'Sin equipo'}}]} as any];
    app.and.callFake(async(_method:string,path:string)=>path.includes('prescription')?{data:{prescribed:{sets:3,reps_min:8,reps_max:12,target_rir:2,rest_seconds:90},suggestion_id:'id'}}:{data:{candidates:[],message:null}});
    await page.add({id:1,code:'bodyweight',names:{es:'Sin carga'},score:0,reasons:[]});
    expect(page.equipmentExercise).toBeNull();expect(page.draft.equipment).toEqual([]);expect(page.draft.exercises[0].chosen_load).toBeNull();
  });
  it('adds the exercise only after confirming all equipment and rejects silent removal',async()=>{
    page.catalog=[{id:1,names:{es:'Sentadilla'},load_mode:'total_barbell',terms:[{kind:'equipment',code:'barbell',names:{es:'Barra'}},{kind:'equipment',code:'rack',names:{es:'Rack'}}]} as any];
    app.and.callFake(async(_method:string,path:string)=>path.includes('prescription')?{data:{prescribed:{sets:3,reps_min:8,reps_max:12,target_rir:2,rest_seconds:90},suggestion_id:'id'}}:{data:{candidates:[],message:null}});
    await page.add({id:1,code:'squat',names:{es:'Sentadilla'},score:0,reasons:[]});page.equipmentChecks={barbell:true,rack:true};await page.confirmEquipment();
    expect(page.draft.exercises.length).toBe(1);expect(page.draft.equipment).toEqual(['barbell','rack']);
    const target={checked:false};await page.toggleEquipment('rack',{target} as any);
    expect(page.equipmentRemoval?.names).toEqual(['Sentadilla']);expect(page.draft.equipment).toContain('rack');expect(target.checked).toBeTrue();
    await page.confirmEquipmentRemoval();expect(page.draft.exercises.length).toBe(0);expect(page.draft.equipment).not.toContain('rack');
  });
  it('looks up a maximum inline without inventing a performed load',async()=>{
    app.and.resolveTo({basis:null});const item=exercise(1);item.chosen_load=null;
    await page.openInlineRm(item);expect(app).toHaveBeenCalledWith('GET','/exercise-maxes?exercise_id=1');
    expect(page.inlineRmExercise).toBe(1);expect(page.notice).toContain('continuar sin RM');expect(item.chosen_load).toBeNull();
  });
  it('does not store a maximum for an unsupported load mode',async()=>{
    page.rmExercise=1;page.rmValue=50;page.rmConfirmed=true;await page.saveRm();
    expect(app).not.toHaveBeenCalled();expect(page.error).toContain('continuar sin máximo');
  });
  it('reorders and substitutes by removing an exercise without losing the other parameters',async()=>{
    page.draft.exercises=[exercise(1),exercise(2),exercise(3)];await page.move(0,1);
    expect(page.draft.exercises.map(e=>e.exercise_id)).toEqual([2,1,3]);
    await page.remove(1);expect(page.draft.exercises.map(e=>e.exercise_id)).toEqual([2,3]);expect(page.draft.exercises[0].sets).toBe(3);expect(save).toHaveBeenCalled();
  });
  it('clears a programmed load when changing its unit instead of reinterpreting kg as lb',async()=>{
    const item=exercise(1);item.unit='lb';await page.unitChanged(item);expect(item.chosen_load).toBeNull();
  });
  it('keeps a failed draft visibly unsaved and asks before navigation',async()=>{
    save.and.rejectWith(new Error('No space'));await page.changed();spyOn(window,'confirm').and.returnValue(false);
    expect(page.canDeactivate()).toBeFalse();expect(page.error).toContain('No space');
  });
  it('serializes the edited prescription separately from recorded values and opens its execution',async()=>{
    page.draft.exercises=[exercise(1)];await page.prepare();
    const body=start.calls.mostRecent().args[1];expect(body.exercises[0].chosen_load).toBe('75');
    expect(body.exercises[0].actual_load).toBeUndefined();expect(router.navigate).toHaveBeenCalledWith(['/workout-recorder'],{queryParams:{execution:'generated'}});
  });
});
