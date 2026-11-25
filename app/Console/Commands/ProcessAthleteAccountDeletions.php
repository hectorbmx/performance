<?php
namespace App\Console\Commands;

use App\Services\AthleteAccountDeletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAthleteAccountDeletions extends Command
{
    protected $signature = 'app:account-deletions {--process : Completar cuentas independientes sin datos legados; reintentar confirmaciones}';
    protected $description = 'Listar solicitudes de eliminación sin datos personales; procesar cuentas independientes verificables.';
    public function handle(AthleteAccountDeletion $service): int
    {
        $rows=DB::table('athlete_account_deletions')->where(function($q){$q->where('status','requested')->orWhereNull('notification_sent_at');})->orderBy('due_at')->limit(100)->get();
        foreach ($rows as $row) {
            $status=$row->status;
            if ($this->option('process')) {
                try { $status=$service->completeUnlinked($row->id) ? 'completed' : 'platform_review_required'; }
                catch (\Throwable $e) { $status='retry_required'; }
            }
            $this->line($row->id.' | '.$status.' | limite '.$row->due_at);
        }
        $this->info('Solicitudes: '.$rows->count().'. Los casos comerciales/legados requieren revisión de datos por la plataforma; no se eliminan automáticamente.');
        return self::SUCCESS;
    }
}
