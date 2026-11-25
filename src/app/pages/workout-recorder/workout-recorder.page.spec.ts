import { ToastController } from '@ionic/angular/standalone';
import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { ActivatedRoute, Router, provideRouter, convertToParamMap } from '@angular/router';
import { WorkoutRecorderPage } from './workout-recorder.page';
import { WorkoutOfflineService } from '../../services/workout-offline.service';

describe('WorkoutRecorderPage save confirmation', () => {
  let page: WorkoutRecorderPage;
  let save: jasmine.Spy;
  beforeEach(() => {
    save = jasmine.createSpy('save').and.resolveTo();
    TestBed.configureTestingModule({ providers: [
      {provide:ToastController,useValue:{create:async()=>({present:async()=>undefined})}},
      { provide: WorkoutOfflineService, useValue: { owner: signal('owner'), state: signal(null), save } },
      { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap({}) } } },
      { provide: Router, useValue: { navigate: jasmine.createSpy().and.resolveTo(true) } },
    ] });
    page = TestBed.runInInjectionContext(() => new WorkoutRecorderPage());
    TestBed.flushEffects(); page.selected.set('execution'); page.editing = 'set'; page.dirty = true;
    page.form.actual_reps = 10;
  });
  it('rejects incompatible barbell values before adding them to the offline queue',async()=>{
    page.editingAllowsBarbell=false;page.form.load_mode='total_barbell';page.form.actual_load=0;
    await page.save();expect(save).not.toHaveBeenCalled();expect(page.dirty).toBeTrue();expect(page.error).toContain('Sin carga registrada');
  });
  it('keeps the modal and form when discarding changes is rejected',async()=>{
    spyOn(window,'confirm').and.returnValue(false);expect(await page.canDismissSeries()).toBeFalse();
    expect(page.editing).toBe('set');expect(page.dirty).toBeTrue();expect(page.form.actual_reps).toBe(10);
  });
  it('allows modal dismissal after a confirmed local commit even while the action finishes',async()=>{
    page.busy=true;expect(await page.canDismissSeries()).toBeFalse();
    page.editing=null;page.dirty=false;expect(await page.canDismissSeries()).toBeTrue();
  });
  it('shows an advisory without turning suggested load into performed load',async()=>{
    const request=jasmine.createSpy().and.resolveTo({data:{suggested_load:{value:'70',unit:'kg'},reason:'Revisar',rule_version:'v1'}});
    (page.workouts as any).appRequest=request;
    (page.workouts.state as any).set({executions:{execution:{execution:{prescription:{content:{source:'athlete_generated',equipment:['barbell'],sections:[{exercises:[{workout_exercise_id:1,sets:[{uuid:'set',prescribed:{reps_min:8,reps_max:12,rir:2,rest_seconds:90}}]}]}]}}},records:{}}}});
    page.form.actual_load='';await page.consultLoad();
    expect(page.advisory?.suggested_load?.value).toBe('70');expect(page.form.actual_load).toBe('');expect(save).not.toHaveBeenCalled();
    page.form.actual_reps=5;expect(page.belowTarget()).toBeTrue();
  });
  it('does not confirm saved until the local transaction finishes', async () => {
    let commit!: () => void;
    save.and.returnValue(new Promise<void>(resolve => { commit = resolve; }));
    const saving = page.save();
    expect(page.dirty).toBeTrue(); expect(page.message).toBe(''); expect(page.busy).toBeTrue();
    commit(); await saving;
    expect(page.dirty).toBeFalse(); expect(page.editing).toBeNull(); expect(page.message).toContain('guardada en el dispositivo');
  });
  it('keeps the form when local storage rejects a save', async () => {
    save.and.rejectWith(new DOMException('Quota exceeded', 'QuotaExceededError'));
    await page.save();
    expect(page.dirty).toBeTrue(); expect(page.editing).toBe('set'); expect(page.error).toContain('Quota'); expect(page.message).toBe('');
  });
  it('keeps empty optional numeric controls absent instead of changing them to zero', async () => {
    (page.form as any).actual_rir = null; (page.form as any).actual_load = null;
    await page.save();
    const body = save.calls.mostRecent().args[2];
    expect(body.actual_load).toBeNull(); expect(body.actual_rir).toBeNull(); expect(body.load_unit).toBeNull();
    expect(body.performed_at).toMatch(/T\d{2}:\d{2}:\d{2}Z$/);
  });
  it('requires explicit repetitions instead of using prescribed ones', async () => {
    page.form.actual_reps = ''; await page.save();
    expect(save).not.toHaveBeenCalled(); expect(page.dirty).toBeTrue(); expect(page.error).toContain('repeticiones');
  });
  it('saves an omitted series without performed values', async () => {
    page.form.status = 'skipped'; page.form.actual_load = 75; page.form.actual_rir = 2;
    await page.save(); const body = save.calls.mostRecent().args[2];
    expect(body.actual_reps).toBeNull(); expect(body.actual_load).toBeNull(); expect(body.actual_rir).toBeNull();
  });
});

describe('Workout execution layout',()=>{
  it('shows execution first, hides local history and opens series registration in a modal',async()=>{
    const state=signal<any>({executions:{execution:{records:{},execution:{uuid:'execution',status:'in_progress',started_at:'2026-11-15T12:00:00Z',
      prescription:{content:{title:'Piernas',sections:[{uuid:'section',name:'Rutina personalizada',exercises:[{uuid:'exercise',name:'Sentadilla',notes:null,
        sets:[{uuid:'set',set_number:1,prescribed:{reps_min:8,reps_max:12,rir:2,rest_seconds:90,percentage:null}}]}]}]}}}}},queue:[]});
    await TestBed.configureTestingModule({imports:[WorkoutRecorderPage],providers:[
      {provide:ToastController,useValue:{create:async()=>({present:async()=>undefined})}},
      {provide:WorkoutOfflineService,useValue:{useScope:async()=>{},selectExecution:async()=>{},migrationNotice:signal(null),hasAssigned:signal(false),owner:signal('owner'),state,error:signal(null),syncing:signal(false)}},
      {provide:ActivatedRoute,useValue:{snapshot:{queryParamMap:convertToParamMap({execution:'execution'})}}},
      provideRouter([]),
    ]}).compileComponents();
    const fixture=TestBed.createComponent(WorkoutRecorderPage);fixture.componentInstance.selected.set('execution');fixture.detectChanges();
    const main=fixture.nativeElement.querySelector('main');expect(main.querySelector('section h2').textContent).toContain('Rutina personalizada');
    expect(main.textContent).not.toContain('Historial en este dispositivo');expect(main.querySelector('form')).toBeNull();
    main.querySelector('.set .register-button').click();fixture.detectChanges();expect(fixture.componentInstance.editing).toBe('set');
    expect(fixture.nativeElement.querySelector('ion-modal').isOpen).toBeTrue();
    fixture.componentInstance.dirty=false;fixture.componentInstance.editing=null;fixture.detectChanges();fixture.destroy();
  });
});
