<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The port a site's own server process listens on, and the flag on the
 * process that serves a site, were named after Laravel Octane. Other apps
 * that run as their own server (Next.js, Nuxt) use them too, so they get
 * names that don't belong to one of them.
 *
 * Each index is renamed apart from its column: SQLite renames an index by
 * recreating it from its stored definition, which must already name the
 * renamed column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->renameColumn('octane_port', 'app_port');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->renameIndex('sites_server_id_octane_port_unique', 'sites_server_id_app_port_unique');
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->renameColumn('runs_octane', 'serves_site');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->renameColumn('serves_site', 'runs_octane');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->renameColumn('app_port', 'octane_port');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->renameIndex('sites_server_id_app_port_unique', 'sites_server_id_octane_port_unique');
        });
    }
};
