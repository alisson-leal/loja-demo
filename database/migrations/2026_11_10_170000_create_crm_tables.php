<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('cpf')->index();
            $table->string('gender', 20)->nullable()->after('birth_date');
            $table->string('whatsapp', 20)->nullable()->after('phone')->index();
            $table->string('address', 190)->nullable()->after('email');
            $table->string('neighborhood', 100)->nullable()->after('address');
            $table->string('city', 100)->nullable()->after('neighborhood')->index();
            $table->string('zip_code', 8)->nullable()->after('city');
            $table->text('notes')->nullable()->after('zip_code');
        });

        Schema::create('customer_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('birth_date')->nullable()->index();
            $table->string('clothing_size', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->json('filters');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Quem autorizou quando o operador não tem a permissão.
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cancel_authorized_by')->nullable()->after('cancelled_by')->constrained('users')->restrictOnDelete();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreignId('authorized_by')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', fn (Blueprint $table) => $table->dropConstrainedForeignId('authorized_by'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('cancel_authorized_by'));
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_children');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender', 'whatsapp', 'address', 'neighborhood', 'city', 'zip_code', 'notes']);
        });
    }
};
