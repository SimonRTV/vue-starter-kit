<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('renderer');
            $table->timestamps();
        });
        Schema::create('page_field_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->json('fields');
            $table->timestamps();
        });
        Schema::create('page_field_set_page_template', function (Blueprint $table) {
            $table->foreignId('page_field_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('page_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->primary(['page_field_set_id', 'page_template_id']);
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('page_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('template_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_template_id');
            $table->dropColumn('template_fields');
        });
        Schema::dropIfExists('page_field_set_page_template');
        Schema::dropIfExists('page_field_sets');
        Schema::dropIfExists('page_templates');
    }
};
