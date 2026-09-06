<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('subject_type', 100);
            $table->string('subject_id');
            $table->string('event', 100);
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['created_at', 'id']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
