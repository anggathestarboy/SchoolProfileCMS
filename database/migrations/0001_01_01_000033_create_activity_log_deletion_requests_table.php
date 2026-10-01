<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->uuid('requested_by')->nullable();
            $table->string('token_hash')->unique();
            $table->string('status')->default('pending'); // pending, confirmed, executed, expired
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedInteger('deleted_count')->nullable();
            $table->timestamps();

            $table->index(['status', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log_deletion_requests');
    }
};
