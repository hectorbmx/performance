<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tip;
use App\Services\Tips\TipService;
use Spatie\Permission\Models\Role;
$name = $argv[2] ?? ('tips_cp4_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3)));
if (!preg_match('/^tips_cp4_[0-9]{14}_[a-f0-9]{6}$/', $name)) throw new RuntimeException('Invalid test schema');
$source = config('database.connections.mysql');
if (($source['driver'] ?? '') !== 'mysql' || $source['database'] === $name) throw new RuntimeException('Unsafe connection');
$server = new PDO('mysql:host='.$source['host'].';port='.$source['port'], $source['username'], $source['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$worker = ($argv[1] ?? '') === 'worker';
if (!$worker) $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.connections.tips_cp4'=>array_merge($source,['database'=>$name]),'cache.default'=>'array','permission.cache.store'=>'array']);
DB::setDefaultConnection('tips_cp4');
if (DB::selectOne('SELECT DATABASE() AS db')->db !== $name) throw new RuntimeException('Connection mismatch');
if ($worker) {
 $tip = (int)$argv[3]; $actor=User::findOrFail((int)$argv[4]); $action=$argv[5]; $hold=($argv[6]??'')==='hold';
 try {
  if ($hold) { DB::beginTransaction(); Tip::whereKey($tip)->lockForUpdate()->firstOrFail(); }
  app(TipService::class)->transition($actor,$tip,$action,$action==='reject'?'Concurrent decision':null);
  if ($hold) { echo "LOCKED\n"; flush(); usleep(1500000); DB::commit(); }
  echo "OK\n";
 } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) { echo "CONFLICT\n"; }
 exit;
}
try {
 foreach (['2014_10_12_000000_create_users_table.php','2026_01_04_234242_create_permission_tables.php','2026_09_11_000001_create_tips_table.php','2026_09_15_000001_add_expires_at_to_tips_table.php'] as $file) (require database_path('migrations/'.$file))->up();
 $coach=User::factory()->create(); $coach->assignRole(Role::findOrCreate('coach','web'));
 $admin=User::factory()->create(); $admin->assignRole(Role::findOrCreate('admin','web'));
 foreach (['approve','withdraw'] as $first) {
  $tip=app(TipService::class)->save($coach,['title'=>'Concurrency test','body'=>'Temporary'],intent:'submit');
  $pipes=[];
  $p=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$tip->id,(string)($first==='approve'?$admin->id:$coach->id),$first,'hold'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if (trim(fgets($pipes[1])) !== 'LOCKED') throw new RuntimeException('Worker failed to acquire lock: '.stream_get_contents($pipes[2]));
  $started=microtime(true); $result='';
  try { app(TipService::class)->transition($admin,$tip->id,$first==='approve'?'reject':'approve','Second review'); $result='UNEXPECTED'; }
  catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) { $result='CONFLICT'; }
  $elapsed=microtime(true)-$started;
  foreach($pipes as $pipe) fclose($pipe);
  $exit=proc_close($p);
  if ($result!=='CONFLICT'||$elapsed<1||$exit!==0) throw new RuntimeException('Concurrency expectation failed');
  echo json_encode(['first'=>$first,'second'=>$result,'blocked_seconds'=>round($elapsed,2),'final_state'=>$tip->fresh()->status->value])."\n";
 }
} finally { DB::disconnect('tips_cp4'); $server->exec('DROP DATABASE `'.$name.'`'); echo "Temporary schema removed\n"; }
