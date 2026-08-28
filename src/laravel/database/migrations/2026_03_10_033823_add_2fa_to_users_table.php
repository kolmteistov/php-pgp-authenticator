<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_fa_enabled')->default(false)->after('is_active');
            $table->text('pgp_public_key')->nullable()->after('two_fa_enabled');
            $table->boolean('pgp_verified')->default(false)->after('pgp_public_key');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_fa_enabled', 'pgp_public_key', 'pgp_verified']);
        });
    }
};
