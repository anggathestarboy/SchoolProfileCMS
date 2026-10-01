<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_set_variables', function (Blueprint $table) {
            $table->id();
            $table->string('handle');
            $table->string('locale')->nullable();
            $table->json('data');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('handle', 'global_set_variables_handle_index');

            $table->foreign('handle', 'global_set_variables_handle_foreign')
                ->references('handle')->on('global_sets');
        });
    }

    public function down(): void
    {
        Schema::table('global_set_variables', function (Blueprint $table) {
            $table->dropForeign('global_set_variables_handle_foreign');
        });
        Schema::dropIfExists('global_set_variables');
    }
};
