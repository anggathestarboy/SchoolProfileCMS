<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets_meta', function (Blueprint $table) {
            $table->id();
            $table->string('container');
            $table->string('folder');
            $table->string('basename');
            $table->string('filename');
            $table->char('extension', 10);
            $table->string('path');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['container', 'folder', 'basename'], 'assets_meta_container_folder_basename_unique');
            $table->index('container', 'assets_meta_container_index');
            $table->index('folder', 'assets_meta_folder_index');
            $table->index('basename', 'assets_meta_basename_index');
            $table->index('filename', 'assets_meta_filename_index');
            $table->index('extension', 'assets_meta_extension_index');
            $table->index('path', 'assets_meta_path_index');

            $table->foreign('container', 'assets_meta_container_foreign')
                ->references('handle')->on('asset_containers');
        });
    }

    public function down(): void
    {
        Schema::table('assets_meta', function (Blueprint $table) {
            $table->dropForeign('assets_meta_container_foreign');
        });
        Schema::dropIfExists('assets_meta');
    }
};
