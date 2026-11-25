import { CommonModule } from '@angular/common';
import { Component, ElementRef, ViewChild, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { IonBackButton,IonButton,IonButtons,IonContent,IonHeader,IonTitle,IonToolbar,ToastController } from '@ionic/angular/standalone';
import { ApiService } from '../../../services/api.service';
import { CatalogExercise } from '../../../services/workout-builder.types';

@Component({selector:'app-coach-workout-generator',standalone:true,
  imports:[CommonModule,FormsModule,IonBackButton,IonButton,IonButtons,IonContent,IonHeader,IonTitle,IonToolbar],
  templateUrl:'./coach-workout-generator.page.html',styleUrls:['./coach-workout-generator.page.scss']})
export class CoachWorkoutGeneratorPage {
  @ViewChild('exerciseEditor') private exerciseEditor?:ElementRef<HTMLElement>;
  private toastCtrl=inject(ToastController);
  permissionsDirty=false;
  availability:Record<string,{enabled:boolean;reasons:string[]}>={};
  enabledExerciseCount=0;
  private api=inject(ApiService); private route=inject(ActivatedRoute);
  athleteId=0; busy=false; error=''; message=''; reviewed=false;
  catalog:CatalogExercise[]=[]; meta:Record<string,Array<{code:string;names:{es:string}}>>={}; selected:CatalogExercise|null=null;
  settings:{feature_enabled:boolean;executions_enabled:boolean;generator_enabled:boolean;blocked_exercise_codes:string[];blocked_demands:string[];profile?:Record<string,string>}={feature_enabled:false,executions_enabled:false,generator_enabled:false,blocked_exercise_codes:[] as string[],blocked_demands:[] as string[]};
  editor={code:'',names:{es:'',en:''},load_mode:'bodyweight',fatigue:'moderate',unilateral:false,is_active:true,review_status:'pending',notes:'',
    regions:['lower_body'],goals:['hypertrophy'],levels:['intermediate'],patterns:[] as string[],equipment:[] as string[],primary_muscles:[] as string[],secondary_muscles:[] as string[],demands:[] as string[]};
  async ionViewWillEnter():Promise<void>{this.athleteId=Number(this.route.snapshot.paramMap.get('id'));await this.load();}
  async load():Promise<void>{await this.action(()=>this.loadData());}
  private async loadData():Promise<void>{
    const [settings,meta,catalog]=await Promise.all([
      this.api.get<{data:CoachWorkoutGeneratorPage['settings']}>(`coach/clients/${this.athleteId}/workout-generator/settings`),
      this.api.get<{data:CoachWorkoutGeneratorPage['meta']}>('coach/exercise-catalog/meta'),
      this.api.get<{data:CatalogExercise[]}>('coach/exercise-catalog',{per_page:100,include_inactive:1}),
    ]);this.settings=settings.data;this.permissionsDirty=false;this.meta=meta.data;this.catalog=catalog.data;this.updateAvailability();
  }
  private updateAvailability():void {
    this.availability={};this.enabledExerciseCount=0;
    for(const exercise of this.catalog){
      const reasons:string[]=[];
      if(!this.settings.feature_enabled)reasons.push('Generador desactivado en el servidor');
      if(!this.settings.generator_enabled)reasons.push('Permiso del atleta desactivado');
      if(!exercise.is_active)reasons.push('Ejercicio inactivo');
      if(exercise.review_status!=='reviewed')reasons.push('Pendiente de revisión');
      if(this.settings.blocked_exercise_codes.includes(exercise.code))reasons.push('Excluido para este atleta');
      if(exercise.terms.some(term=>term.kind==='demand'&&this.settings.blocked_demands.includes(term.code)))reasons.push('Demanda excluida por el coach');
      if(!this.settings.profile)reasons.push('Perfil del generador no disponible');
      else if(Object.entries(this.settings.profile).some(([kind,code])=>!exercise.terms.some(term=>term.kind===kind&&term.code===code)))reasons.push('Fuera del perfil actual del generador');
      const enabled=reasons.length===0;this.availability[exercise.code]={enabled,reasons};if(enabled)this.enabledExerciseCount++;
    }
  }
  open(exercise:CatalogExercise):void{
    this.selected=exercise;this.reviewed=exercise.review_status==='reviewed';this.message='';this.error='';
    const codes=(kind:string,role?:string)=>exercise.terms.filter(t=>t.kind===kind&&(!role||t.role===role)).map(t=>t.code);
    this.editor={code:'review_'+exercise.code,names:{es:exercise.names.es,en:exercise.names.en??''},load_mode:exercise.load_mode,
      fatigue:exercise.fatigue,unilateral:exercise.unilateral,is_active:exercise.is_active,review_status:exercise.review_status,notes:exercise.notes??'',
      regions:codes('region'),goals:codes('goal'),levels:codes('level'),patterns:codes('pattern'),equipment:codes('equipment'),
      primary_muscles:codes('muscle','primary'),secondary_muscles:codes('muscle','secondary'),demands:codes('demand')};
    requestAnimationFrame(()=>this.exerciseEditor?.nativeElement.scrollIntoView({behavior:'smooth',block:'start'}));
  }
  reviewChanged(confirmed:boolean):void{this.reviewed=confirmed;this.editor.review_status=confirmed?'reviewed':'pending';}
  toggle(field:'patterns'|'equipment'|'primary_muscles'|'secondary_muscles'|'demands',code:string,event:Event):void{
    const checked=(event.target as HTMLInputElement).checked;
    this.editor[field]=checked?[...new Set([...this.editor[field],code])]:this.editor[field].filter(item=>item!==code);
  }
  permissionsChanged():void{this.permissionsDirty=true;this.message='';}
  toggleBlocked(field:'blocked_exercise_codes'|'blocked_demands',code:string,event:Event):void{
    const checked=(event.target as HTMLInputElement).checked;
    this.settings[field]=checked?[...new Set([...this.settings[field],code])]:this.settings[field].filter(item=>item!==code);
    this.permissionsChanged();
  }
  async saveExercise():Promise<void>{await this.action(async()=>{
    if(!this.selected)return;
    if(this.editor.review_status==='reviewed'&&!this.reviewed)throw new Error('Confirma que revisaste la clasificación antes de marcarla como revisada.');
    const {code,...body}=this.editor;
    const response=this.selected.scope==='global'
      ? await this.api.post<{data:CatalogExercise}>('coach/exercise-catalog',{...body,code,variant_of_id:this.selected.id})
      : await this.api.put<{data:CatalogExercise}>(`coach/exercise-catalog/${this.selected.id}`,body);
    const saved=response.data;
    this.selected=null;await this.loadData();
    if(saved.review_status!=='reviewed'){
      this.message=`«${saved.names.es}» guardado como pendiente de revisión. Aún no aparece al atleta.`;
      await this.toast(this.message,'warning');
    }else{
      const status=this.availability[saved.code];
      const blocked=status&&!status.enabled ? ' No habilitado: '+status.reasons.join(' · ')+'.' : '';
      this.message=`«${saved.names.es}» guardado y revisado.`+blocked;
      await this.toast(this.message,blocked?'warning':'success');
    }
  });}
  async saveSettings():Promise<void>{await this.action(async()=>{
    const result=await this.api.put<{data:CoachWorkoutGeneratorPage['settings']}>(`coach/clients/${this.athleteId}/workout-generator/settings`,{
      generator_enabled:this.settings.generator_enabled,
      blocked_exercise_codes:this.settings.blocked_exercise_codes,blocked_demands:this.settings.blocked_demands,
    });
    this.settings=result.data;this.permissionsDirty=false;this.updateAvailability();
    this.message='Permisos y restricciones guardados para este atleta.';
    const detail=this.settings.generator_enabled&&!this.settings.feature_enabled?' El generador sigue desactivado en el servidor.':
      this.settings.generator_enabled&&!this.settings.executions_enabled?' El inicio de sesiones sigue desactivado en el servidor.':'';
    await this.toast(this.message+detail,'success');
  });}
  private async action(work:()=>Promise<unknown>):Promise<void>{if(this.busy)return;this.busy=true;this.error='';this.message='';
    try{await work();}catch(err:any){this.error=err?.message??'No se pudo completar la operación.';await this.toast(this.error,'danger');}finally{this.busy=false;}}
  private async toast(message:string,color:'success'|'danger'|'warning'):Promise<void>{
    const toast=await this.toastCtrl.create({message,color,duration:3500,position:'top',buttons:[{text:'OK',role:'cancel'}]});await toast.present();
  }
  trackCode(_:number,item:{code:string}):string{return item.code;}
}
