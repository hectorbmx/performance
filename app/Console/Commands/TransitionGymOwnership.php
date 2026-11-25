<?php
namespace App\Console\Commands;

use App\Services\WorkoutOwnershipTransition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TransitionGymOwnership extends Command
{
    protected $signature='gym:ownership {--apply : Aplicar solo propietarios demostrables, conservando datos originales}';
    protected $description='Auditar la propiedad legada de GYM; por defecto no modifica la base.';
    public function handle(WorkoutOwnershipTransition $transition): int
    {
        $report=$this->option('apply') ? $transition->apply() : $transition->report();
        $directory=storage_path('app/private/gym02');File::ensureDirectoryExists($directory);
        $mode=$this->option('apply')?'applied':'dry-run';
        $path=$directory.'/ownership-'.$mode.'-'.now()->format('Ymd-His').'.json';
        File::put($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->line(json_encode(['mode'=>$mode,'counts'=>$report['counts'],'unresolved'=>count($report['exceptions']),'report'=>$path],JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
