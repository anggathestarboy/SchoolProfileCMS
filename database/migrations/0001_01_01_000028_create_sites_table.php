<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('handle');
            $table->string('name');
            $table->string('url');
            $table->string('locale');
            $table->string('lang');
            $table->json('attributes');
            $table->integer('order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('handle', 'sites_handle_unique');
            $table->index('order', 'sites_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
