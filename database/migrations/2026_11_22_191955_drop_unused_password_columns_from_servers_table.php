<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sudo and database passwords live in server_credentials; these copies
 * on the servers table were never written or read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['sudo_password', 'database_password']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->text('sudo_password')->nullable();
            $table->text('database_password')->nullable();
        });
    }
};
