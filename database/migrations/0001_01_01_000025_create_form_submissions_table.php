<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->decimal('id', 14, 4)->primary();
            $table->string('form')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // id is already unique via primary key (form_submissions_id_unique in source ERD)
            $table->unique(['form', 'created_at'], 'form_submissions_form_created_at_unique');
            $table->index('form', 'form_submissions_form_index');

            $table->foreign('form', 'form_submissions_form_foreign')
                ->references('handle')->on('forms');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign('form_submissions_form_foreign');
        });
        Schema::dropIfExists('form_submissions');
    }
};
