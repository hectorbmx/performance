import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { IonBackButton,IonButton,IonButtons,IonContent,IonHeader,IonTitle,IonToolbar } from '@ionic/angular/standalone';
import { ApiService } from '../../services/api.service';
import { WorkoutOfflineService } from '../../services/workout-offline.service';
import { CatalogExercise } from '../../services/workout-builder.types';
interface Point {kind:string;date:string;value_kg:string;method:string;confidence:string;source?:string;author?:string;load_original?:string;unit?:string;reps?:number;rir?:string;}
interface Progress {name:string;points:Point[];goals:Point[];limits:string;history_window_days:number;}
@Component({selector:'app-workout-progress',standalone:true,imports:[CommonModule,FormsModule,IonBackButton,IonButton,IonButtons,IonContent,IonHeader,IonTitle,IonToolbar],
  templateUrl:'./workout-progress.page.html',styleUrls:['./workout-progress.page.scss']})
export class WorkoutProgressPage {
  private route=inject(ActivatedRoute); private api=inject(ApiService); private workouts=inject(WorkoutOfflineService);
  coachId=0;busy=false;error='';message='';catalog:CatalogExercise[]=[];exerciseId=0;progress:Progress|null=null;
  points:Array<Point&{x:number;y:number}>=[]; sessions:Array<{uuid:string;title:string;status:string;started_at:string}>=[];
  page=1;lastPage=1; detail:any=null; detailRecords:Record<string,any>={};
  goal={scheduled_for:'',load:'',unit:'kg',reps:8,rir:2,notes:''}; pendingGoal:{request_id:string;exercise_id:number;[key:string]:unknown}|null=null;
  async ionViewWillEnter():Promise<void>{this.coachId=Number(this.route.snapshot.paramMap.get('id')??0);if(!this.coachId)await this.workouts.useScope('personal');await this.run(async()=>{
    const response=await this.request<{data:CatalogExercise[]}>('GET',this.coachId?'exercise-catalog?per_page=100':'workout-progress/exercises');this.catalog=response.data.filter(e=>e.load_mode==='total_barbell');
    this.exerciseId=this.catalog.find(e=>e.code==='lower_body_back_squat')?.id??this.catalog[0]?.id??0;
    if(this.exerciseId)await this.loadProgress();if(this.coachId)await this.loadSessions();
  });}
  private request<T>(method:'GET'|'POST',path:string,body?:unknown):Promise<T>{
    if(!this.coachId)return this.workouts.appRequest<T>(method,'/'+path,body);
    const url=path.startsWith('exercise-catalog')?'coach/'+path:`coach/clients/${this.coachId}/${path}`;
    return method==='GET'?this.api.get<T>(url):this.api.post<T>(url,body);
  }
  async changeExercise():Promise<void>{if(this.pendingGoal)return;await this.run(()=>this.loadProgress());}
  private async loadProgress():Promise<void>{
    const response=await this.request<{data:Progress}>('GET','workout-progress?exercise_id='+this.exerciseId);this.progress=response.data;
    const all=[...this.progress.points,...this.progress.goals];
    const dates=all.map(p=>Date.parse(p.date));const min=Math.min(...dates),max=Math.max(...dates);const top=Math.max(1,...all.map(p=>Number(p.value_kg)))*1.1;
    this.points=all.map(p=>({...p,x:35+(Date.parse(p.date)-min)/Math.max(86400000,max-min)*630,y:230-Number(p.value_kg)/top*205}));
  }
  async saveGoal():Promise<void>{await this.run(async()=>{
    this.pendingGoal??={...this.goal,request_id:crypto.randomUUID(),exercise_id:Number(this.exerciseId)};
    try{await this.request('POST','workout-goals',this.pendingGoal);this.pendingGoal=null;this.message='Meta programada guardada; no modifica tu RM ni tus resultados.';await this.loadProgress();}
    catch(err:any){if([409,422].includes(err?.status))this.pendingGoal=null;throw err;}
  });}
  canDeactivate():boolean{return !this.pendingGoal||window.confirm('La meta podría estar guardada en el servidor. Revisa el historial antes de volver a crearla. ¿Salir?');}
  async paginate(delta:number):Promise<void>{await this.run(async()=>{this.page+=delta;try{await this.loadSessions();}catch(err){this.page-=delta;throw err;}});}
  private async loadSessions():Promise<void>{const result=await this.request<{data:{data:Array<{uuid:string;title:string;status:string;started_at:string}>;last_page:number}}>('GET','workout-executions?page='+this.page);this.sessions=result.data.data;this.lastPage=result.data.last_page;}
  async openSession(uuid:string):Promise<void>{await this.run(async()=>{this.detail=(await this.request<{data:any}>('GET','workout-executions/'+uuid)).data;this.detailRecords=Object.fromEntries(this.detail.sets.map((r:any)=>[r.set_uuid,r]));});}
  label(kind:string):string{return kind==='tested'?'RM probado declarado':kind==='estimated'?'e1RM estimado':'Meta programada';}
  color(kind:string):string{return kind==='tested'?'#047857':kind==='estimated'?'#2563eb':'#a16207';}
  private async run(work:()=>Promise<unknown>):Promise<void>{if(this.busy)return;this.busy=true;this.error='';try{await work();}catch(err:any){this.error=err?.message??'No se pudo consultar el progreso.';}finally{this.busy=false;}}
}
