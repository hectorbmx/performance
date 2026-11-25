import { PrescriptionResult } from '../../services/workout-builder.types';
import { measuredSummary } from '../../services/workout-measured-summary';
import { CommonModule } from '@angular/common';
import { Component, HostListener, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { IonBackButton, IonButton, IonButtons, IonContent, IonHeader, IonTitle, IonToolbar, IonModal, ToastController, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { repeatOutline, speedometerOutline, timeOutline, informationCircleOutline, saveOutline } from 'ionicons/icons';
import { WorkoutOfflineService } from '../../services/workout-offline.service';
import { PrescribedSet, SetValues } from '../../services/workout-offline.store';

@Component({
  selector: 'app-workout-recorder', standalone: true,
  imports: [CommonModule, FormsModule, IonBackButton, IonButton, IonButtons, IonContent, IonHeader, IonTitle, IonToolbar, IonModal, IonIcon],
  templateUrl: './workout-recorder.page.html', styleUrls: ['./workout-recorder.page.scss'],
})
export class WorkoutRecorderPage {
  readonly workouts = inject(WorkoutOfflineService);
  private toastCtrl=inject(ToastController);
  editingAllowsBarbell=true;
  editingLoadMode:SetValues['load_mode']='total_barbell';
  previousLoad:{value:string;unit:'kg'|'lb'}|null=null;
  previousFeeling:''|'light'|'good'|'heavy'='';
  get loadLabel():string {return this.editingLoadMode==='per_dumbbell'?'Peso por mancuerna':this.editingLoadMode==='machine_indicated'?'Peso indicado en la máquina':this.editingLoadMode==='total_barbell'?'Peso total, incluida la barra':'Peso externo total';}
  chooseFeeling(value:''|'light'|'good'|'heavy'):void {this.previousFeeling=value;}
  usePreviousLoad():void {if(!this.previousLoad)return;this.form.actual_load=this.previousLoad.value;this.form.load_unit=this.previousLoad.unit;this.dirty=true;}
  changeLoad(direction:'up'|'down'):void {if(!this.previousLoad||this.advisoryPain||this.advisoryTechnique)return;this.usePreviousLoad();this.form.actual_load='';}

  private notifiedErrors=new Set<string>();
  private async toast(message:string,color:'success'|'danger'):Promise<void>{
    const toast=await this.toastCtrl.create({message,color,position:'top',duration:5000,buttons:[{text:'OK',role:'cancel'}]});await toast.present();
  }
  private async showError(message:string):Promise<void>{this.error=message;await this.toast(message,'danger');}
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  readonly selected = signal<string | null>(this.route.snapshot.queryParamMap.get('execution'));
  readonly sessions = computed(() => Object.values(this.workouts.state()?.executions ?? {}).reverse());
  readonly current = computed(() => this.workouts.state()?.executions[this.selected() ?? ''] ?? null);
  readonly measured = computed(() => { const session = this.current(); return session ? measuredSummary(session) : null; });
  readonly exerciseHistory = computed(() => (this.measured()?.exercises ?? []).map(exercise => ({
    uuid: exercise.uuid, name: exercise.name,
    entries: this.sessions().flatMap(session => measuredSummary(session).exercises
      .filter(item => exercise.catalogId !== null ? item.catalogId === exercise.catalogId
        : item.catalogId === null && item.name.trim().toLowerCase() === exercise.name.trim().toLowerCase())
      .map(item => ({ uuid: session.execution.uuid + ':' + item.uuid, date: session.execution.started_at,
        title: session.execution.prescription.content.title, tonnage: item.tonnage, partial: !item.complete }))),
  })));
  readonly substitution = computed(()=>this.workouts.state()?.substitutions?.[this.selected()??'']??null);
  replacementTarget:string|null=null; replacementOptions:Array<{id:number;names:{es:string};reasons:string[]}>=[];
  replacementId=0;
  readonly closing = computed(() => this.workouts.state()?.closes?.[this.selected() ?? ''] ?? null);
  readonly completionCounts = computed(() => {
    const session = this.current();
    const prescribed = session?.execution.prescription.content.sections.reduce((total, s) => total + s.exercises.reduce((n, e) => n + e.sets.length, 0), 0) ?? 0;
    const records = Object.values(session?.records ?? {});
    return { prescribed, registered: records.length, completed: records.filter(r => r.values.status === 'completed').length,
      failed: records.filter(r => r.values.status === 'failed').length, skipped: records.filter(r => r.values.status === 'skipped').length,
      missing: Math.max(0, prescribed - records.length) };
  });
  readonly sections = computed(() => this.current()?.execution.prescription.content.sections ?? []);
  readonly exerciseProgress=computed(()=>{
    const result:Record<string,number>={};const records=this.current()?.records??{};
    for(const section of this.sections())for(const exercise of section.exercises)
      result[exercise.uuid]=exercise.sets.filter(set=>records[set.uuid]?.values.status==='completed').length;
    return result;
  });
  readonly nextSeries=computed(()=>{
    const result:Record<string,boolean>={};const records=this.current()?.records??{};
    for(const section of this.sections())for(const exercise of section.exercises){
      const next=exercise.sets.find(set=>!records[set.uuid]);if(next)result[next.uuid]=true;
    }
    return result;
  });
  readonly pending = computed(() => new Set((this.workouts.state()?.queue ?? []).map(op => `${op.execution}:${op.set}`)).size);
  readonly assignment = Number(this.route.snapshot.queryParamMap.get('assignment')) || null;
  editing: string | null = null;
  editingExerciseName=''; editingSetNumber=0; editingPrescription:PrescribedSet|null=null;
  readonly canDismissSeries=async():Promise<boolean>=>this.editing===null || (!this.busy && this.canDeactivate());
  seriesModalDismissed():void{this.editing=null;this.dirty=false;this.editingPrescription=null;}
  dirty = false;
  busy = false;
  message = '';
  error = '';
  form = this.emptyForm();
  advisory:PrescriptionResult|null=null; advisoryPain=false; advisoryTechnique=false; advisoryIncrement='2.50';

  constructor() {
    addIcons({repeatOutline,speedometerOutline,timeOutline,informationCircleOutline,saveOutline});
    let previous: string | null = null;
    effect(() => {
      const owner = this.workouts.owner();
      if (previous !== owner) {
        this.editing = null; this.dirty = false; this.form = this.emptyForm();
        this.message = ''; this.error = '';this.notifiedErrors.clear(); previous = owner;
      }
      const state=this.workouts.state();const activeErrors=new Set<string>();
      for(const session of Object.values(state?.executions??{}))for(const [set,record] of Object.entries(session.records)){
        if(record.sync!=='error'||!record.message)continue;
        const key=session.execution.uuid+':'+set+':'+record.serverRevision+':'+record.message;
        activeErrors.add(key);if(this.notifiedErrors.has(key))continue;this.notifiedErrors.add(key);
        void this.toast('No se pudo sincronizar una serie: '+record.message,'danger').catch(()=>undefined);
      }
      for(const key of this.notifiedErrors)if(!activeErrors.has(key))this.notifiedErrors.delete(key);
    });
  }
  async ionViewWillEnter():Promise<void> {
    if(this.selected())await this.workouts.selectExecution(this.selected()!);
    else await this.workouts.useScope(this.assignment?'assigned':'personal');
  }
  async showScope(scope:'personal'|'assigned'):Promise<void>{
    if(!this.canDeactivate())return;this.selected.set(null);await this.workouts.useScope(scope);
  }
  private emptyForm() {
    return { status: 'completed' as SetValues['status'], type: 'work' as SetValues['type'],
      load_mode: 'unknown' as SetValues['load_mode'], actual_reps: '' as string | number,
      actual_load: '' as string | number, load_unit: 'kg' as 'kg' | 'lb', actual_rir: '' as string | number, notes: '' };
  }
  canDeactivate(): boolean {
    if (!this.dirty) return true;
    if (!window.confirm('Hay cambios sin guardar. ¿Salir sin guardarlos?')) return false;
    this.dirty = false; return true;
  }
  @HostListener('window:beforeunload', ['$event'])
  beforeUnload(event: BeforeUnloadEvent): void { if (this.dirty) { event.preventDefault(); event.returnValue = ''; } }
  async start(): Promise<void> {
    if (!this.assignment || !this.canDeactivate()) return;
    await this.action(async () => {
      const uuid = await this.workouts.start(this.assignment!);
      this.dirty = false; this.editing = null; this.selected.set(uuid);
      await this.remember(uuid); this.message = 'Sesión descargada. Ya puedes registrar series sin conexión.';
    });
  }
  async select(uuid: string): Promise<void> {
    if (!this.canDeactivate()) return;
    this.dirty = false; this.editing = null; this.selected.set(uuid); await this.remember(uuid);
  }
  private remember(uuid: string): Promise<boolean> {
    return this.router.navigate([], { relativeTo: this.route, queryParams: { execution: uuid }, queryParamsHandling: 'merge', replaceUrl: true });
  }
  edit(set: PrescribedSet): void {
    if (!this.canDeactivate()) return;
    const exercise=this.sections().flatMap(section=>section.exercises).find(exercise=>exercise.sets.some(item=>item.uuid===set.uuid));
    this.editingExerciseName=exercise?.name??'';
    const mode=exercise?.catalog?.load_mode??exercise?.load_mode;
    this.editingAllowsBarbell=!mode||mode==='total_barbell';
    this.editingLoadMode=mode==='bodyweight'?'external_total':['total_barbell','per_dumbbell','external_total','machine_indicated'].includes(mode??'total_barbell')?(mode??'total_barbell') as SetValues['load_mode']:'unknown';
    this.previousFeeling='';this.previousLoad=null;
    const previous=exercise?.sets.filter(item=>item.set_number<set.set_number).sort((a,b)=>b.set_number-a.set_number)
      .map(item=>this.current()?.records[item.uuid]?.values).find(value=>value&&value.status!=='skipped'&&value.load_mode===this.editingLoadMode&&value.actual_load!=null&&value.load_unit);
    if(previous?.actual_load!=null&&previous.load_unit)this.previousLoad={value:String(previous.actual_load),unit:previous.load_unit};
    this.editingSetNumber=set.set_number;this.editingPrescription=set;
    const saved = this.current()?.records[set.uuid]?.values;
    this.form = this.emptyForm();
    this.form.load_mode=this.editingLoadMode;
    if(!saved&&this.previousLoad){this.form.actual_load=this.previousLoad.value;this.form.load_unit=this.previousLoad.unit;}
    if (saved) this.form = { ...this.form, ...saved, actual_reps: saved.actual_reps ?? '', actual_load: saved.actual_load ?? '',
      actual_rir: saved.actual_rir ?? '', load_unit: saved.load_unit ?? 'kg', notes: saved.notes ?? '' };
    if(this.form.load_mode==='unknown')this.form.load_mode=this.editingLoadMode;
    this.editing = set.uuid; this.dirty = false; this.error = ''; this.message = '';this.advisory=null;this.advisoryPain=false;this.advisoryTechnique=false;
  }
  cancel(): void { if (this.canDeactivate()) { this.editing = null; this.dirty = false; } }
  effortChanged(event:Event):void {
    const value=(event.target as HTMLSelectElement).value;
    const approximate:Record<string,number>={easy:4,adequate:2,hard:1,limit:0};
    if(value in approximate){this.form.actual_rir=approximate[value];this.dirty=true;}
  }
  async consultLoad():Promise<void>{
    const session=this.current();if(!session||!this.editing)return;
    const exercise=this.sections().flatMap(s=>s.exercises).find(e=>e.sets.some(set=>set.uuid===this.editing));
    const set=exercise?.sets.find(set=>set.uuid===this.editing);if(!exercise?.workout_exercise_id||!set)return;
    await this.action(async()=>{this.advisory=null;this.advisory=(await this.workouts.appRequest<{data:PrescriptionResult}>('POST','/workout-generator/prescription',{
      exercise_id:exercise.workout_exercise_id,equipment:session.execution.prescription.content.equipment??[],sets:exercise.sets.length,
      reps_min:set.prescribed.reps_min,reps_max:set.prescribed.reps_max,target_rir:set.prescribed.rir??2,rest_seconds:set.prescribed.rest_seconds??90,
      unit:this.form.load_unit,increment:this.advisoryIncrement,pain_reported:this.advisoryPain,technique_issue:this.advisoryTechnique,
    })).data;});
  }
  belowTarget():boolean {
    if(this.form.status==='skipped'||String(this.form.actual_reps).trim()==='')return false;
    const set=this.sections().flatMap(s=>s.exercises.flatMap(e=>e.sets)).find(s=>s.uuid===this.editing);
    return !!set && Number(this.form.actual_reps)<set.prescribed.reps_min;
  }
  async save(): Promise<void> {
    const uuid = this.selected();
    if (!uuid || !this.editing) return;
    const skipped = this.form.status === 'skipped';
    const decimal = (value: string | number) => String(value ?? '').trim().replace(',', '.');
    const reps = decimal(this.form.actual_reps);
    const load = decimal(this.form.actual_load);
    const rir = decimal(this.form.actual_rir);
    if (!skipped && (!/^\d{1,4}$/.test(reps) || Number(reps) > 1000 || (this.form.status === 'completed' && Number(reps) < 1))) {
      await this.showError('Captura las repeticiones realizadas (0–1000; al menos 1 al completar).'); return;
    }
    if (!skipped && this.form.load_mode !== 'unknown' && load && (!/^\d{1,5}(?:\.\d{1,2})?$/.test(load) || Number(load) > 10000)) {
      await this.showError('Captura una carga de 0 a 10000 con hasta dos decimales.'); return;
    }
    if (!skipped && rir && (!/^\d{1,2}(?:\.\d)?$/.test(rir) || Number(rir) > 10)) {
      await this.showError('El RIR debe estar entre 0 y 10, con hasta un decimal.'); return;
    }
    const hasLoad = !skipped && this.form.load_mode !== 'unknown' && load !== '';
    const values: SetValues = {
      status: this.form.status, type: this.form.type, load_mode: this.form.load_mode,
      actual_reps: skipped ? null : Number(reps), actual_load: hasLoad ? load : null,
      load_unit: hasLoad ? this.form.load_unit : null, actual_rir: skipped || !rir ? null : rir,
      notes: this.form.notes.trim() || null, performed_at: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
    };
    const set = this.editing;
    await this.action(async () => {
      await this.workouts.save(uuid, set, values);
      this.dirty = false; this.editing = null;
      this.message = 'Serie guardada en el dispositivo. Se sincronizará al tener conexión.';await this.toast(this.message,'success');
    });
  }
  async resolve(set: string, keepLocal: boolean): Promise<void> {
    const uuid = this.selected(); if (!uuid) return;
    await this.action(async () => { await this.workouts.resolve(uuid, set, keepLocal); });
  }
  async chooseReplacement(exerciseUuid:string):Promise<void>{
    const session=this.current();if(!session)return;
    await this.action(async()=>{
      if(!this.canDeactivate())return;
      const exercises=session.execution.prescription.content.sections.flatMap(section=>section.exercises);
      const selected=exercises.filter(exercise=>exercise.uuid!==exerciseUuid).map(exercise=>exercise.workout_exercise_id!);
      const response=await this.workouts.appRequest<{data:{candidates:Array<{id:number;names:{es:string};reasons:string[]}>}}>('POST','/workout-generator/recommendations',{
        selected_ids:selected,equipment:session.execution.prescription.content.equipment??[],exercise_count:Math.max(3,exercises.length),
      });
      const original=exercises.find(exercise=>exercise.uuid===exerciseUuid)?.workout_exercise_id;
      this.replacementOptions=response.data.candidates.filter(candidate=>candidate.id!==original);this.replacementTarget=exerciseUuid;this.replacementId=0;
    });
  }
  async replaceExercise():Promise<void>{
    const uuid=this.selected();if(!uuid||!this.replacementTarget||!this.replacementId)return;
    if(!window.confirm('¿Sustituir el ejercicio sin comenzar? Se conservarán la programación anterior y las series realizadas. La nueva carga quedará por definir.'))return;
    await this.action(async()=>{
      await this.workouts.substitute(uuid,this.replacementTarget!,{exercise_id:Number(this.replacementId),sets:3,reps_min:8,reps_max:12,target_rir:2,rest_seconds:90,unit:'kg',chosen_load:null,notes:'',suggestion_id:null});
      this.replacementTarget=null;this.editing=null;this.dirty=false;this.message='Ejercicio sustituido. La sesión conserva su historial de programación.';
    });
  }
  async retrySubstitution():Promise<void>{
    const uuid=this.selected();const pending=this.substitution();if(!uuid||!pending)return;
    await this.action(async()=>{await this.workouts.substitute(uuid,pending.target_exercise_uuid,pending.replacement);this.replacementTarget=null;this.editing=null;});
  }
  async reviewSubstitution():Promise<void>{const uuid=this.selected();if(uuid)await this.action(()=>this.workouts.reviewSubstitution(uuid));}
  async complete(): Promise<void> {
    const uuid = this.selected();
    if (!uuid || !this.canDeactivate()) return;
    const counts = this.completionCounts();
    if (!window.confirm(`¿Finalizar esta sesión? Hay ${counts.missing} series sin registrar. Se conservarán tal como están y el cierre se enviará después de las series pendientes.`)) return;
    await this.action(async () => {
      await this.workouts.complete(uuid); this.editing = null; this.dirty = false;
      this.message = 'Finalización guardada en el dispositivo. Esperando confirmación del servidor.';
    });
  }
  async cancelCompletion(): Promise<void> {
    const uuid = this.selected(); if (!uuid) return;
    await this.action(() => this.workouts.cancelCompletion(uuid));
  }
  async reviewCompletion(): Promise<void> {
    const uuid = this.selected(); if (!uuid) return;
    await this.action(async () => { await this.workouts.reviewCompletion(uuid); this.editing = null; this.dirty = false; });
  }
  async download(): Promise<void> { await this.action(() => this.workouts.downloadHistory()); }
  async retry(): Promise<void> { await this.action(() => this.workouts.retry()); }
  private async action(work: () => Promise<unknown>): Promise<void> {
    if (this.busy) return;
    this.busy = true; this.error = '';
    try { await work(); } catch (err: any) { await this.showError(err?.error?.message ?? err?.message ?? 'No se completó la operación. Tus registros guardados se conservan.'); }
    finally { this.busy = false; }
  }
  trackUuid(_: number, value: { uuid: string }): string { return value.uuid; }
  summary(values: SetValues | null | undefined): string {
    if (!values) return 'Sin registro remoto';
    const status = { completed: 'Completada', failed: 'Fallida', skipped: 'Omitida' }[values.status];
    return `${status} · ${values.type === 'warmup' ? 'Aproximación' : 'Trabajo'} · ${values.actual_reps ?? '—'} reps · ${values.actual_load ?? 'Sin carga'} ${values.load_unit ?? ''} · RIR ${values.actual_rir ?? 'sin registrar'}${values.notes ? ' · ' + values.notes : ''}`;
  }
}
