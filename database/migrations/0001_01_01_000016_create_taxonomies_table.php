<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('handle');
            $table->string('title');
            $table->json('sites')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('handle', 'taxonomies_handle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
