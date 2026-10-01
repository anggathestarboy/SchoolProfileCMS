<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID');
            $table->string('site');
            $table->uuid('origin_id')->nullable()->comment('Self-ref for localization (nullable)');
            $table->tinyInteger('published')->default(1);
            $table->string('status')->default('draft');
            $table->string('slug')->nullable();
            $table->string('uri')->nullable();
            $table->string('date')->nullable();
            $table->integer('order')->nullable();
            $table->string('collection');
            $table->string('blueprint', 30)->nullable();
            $table->json('data');
            $table->json('schema')->nullable();
            $table->json('seo')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('site', 'entries_site_index');
            $table->index('origin_id', 'entries_origin_id_index');
            $table->index('uri', 'entries_uri_index');
            $table->index('date', 'entries_date_index');
            $table->index('order', 'entries_order_index');
            $table->index('collection', 'entries_collection_index');
            $table->index('blueprint', 'entries_blueprint_index');

            $table->foreign('collection', 'entries_collection_foreign')
                ->references('handle')->on('collections');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign('entries_collection_foreign');
        });
        Schema::dropIfExists('entries');
    }
};
