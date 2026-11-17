<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_blocked')->default(false)->index()->after('password');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
            $table->foreignId('blocked_by')->nullable()->after('blocked_reason')
                ->constrained('users')->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('blocked_by');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn([
                'phone', 'is_blocked', 'blocked_at', 'blocked_reason',
                'must_change_password', 'password_changed_at', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
