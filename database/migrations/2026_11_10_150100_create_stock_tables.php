<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reason');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active')->index();
            // Identifica a origem (ex.: "pedido:123") e evita reservar duas vezes.
            $table->string('reference_key', 100)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('variant_cost_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('previous_cost', 10, 2);
            $table->decimal('new_cost', 10, 2);
            $table->string('source', 20);
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->integer('counted_quantity')->nullable();
            $table->integer('expected_quantity')->nullable();
            $table->timestamps();
            $table->unique(['inventory_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('variant_cost_history');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_entries');
    }
};
