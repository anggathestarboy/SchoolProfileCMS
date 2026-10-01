<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('handle');
            $table->string('title');
            $table->json('permissions');
            $table->timestamps();

            $table->unique('handle', 'roles_handle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
