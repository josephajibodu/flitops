<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_security_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('advisory', 100);
            $table->string('status', 50)->default('unchecked')->index();
            $table->text('detail')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('fixed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'advisory']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_security_checks');
    }
};
