<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->index()->after('email_verified_at');
            $table->timestamp('invitation_sent_at')->nullable()->after('disabled_at');
            $table->timestamp('last_login_at')->nullable()->after('invitation_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'disabled_at',
                'invitation_sent_at',
                'last_login_at',
            ]);
        });
    }
};
