<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trees', function (Blueprint $table) {
            $table->id();
            $table->string('handle');
            $table->string('type')->comment('collection or navigation');
            $table->string('locale')->nullable();
            $table->json('tree')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['handle', 'type', 'locale'], 'trees_handle_type_locale_unique');
            $table->index('type', 'trees_type_index');
            $table->index('locale', 'trees_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trees');
    }
};
