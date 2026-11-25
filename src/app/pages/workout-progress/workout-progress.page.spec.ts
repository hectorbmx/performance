import { TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { WorkoutOfflineService } from '../../services/workout-offline.service';
import { WorkoutProgressPage } from './workout-progress.page';
describe('WorkoutProgressPage',()=>{
  let page:WorkoutProgressPage;let request:jasmine.Spy;
  beforeEach(()=>{
    request=jasmine.createSpy().and.callFake(async(method:string,path:string)=>(path.includes('exercise-catalog')||path.endsWith('/exercises'))?{data:[{id:1,code:'lower_body_back_squat',load_mode:'total_barbell',names:{es:'Sentadilla'}}]}:{data:{name:'Sentadilla',points:[{kind:'tested',date:'2026-11-01',value_kg:'100',method:'single_rep_reported',confidence:'declared'}],goals:[],limits:'Orientativo',history_window_days:45}});
    TestBed.configureTestingModule({providers:[{provide:ActivatedRoute,useValue:{snapshot:{paramMap:{get:()=>null}}}},{provide:ApiService,useValue:{}},{provide:WorkoutOfflineService,useValue:{appRequest:request,useScope:async()=>{}}}]});
    page=TestBed.runInInjectionContext(()=>new WorkoutProgressPage());
  });
  it('does not invent future points or connect an extrapolated improvement curve',async()=>{
    await page.ionViewWillEnter();expect(page.points.length).toBe(1);expect(page.points[0].kind).toBe('tested');expect(page.progress?.goals).toEqual([]);
    expect(Number.isFinite(page.points[0].x)).toBeTrue();expect(Number.isFinite(page.points[0].y)).toBeTrue();
  });
  it('retries an uncertain goal with exactly the same request and fields',async()=>{
    await page.ionViewWillEnter();page.goal.load='80';page.goal.scheduled_for='2026-12-01';
    const calls:unknown[]=[];request.and.callFake(async(method:string,path:string,body:unknown)=>{calls.push(structuredClone(body));throw {status:0,message:'offline'};});
    await page.saveGoal();const first=structuredClone(page.pendingGoal);page.goal.load='90';await page.saveGoal();
    expect(page.pendingGoal).toEqual(first);expect(calls[0]).toEqual(calls[1]);
    spyOn(window,'confirm').and.returnValue(false);expect(page.canDeactivate()).toBeFalse();
  });
});
