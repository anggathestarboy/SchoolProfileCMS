<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemaps', function (Blueprint $table) {
            $table->id();
            $table->uuid('origin_id')->nullable()->comment('References entries.id (nullable)');
            $table->string('collection');
            $table->string('url');
            $table->string('changefreq')->default('weekly');
            $table->decimal('priority', 2, 1)->default(0.5);
            $table->timestamp('lastmod')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->string('site')->default('indonesia');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['site', 'collection', 'origin_id'], 'sitemaps_site_collection_origin_id_unique');
            $table->index(['site', 'collection', 'url'], 'sitemaps_site_collection_url_index');

            $table->foreign('collection', 'sitemaps_collection_foreign')
                ->references('handle')->on('collections');
            $table->foreign('site', 'sitemaps_site_foreign')
                ->references('handle')->on('sites');
        });
    }

    public function down(): void
    {
        Schema::table('sitemaps', function (Blueprint $table) {
            $table->dropForeign('sitemaps_collection_foreign');
            $table->dropForeign('sitemaps_site_foreign');
        });
        Schema::dropIfExists('sitemaps');
    }
};
