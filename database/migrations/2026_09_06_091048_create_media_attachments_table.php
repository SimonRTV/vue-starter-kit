<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('attachable');
            $table->timestamps();
            $table->unique(['media_id', 'attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
