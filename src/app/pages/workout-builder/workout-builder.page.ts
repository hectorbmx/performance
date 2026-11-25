import { CommonModule } from '@angular/common';
import { Component, HostListener, computed, effect, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonBackButton, IonButton, IonButtons, IonContent, IonHeader, IonTitle, IonToolbar, IonRefresher, IonRefresherContent, IonModal, IonIcon, ToastController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { helpCircleOutline } from 'ionicons/icons';
import type { RefresherCustomEvent } from '@ionic/angular';
import { WorkoutOfflineService, workoutUuid } from '../../services/workout-offline.service';
import { BuilderExercise, CatalogExercise, GeneratedWorkoutInput, PrescriptionResult, WorkoutBuilderDraft } from '../../services/workout-builder.types';

type Candidate = { id: number; code: string; names: { es: string }; score: number; reasons: string[] };
@Component({
  selector:'app-workout-builder',standalone:true,imports:[CommonModule,FormsModule,IonBackButton,IonButton,IonButtons,IonContent,IonHeader,IonTitle,IonToolbar,IonRefresher,IonRefresherContent,IonModal,IonIcon],
  templateUrl:'./workout-builder.page.html',styleUrls:['./workout-builder.page.scss'],
})
export class WorkoutBuilderPage {
  readonly workouts = inject(WorkoutOfflineService); private router = inject(Router);
  private toastCtrl=inject(ToastController);
  private errorToast?:HTMLIonToastElement;
  rirHelpOpen=false;
  equipmentExercise:CatalogExercise|null=null;
  equipmentChecks:Record<string,boolean>={};
  private equipmentOwner:string|null=null;
  equipmentRemoval:{code:string;names:string[];ids:number[]}|null=null;
  draft: WorkoutBuilderDraft = this.empty();
  settings: { generator_enabled: boolean; feature_enabled: boolean; executions_enabled:boolean } | null = null;
  equipmentOptions: Array<{code:string;names:{es:string}}> = [];
  candidates: Candidate[] = []; catalog: CatalogExercise[] = [];
  equipmentHints:Array<{id:number;name:string;missing:string[]}>=[];
  private draftWrites=0; private draftFailed=false;
  busy = false; error = ''; notice = ''; loadedOwner: string | null = null;
  rmExercise = 0; rmValue: string | number = ''; rmUnit: 'kg'|'lb' = 'kg'; rmDate = new Date().toISOString().slice(0,10); rmConfirmed = false;
  readonly pendingStart = computed(() => !!this.workouts.state()?.generatedStarts?.[this.draft.uuid]);
  constructor() {
    addIcons({helpCircleOutline});
    effect(() => {
      const owner = this.workouts.owner(); const state = this.workouts.state();
      if (owner && state && owner !== this.loadedOwner) {
        this.equipmentExercise=null;this.equipmentRemoval=null;this.equipmentChecks={};
        this.loadedOwner = owner; this.draft = state.builderDraft ? structuredClone(state.builderDraft) : this.empty();
        void this.load();
      } else if (!owner) {
        this.equipmentExercise=null;this.equipmentRemoval=null;this.equipmentChecks={};
        this.loadedOwner = null; this.draft = this.empty(); this.settings=null; this.candidates=[]; this.catalog=[];this.equipmentHints=[]; this.rmValue=''; this.rmConfirmed=false;
      }
    });
  }
  async ionViewWillEnter():Promise<void>{
    await this.workouts.useScope('personal');
    const saved=this.workouts.state()?.builderDraft;
    if(saved&&saved.uuid!==this.draft.uuid&&!this.pendingStart())this.draft=structuredClone(saved);
    if(this.workouts.owner() && this.loadedOwner===this.workouts.owner()) await this.load();
  }
  private empty(): WorkoutBuilderDraft {
    return {uuid:workoutUuid(),title:'Tren inferior · Hipertrofia',equipment:[],exercise_count:4,exercises:[],acknowledge_short_session:false,pain_reported:false,technique_issue:false};
  }
  get regionLabel():string {return this.draft.region==='upper_body'?'Tren superior':'Tren inferior';}
  get muscleOptions():Array<{code:string;name:string}> {
    const groups=new Map<string,string>();
    for(const exercise of this.catalog) {
      if(!exercise.terms.some(t=>t.kind==='region'&&t.code===(this.draft.region??'lower_body')))continue;
      for(const term of exercise.terms.filter(t=>t.kind==='muscle'&&t.role==='primary'))groups.set(term.code,term.names.es);
    }
    return [...groups].map(([code,name])=>({code,name})).sort((a,b)=>a.name.localeCompare(b.name,'es'));
  }
  async changeRegion(region:'lower_body'|'upper_body'):Promise<void>{
    if(this.busy||this.pendingStart())return;
    if(this.draft.exercises.length&&!window.confirm('Cambiar de zona quitará los ejercicios elegidos de este borrador. ¿Continuar?'))return;
    this.draft.region=region;this.draft.initial_muscle='';this.draft.exercises=[];
    this.equipmentExercise=null;this.equipmentRemoval=null;this.draft.acknowledge_short_session=false;
    await this.updateSession();
  }
  async updateSession():Promise<void>{
    this.draft.title=this.regionLabel+' · '+this.draft.exercise_count+' ejercicios';
    await this.changed();await this.refresh();
  }
  async onRefresh(event:RefresherCustomEvent):Promise<void>{
    try {await this.load();} finally {await event.target.complete();}
  }
  async load(): Promise<void> {
    await this.action(async () => {
      const owner = this.workouts.owner();
      const settings = await this.workouts.appRequest<{data:{generator_enabled:boolean;feature_enabled:boolean;executions_enabled:boolean}}>('GET','/workout-generator/settings');
      const meta = await this.workouts.appRequest<{data:{equipment:Array<{code:string;names:{es:string}}>}}>('GET','/exercise-catalog/meta');
      const catalog = await this.workouts.appRequest<{data:CatalogExercise[];meta?:{last_page:number}}>('GET','/exercise-catalog?review_status=reviewed&per_page=100');
      for(let page=2;page<=(catalog.meta?.last_page??1);page++){
        const next=await this.workouts.appRequest<{data:CatalogExercise[]}>('GET','/exercise-catalog?review_status=reviewed&per_page=100&page='+page);catalog.data.push(...next.data);
      }
      if (this.workouts.owner() !== owner) return;
      this.settings=settings.data; this.equipmentOptions=meta.data.equipment; this.catalog=catalog.data;
      if (this.settings.generator_enabled && this.settings.feature_enabled) await this.refreshCandidates();
    });
  }
  async changed(): Promise<void> {
    this.draftWrites++;
    try { await this.workouts.saveBuilderDraft(this.draft); this.draftFailed=false; }
    catch (err:any) { this.draftFailed=true; await this.showError(err?.message ?? 'No se guardó el borrador.'); }
    finally { this.draftWrites--; }
  }
  canDeactivate():boolean { return (this.draftWrites===0&&!this.draftFailed)||window.confirm('El último cambio del borrador no está guardado. ¿Salir de todos modos?'); }
  @HostListener('window:beforeunload',['$event']) beforeUnload(event:BeforeUnloadEvent):void {
    if(this.draftWrites>0||this.draftFailed){event.preventDefault();event.returnValue='';}
  }
  async unitChanged(exercise:BuilderExercise):Promise<void>{exercise.chosen_load=null;await this.changed();}
  async contextChanged():Promise<void>{
    if(this.draft.pain_reported||this.draft.technique_issue) for(const exercise of this.draft.exercises){
      if(exercise.suggestion?.suggested_load?.value===String(exercise.chosen_load)&&exercise.unit===exercise.suggestion.suggested_load.unit)exercise.chosen_load=null;
      exercise.suggestion=undefined;exercise.suggestion_id=null;
    }
    await this.changed();
  }
  async toggleEquipment(code:string,event:Event): Promise<void> {
    const checked=(event.target as HTMLInputElement).checked;
    if(!checked){
      const affected=this.draft.exercises.filter(e=>{
        const known=e.required_equipment??this.catalog.find(c=>c.id===e.exercise_id)?.terms.filter(t=>t.kind==='equipment').map(t=>t.code);
        return !known||known.includes(code);
      });
      if(affected.length){(event.target as HTMLInputElement).checked=true;this.equipmentRemoval={code,names:affected.map(e=>e.name),ids:affected.map(e=>e.exercise_id)};return;}
    }
    this.draft.equipment=checked ? [...new Set([...this.draft.equipment,code])] : this.draft.equipment.filter(item=>item!==code);
    await this.changed(); await this.refresh();
  }
  async refresh(): Promise<void> { await this.action(()=>this.refreshCandidates()); }
  private async refreshCandidates(): Promise<void> {
    const response=await this.workouts.appRequest<{data:{candidates:Candidate[];message:string|null}}>('POST','/workout-generator/recommendations',{
      region:this.draft.region ?? 'lower_body',initial_muscle:this.draft.initial_muscle || null,
      selected_ids:this.draft.exercises.map(e=>e.exercise_id),equipment:this.draft.equipment,exercise_count:this.draft.exercise_count,discover_equipment:true,
    });
    this.candidates=response.data.candidates;
    const selected=new Set(this.draft.exercises.map(exercise=>exercise.exercise_id));
    const equipment=new Set(['bodyweight',...this.draft.equipment]);
    const labels=new Map(this.equipmentOptions.map(item=>[item.code,item.names.es]));
    this.equipmentHints=this.catalog.filter(exercise=>exercise.is_active&&exercise.review_status==='reviewed'&&!selected.has(exercise.id)&&exercise.terms.some(t=>t.kind==='region'&&t.code===(this.draft.region ?? 'lower_body')))
      .map(exercise=>({id:exercise.id,name:exercise.names.es,missing:exercise.terms.filter(term=>term.kind==='equipment'&&!equipment.has(term.code))
        .map(term=>labels.get(term.code)??term.names.es)})).filter(item=>item.missing.length>0);
    this.notice=!this.catalog.length && !this.candidates.length ? 'Todavía no hay ejercicios revisados publicados por la plataforma.' : response.data.message ?? '';
  }
  async add(candidate:Candidate): Promise<void> {
    if (this.busy || this.draft.exercises.length>=this.draft.exercise_count || this.pendingStart() || this.draft.exercises.some(e=>e.exercise_id===candidate.id)) return;
    await this.action(async()=>{
      let exercise=this.catalog.find(item=>item.id===candidate.id);
      if(!exercise) { const detail=await this.workouts.appRequest<{data:CatalogExercise}>('GET','/exercise-catalog/'+candidate.id); exercise=detail.data; this.catalog=[...this.catalog,exercise]; }
      if(this.requiredEquipment(exercise).length){
        this.equipmentOwner=this.workouts.owner();this.equipmentChecks={};
        for(const term of this.requiredEquipment(exercise))this.equipmentChecks[term.code]=this.draft.equipment.includes(term.code);
        this.equipmentExercise=exercise;
      }else await this.commitExercise(exercise,this.draft.equipment);
    });
  }
  requiredEquipment(exercise:CatalogExercise):CatalogExercise['terms'] {return exercise.terms.filter(t=>t.kind==='equipment'&&t.code!=='bodyweight');}
  get equipmentConfirmed():boolean {return !!this.equipmentExercise&&this.requiredEquipment(this.equipmentExercise).every(t=>this.equipmentChecks[t.code]);}
  rememberedEquipment(code:string):boolean {return this.workouts.state()?.equipmentPreferences?.includes(code)??false;}
  async confirmEquipment():Promise<void>{
    if(!this.equipmentConfirmed||this.busy||!this.equipmentExercise)return;
    if(this.equipmentOwner!==this.workouts.owner()){this.equipmentExercise=null;await this.showError('La cuenta cambió. Vuelve a elegir el ejercicio.');return;}
    const exercise=this.equipmentExercise;
    const equipment=[...new Set([...this.draft.equipment,...this.requiredEquipment(exercise).map(t=>t.code)])];
    await this.action(async()=>{await this.commitExercise(exercise,equipment);this.equipmentExercise=null;});
  }
  private async commitExercise(exercise:CatalogExercise,equipment:string[]):Promise<void>{
    if(this.pendingStart()||this.draft.exercises.some(e=>e.exercise_id===exercise.id))return;
    const response=await this.workouts.appRequest<{data:PrescriptionResult}>('POST','/workout-generator/prescription',{
      region:this.draft.region ?? 'lower_body',exercise_id:exercise.id,equipment,pain_reported:this.draft.pain_reported,technique_issue:this.draft.technique_issue,
    });
    this.draft.equipment=equipment;
    this.draft.exercises.push({exercise_id:exercise.id,name:exercise.names.es,load_mode:exercise.load_mode,...response.data.prescribed,
      required_equipment:this.requiredEquipment(exercise).map(t=>t.code),
      unit:'kg',increment:'2.50',chosen_load:response.data.suggested_load?.value??null,notes:'',suggestion_id:response.data.suggestion_id,suggestion:response.data});
    await this.changed();await this.refreshCandidates();
  }
  async confirmEquipmentRemoval():Promise<void>{
    const pending=this.equipmentRemoval;if(!pending||this.busy||this.pendingStart())return;
    this.draft.exercises=this.draft.exercises.filter(e=>!pending.ids.includes(e.exercise_id));
    this.draft.equipment=this.draft.equipment.filter(code=>code!==pending.code);this.equipmentRemoval=null;
    await this.changed();await this.refresh();
  }
  async remove(index:number): Promise<void> { if(this.pendingStart()) return; this.draft.exercises.splice(index,1); await this.changed(); await this.refresh(); }
  async move(index:number,delta:number): Promise<void> {
    const target=index+delta; if(target<0 || target>=this.draft.exercises.length || this.pendingStart()) return;
    [this.draft.exercises[index],this.draft.exercises[target]]=[this.draft.exercises[target],this.draft.exercises[index]];
    await this.changed(); await this.refresh();
  }
  async suggest(exercise:BuilderExercise): Promise<void> {
    if(this.pendingStart()) return;
    await this.action(async()=>{
      const response=await this.workouts.appRequest<{data:PrescriptionResult}>('POST','/workout-generator/prescription',{
        region:this.draft.region ?? 'lower_body',exercise_id:exercise.exercise_id,equipment:this.draft.equipment,sets:exercise.sets,reps_min:exercise.reps_min,reps_max:exercise.reps_max,
        target_rir:exercise.target_rir,rest_seconds:exercise.rest_seconds,unit:exercise.unit,increment:exercise.increment,
        pain_reported:this.draft.pain_reported,technique_issue:this.draft.technique_issue,
      });
      exercise.suggestion=response.data; exercise.suggestion_id=response.data.suggestion_id;
      await this.changed();
    });
  }
  async accept(exercise:BuilderExercise): Promise<void> {
    if(this.pendingStart()) return;
    if(exercise.suggestion?.suggested_load) {
      exercise.chosen_load=exercise.suggestion.suggested_load.value; exercise.unit=exercise.suggestion.suggested_load.unit; await this.changed();
    }
  }
  inlineRmExercise:number|null=null;
  async openInlineRm(exercise:BuilderExercise):Promise<void>{
    this.inlineRmExercise=exercise.exercise_id;this.rmExercise=exercise.exercise_id;this.rmValue='';this.rmConfirmed=false;
    await this.action(async()=>{
      const response=await this.workouts.appRequest<{basis:PrescriptionResult['basis']}>('GET','/exercise-maxes?exercise_id='+exercise.exercise_id);
      if(response.basis)this.notice='Ya tienes un máximo vigente para este ejercicio. Puedes corregirlo si conoces un dato más reciente.';
      else this.notice='No encontramos un máximo vigente para este ejercicio. Puedes registrarlo aquí si lo conoces o continuar sin RM.';
    });
  }
  rmCompatible():boolean{return this.catalog.some(exercise=>exercise.id===Number(this.rmExercise)&&exercise.load_mode==='total_barbell');}
  async saveRm(): Promise<void> {
    await this.action(async()=>{
      if(!this.rmCompatible())throw new Error('Esta modalidad todavía no admite registro de RM. Puedes continuar sin máximo.');
      if(!this.rmExercise || !this.rmConfirmed) throw new Error('Elige ejercicio y confirma que registras una prueba de una repetición.');
      await this.workouts.appRequest('POST','/exercise-maxes',{exercise_id:Number(this.rmExercise),value:this.rmValue,unit:this.rmUnit,
        measured_at:this.rmDate+'T00:00:00Z',single_rep_confirmed:true});
      const exercise=this.draft.exercises.find(item=>item.exercise_id===Number(this.rmExercise));
      this.notice='RM declarado guardado. Puedes continuar; la carga sugerida sigue siendo opcional.';this.rmValue='';this.rmConfirmed=false;this.inlineRmExercise=null;
      if(exercise){
        const response=await this.workouts.appRequest<{data:PrescriptionResult}>('POST','/workout-generator/prescription',{
          region:this.draft.region ?? 'lower_body',exercise_id:exercise.exercise_id,equipment:this.draft.equipment,sets:exercise.sets,reps_min:exercise.reps_min,reps_max:exercise.reps_max,
          target_rir:exercise.target_rir,rest_seconds:exercise.rest_seconds,unit:exercise.unit,increment:exercise.increment,
          pain_reported:this.draft.pain_reported,technique_issue:this.draft.technique_issue,
        });exercise.suggestion=response.data;exercise.suggestion_id=response.data.suggestion_id;await this.changed();
      }
    });
  }
  async prepare(): Promise<void> {
    await this.action(async()=>{
      if(!this.draft.exercises.length) throw new Error('Elige los ejercicios de tu sesión.');
      const body:GeneratedWorkoutInput={region:this.draft.region ?? 'lower_body',title:this.draft.title,equipment:[...this.draft.equipment],acknowledge_short_session:this.draft.acknowledge_short_session,
        exercises:this.draft.exercises.map(e=>({exercise_id:e.exercise_id,sets:Number(e.sets),reps_min:Number(e.reps_min),reps_max:Number(e.reps_max),
          target_rir:Number(e.target_rir),rest_seconds:Number(e.rest_seconds),chosen_load:e.load_mode==='total_barbell' && e.chosen_load!==null && String(e.chosen_load).trim()!=='' ? String(e.chosen_load) : null,
          unit:e.unit,notes:e.notes,suggestion_id:e.suggestion_id}))};
      await this.workouts.saveBuilderDraft(this.draft);
      const uuid=await this.workouts.startGenerated(this.draft,body);
      await this.router.navigate(['/workout-recorder'],{queryParams:{execution:uuid}});
    });
  }
  private async action(work:()=>Promise<unknown>):Promise<void> {
    if(this.busy) return; this.busy=true; this.error='';
    try{await work();}catch(err:any){await this.showError(err?.error?.message ?? err?.message ?? 'No se pudo completar. El borrador guardado se conserva.');}
    finally{this.busy=false;}
  }
  private async showError(message:string):Promise<void>{
    this.error=message;
    if(this.errorToast){
      if(this.errorToast.message===message)return;
      await this.errorToast.dismiss();
    }
    const toast=await this.toastCtrl.create({message,color:'danger',position:'top',duration:6000,buttons:[{text:'OK',role:'cancel'}]});
    this.errorToast=toast;
    void toast.onDidDismiss().then(()=>{if(this.errorToast===toast)this.errorToast=undefined;});
    await toast.present();
  }
  trackId(_:number,item:{exercise_id?:number;id?:number}):number{return item.exercise_id ?? item.id!;}
  trackCode(_:number,item:{code:string}):string{return item.code;}
}
