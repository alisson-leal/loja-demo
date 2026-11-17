<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadastro mínimo para identificar o cliente na venda; a Fase 4 amplia esta tabela.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('cpf', 11)->nullable()->unique();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email', 190)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('opening_amount', 10, 2);
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('counted_amount', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->string('closing_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Impede que um duplo clique registre a mesma venda duas vezes.
            $table->string('checkout_token', 36)->unique();
            // Distingue loja física e futura loja virtual nos relatórios.
            $table->string('channel', 20)->default('store')->index();
            $table->string('status', 20)->default('completed')->index();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            // Cópia dos dados do momento da venda: alterações futuras não mudam o histórico.
            $table->string('description', 220);
            $table->string('sku', 64);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });

        Schema::create('store_credits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_credit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('received_amount', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->decimal('total', 10, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('store_credit_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->boolean('restocked');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('store_credits');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customers');
    }
};
