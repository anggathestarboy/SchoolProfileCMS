<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('target');
            $table->integer('status_code')->default(301);
            $table->string('match_type')->default('exact')->comment('exact | prefix | regex');
            $table->tinyInteger('is_active')->default(1);
            $table->bigInteger('hits')->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->tinyInteger('is_external')->default(0);
            $table->tinyInteger('keep_path')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('source', 'redirects_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
