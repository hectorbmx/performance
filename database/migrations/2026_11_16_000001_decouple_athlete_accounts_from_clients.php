<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The original FK retained its users_app name after the table rename.
        if (DB::getDriverName() === 'mysql') {
            $keys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_apps' AND COLUMN_NAME='client_id' AND REFERENCED_TABLE_NAME='clients'");
            if (count($keys) !== 1 || !preg_match('/^[a-zA-Z0-9_]+$/', $keys[0]->CONSTRAINT_NAME)) {
                throw new RuntimeException('Revisar FK de user_apps.client_id antes de continuar.');
            }
            $fk = $keys[0]->CONSTRAINT_NAME;
            // MariaDB cannot reuse the same FK name during one atomic ALTER TABLE.
            DB::statement("ALTER TABLE user_apps DROP FOREIGN KEY `$fk`, MODIFY client_id BIGINT UNSIGNED NULL, ADD CONSTRAINT `user_apps_optional_client_foreign` FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL");
        } else {
            // SQLite in isolated tests: DBAL rebuilds the table preserving data/indexes.
            $manager = DB::connection()->getDoctrineSchemaManager();
            $before = $manager->listTableDetails('user_apps');
            $after = clone $before;
            $after->getColumn('client_id')->setNotnull(false);
            foreach ($after->getForeignKeys() as $name=>$key) {
                if ($key->getLocalColumns() === ['client_id']) $after->removeForeignKey($name);
            }
            $after->addForeignKeyConstraint('clients', ['client_id'], ['id'], ['onDelete'=>'SET NULL'], 'user_apps_client_id_foreign');
            $manager->alterTable($manager->createComparator()->compareTables($before, $after));
        }
        Schema::table('user_apps', function (Blueprint $table) {
            $table->timestamp('self_registered_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::create('athlete_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_app_id')->unique()->constrained('user_apps')->restrictOnDelete();
            $table->string('first_name',100);
            $table->string('last_name',100)->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_version',40)->nullable();
            $table->timestamps();
        });
        DB::table('user_apps as u')->join('clients as c','c.id','=','u.client_id')
            ->select(['u.id','c.first_name','c.last_name'])->orderBy('u.id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) DB::table('athlete_profiles')->insert([
                    'user_app_id'=>$row->id,'first_name'=>mb_substr($row->first_name,0,100),
                    'last_name'=>$row->last_name === null ? null : mb_substr($row->last_name,0,100),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }, 'u.id', 'id');
        Schema::create('athlete_email_actions', function (Blueprint $table) {
            $table->string('email');
            $table->string('purpose',20);
            $table->string('token_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
            $table->primary(['email','purpose']);
        });
        Schema::create('athlete_setup_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        // Preserve unambiguous, pending legacy invitations without sharing the coach reset store.
        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('athlete_setup_tokens')->insertUsing(['email','token','created_at'],
                DB::table('user_apps as u')->join('password_reset_tokens as p','p.email','=','u.email')
                    ->whereNull('u.password')->whereNull('u.deleted_at')
                    ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('users as c')->whereRaw('LOWER(c.email) = LOWER(u.email)'))
                    ->select(['u.email','p.token','p.created_at']));
        }
        Schema::create('athlete_account_deletions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_app_id')->nullable()->unique()->constrained('user_apps')->nullOnDelete();
            $table->string('status',20)->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('due_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('notification_email')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('GYM-01 conserva cuentas y datos: corregir hacia adelante, sin rollback destructivo.');
    }
};
