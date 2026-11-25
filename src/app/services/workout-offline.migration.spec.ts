import { WorkoutOfflineStore, emptyAccount, Execution, SetValues } from './workout-offline.store';
import { migrateAccounts, OwnershipProof, personalOwner, assignedOwner } from './workout-offline.migration';

describe('Offline ownership migration (real IndexedDB)',()=>{
  const api='https://gym.test/api/v1',source=api+'|1|5|7',personal=personalOwner(api,1),assigned=assignedOwner(api,1,[5,7]);
  const execution:Execution={uuid:'personal-execution',client_execution_id:'original-start',status:'in_progress',revision:3,started_at:'2026-11-15T00:00:00Z',sets:[],prescription:{content:{title:'Original',sections:[]}}};
  const values:SetValues={status:'completed',type:'work',load_mode:'unknown',actual_reps:8,actual_load:null,load_unit:null,actual_rir:'2',notes:null,performed_at:'2026-11-15T00:01:00Z'};
  const proof:OwnershipProof={user_app_id:1,contexts:[[5,7]],current_context:[5,7],personal:['personal-execution'],assigned:['assigned-execution'],assignments:[9]};
  let store:WorkoutOfflineStore,name:string;
  beforeEach(async()=>{
    name='migration-'+crypto.randomUUID();store=new WorkoutOfflineStore(name);
    await store.change(source,row=>{
      row.executions[execution.uuid]={execution:structuredClone(execution),assignment:null,records:{a:{values,serverRevision:2,sync:'pending'}}};
      row.queue=[{id:'operation-original',execution:execution.uuid,set:'a',values,expected_revision:2,sent:true}];
      row.closes={[execution.uuid]:{id:'close-original',execution:execution.uuid,revision:3,sent:true,state:'pending'}};
      row.starts={'9':'assigned-start-original'};
      row.generatedStarts={draft:{requestId:'uncertain-start',payload:{equipment:['barbell'],exercises:[]} as any}};
    });
  });
  afterEach(async()=>{await store.close();await new Promise<void>((resolve,reject)=>{const r=indexedDB.deleteDatabase(name);r.onsuccess=()=>resolve();r.onerror=()=>reject(r.error);});});
  const migrate=()=>store.migrate(rows=>migrateAccounts(rows,api,1,proof));
  it('atomically preserves operations and uncertain bodies across reopening, without replaying transferred copies',async()=>{
    const original=await store.read(source);expect(await migrate()).toBe(0);
    await store.close();store=new WorkoutOfflineStore(name);
    const moved=await store.read(personal);expect(moved.queue).toEqual(original.queue);expect(moved.executions).toEqual(original.executions);expect(moved.closes).toEqual(original.closes);expect(moved.generatedStarts).toEqual(original.generatedStarts);
    expect((await store.read(assigned)).starts['9']).toBe('assigned-start-original');
    await store.change(personal,row=>{row.queue=[];row.generatedStarts={};});
    expect(await migrate()).toBe(0);expect((await store.read(personal)).queue).toEqual([]);
    expect((await store.read(source)).queue).toEqual(original.queue);
  });
  it('retains conflicting destinations and unproven execution groups without overwriting',async()=>{
    await store.change(personal,row=>{row.executions[execution.uuid]={execution:{...execution,revision:9},assignment:null,records:{}};});
    expect(await migrate()).toBe(1);expect((await store.read(personal)).executions[execution.uuid].execution.revision).toBe(9);
    const unknown={...proof,personal:[]};expect(await store.migrate(rows=>migrateAccounts(rows,api,1,unknown))).toBe(1);
    expect((await store.read(source)).queue[0].id).toBe('operation-original');
  });
  it('waits for live leases and never imports another API or athlete',async()=>{
    await store.change(source,row=>{row.lease={id:'other-window',until:Date.now()+45000};});
    expect(await migrate()).toBe(1);expect((await store.read(personal)).queue.length).toBe(0);
    for(const owner of [api+'|10|5|7','https://another.test/api/v1|1|5|7'])await store.change(owner,row=>{row.starts={'9':'foreign'};});
    await store.change(source,row=>{delete row.lease;});await migrate();
    expect((await store.read(assigned)).starts['9']).toBe('assigned-start-original');
    expect((await store.read(api+'|10|5|7')).transferred).toBeUndefined();
    await expectAsync(store.migrate(rows=>migrateAccounts(rows,api,1,{...proof,user_app_id:10}))).toBeRejected();
  });
  it('imports proven personal history after coach change, retaining old assigned starts',async()=>{
    const changed={...proof,current_context:[6,8],assigned:[]};
    expect(await store.migrate(rows=>migrateAccounts(rows,api,1,changed))).toBe(1);
    expect((await store.read(personal)).queue[0].id).toBe('operation-original');expect((await store.read(assigned)).starts).toEqual({});
  });
  it('rolls back the entire migration if the transaction cannot complete',async()=>{
    await expectAsync(store.migrate(rows=>{migrateAccounts(rows,api,1,proof);throw new DOMException('Full','QuotaExceededError');})).toBeRejected();
    expect((await store.read(source)).transferred).toBeUndefined();expect((await store.read(personal)).executions).toEqual({});
  });
});
