import { emptyAccount, WorkoutAccount } from './workout-offline.store';

export interface OwnershipProof {
  user_app_id:number; contexts:number[][]; current_context:number[]|null;
  personal:string[]; assigned:string[]; assignments:number[];
}
export function personalOwner(api:string,id:number):string {return `${api.replace(/\/$/,'')}|gym-v2|${id}`;}
export function assignedOwner(api:string,id:number,context:number[]):string {return `${api.replace(/\/$/,'')}|assigned-v2|${id}|${context.join('|')}`;}
export function legacyOwners(rows:WorkoutAccount[],api:string,id:number):WorkoutAccount[] {
  const prefix=`${api.replace(/\/$/,'')}|${id}|`;
  return rows.filter(row=>row.owner.startsWith(prefix) && /^\d+\|\d+$/.test(row.owner.slice(prefix.length)));
}
/** Called synchronously inside one IndexedDB transaction. Source bodies are retained. */
export function migrateAccounts(rows:WorkoutAccount[],api:string,id:number,proof:OwnershipProof,now=Date.now()):{rows:WorkoutAccount[];held:number} {
  if(proof.user_app_id!==id)throw new Error('La identidad de la migración no coincide.');
  let held=0;const changed=new Map<string,WorkoutAccount>();
  const destination=(key:string)=>{let row=changed.get(key);if(!row){row=rows.find(r=>r.owner===key)??emptyAccount(key);changed.set(key,row);}return row;};
  const same=(a:unknown,b:unknown)=>JSON.stringify(a)===JSON.stringify(b);
  for(const source of legacyOwners(rows,api,id)) {
    if(source.lease && source.lease.until>now){held++;continue;}
    const context=source.owner.split('|').slice(-2).map(Number);
    const proven=proof.contexts.some(c=>same(c,context));
    const current=!!proof.current_context&&same(proof.current_context,context);
    const personal=destination(personalOwner(api,id));
    const assigned=current?destination(assignedOwner(api,id,context)):null;
    source.transferred??=[];
    const transfer=(key:string,dest:WorkoutAccount|null,canCopy:()=>boolean,copy:()=>void)=>{
      if(source.transferred!.includes(key))return;
      if(!dest || (dest.lease && dest.lease.until>now) || !canCopy()){held++;return;}
      copy();source.transferred!.push(key);
    };
    for(const [uuid,execution] of Object.entries(source.executions)) {
      const dest=proof.personal.includes(uuid)?personal:proof.assigned.includes(uuid)?assigned:null;
      const queue=source.queue.filter(op=>op.execution===uuid);
      transfer('execution:'+uuid,dest,()=>!dest!.executions[uuid] && !dest!.queue.some(op=>queue.some(old=>old.id===op.id))
        && !dest!.closes?.[uuid] && !dest!.substitutions?.[uuid],()=>{
          dest!.executions[uuid]=structuredClone(execution);dest!.queue.push(...structuredClone(queue));
          if(source.closes?.[uuid]){dest!.closes??={};dest!.closes[uuid]=structuredClone(source.closes[uuid]);}
          if(source.substitutions?.[uuid]){dest!.substitutions??={};dest!.substitutions[uuid]=structuredClone(source.substitutions[uuid]);}
        });
    }
    // Draft and uncertain start form one unit: never merge with another draft.
    if(source.builderDraft || Object.keys(source.generatedStarts??{}).length || source.equipmentPreferences?.length) {
      transfer('draft',proven?personal:null,()=>!personal.builderDraft && !Object.keys(personal.generatedStarts??{}).length,()=>{
        personal.builderDraft=structuredClone(source.builderDraft);
        personal.generatedStarts=structuredClone(source.generatedStarts??{});
        personal.equipmentPreferences??=structuredClone(source.equipmentPreferences??[]);
      });
    }
    for(const [assignment,requestId] of Object.entries(source.starts)) {
      transfer('start:'+assignment,current&&proof.assignments.includes(Number(assignment))?assigned:null,
        ()=>!assigned!.starts[assignment] || assigned!.starts[assignment]===requestId,()=>{assigned!.starts[assignment]=requestId;});
    }
    held+=source.queue.filter(op=>!source.executions[op.execution]).length;
    held+=Object.keys(source.closes??{}).filter(uuid=>!source.executions[uuid]).length;
    held+=Object.keys(source.substitutions??{}).filter(uuid=>!source.executions[uuid]).length;
    source.blocked='Almacenamiento anterior conservado. Usa los espacios personal y asignado.';
    changed.set(source.owner,source);
  }
  return {rows:[...changed.values()],held};
}
