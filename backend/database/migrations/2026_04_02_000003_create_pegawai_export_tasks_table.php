<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_export_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // No FK: beberapa DB legacy punya tipe `users.id` yang tidak kompatibel dengan foreignId().
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->default('queued')->index();
            $table->string('format', 10);
            $table->string('scope', 10)->default('all');
            $table->string('separator', 12)->nullable();
            $table->json('filters')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_export_tasks');
    }
};

