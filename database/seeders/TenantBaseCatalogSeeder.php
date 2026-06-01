<?php

namespace Database\Seeders;

use App\Services\TenantBaseCatalogService;
use Illuminate\Database\Seeder;

class TenantBaseCatalogSeeder extends Seeder
{
    public function run(TenantBaseCatalogService $catalogs): void
    {
        $catalogs->seedForAllCoaches();

        $this->command?->info('Catalogos base creados/actualizados para todos los coaches.');
    }
}
