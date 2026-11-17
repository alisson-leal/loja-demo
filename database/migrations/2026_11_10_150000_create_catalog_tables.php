<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('document', 14)->nullable()->unique();
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address', 190)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('variation_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('variation_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_attribute_id')->constrained()->restrictOnDelete();
            $table->string('value', 60);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['variation_attribute_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 30)->nullable()->unique();
            $table->string('sku', 64)->nullable()->unique();
            $table->string('name', 150)->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // IDs dos valores em ordem crescente: impede combinação repetida.
            $table->string('combination_key', 190)->default('');
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            // Colunas com sinal: o cálculo disponível = saldo - reservado pode ser negativo.
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'combination_key']);
        });

        Schema::create('product_variant_value', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_value_id')->constrained()->restrictOnDelete();
            $table->primary(['product_variant_id', 'variation_value_id'], 'variant_value_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_value');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('variation_values');
        Schema::dropIfExists('variation_attributes');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
