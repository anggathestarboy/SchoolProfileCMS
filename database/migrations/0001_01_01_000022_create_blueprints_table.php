<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('namespace')->nullable();
            $table->string('handle');
            $table->json('data');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['handle', 'namespace'], 'blueprints_handle_namespace_unique');
            $table->index('namespace', 'blueprints_namespace_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
