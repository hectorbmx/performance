<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/build/') && is_file(__DIR__.'/../../public'.$path)) return false;
if (!str_starts_with($path, '/coach/tips')) { http_response_code(404); exit; }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$db = __DIR__.'/preview.sqlite';
if (!file_exists($db)) touch($db);
config(['database.default'=>'tips_preview','database.connections.tips_preview'=>['driver'=>'sqlite','database'=>$db,'prefix'=>'','foreign_key_constraints'=>true], 'session.driver'=>'file','session.files'=>__DIR__.'/sessions','session.cookie'=>'cp3_validation','cache.default'=>'array','filesystems.disks.local.root'=>__DIR__.'/images']);
if (!Illuminate\Support\Facades\Schema::hasTable('tips')) {
 foreach (['2014_10_12_000000_create_users_table.php','2026_01_04_234242_create_permission_tables.php','2026_09_11_000001_create_tips_table.php'] as $migration) (require database_path('migrations/'.$migration))->up();
 $user = App\Models\User::factory()->create(['name'=>'Coach de prueba','email_verified_at'=>now()]);
 $user->assignRole(Spatie\Permission\Models\Role::findOrCreate('coach','web'));
 Illuminate\Support\Facades\Schema::create('coach_subscriptions', function ($table) { $table->id(); $table->unsignedBigInteger('coach_id'); $table->date('ends_at'); $table->string('billing_status'); $table->softDeletes(); });
 Illuminate\Support\Facades\DB::table('coach_subscriptions')->insert(['coach_id'=>$user->id,'ends_at'=>'2030-01-01','billing_status'=>'paid']);
}
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::firstOrFail());
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
