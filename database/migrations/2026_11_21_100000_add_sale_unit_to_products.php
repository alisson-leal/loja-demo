<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venda fracionada: o produto é vendido por unidade, metro ou quilo. Nos dois
 * últimos casos as quantidades (estoque, vendas, movimentações) ficam em
 * milésimos. A unidade é copiada para o item da venda, como o preço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit', 3)->default('un')->after('sale_price');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit', 3)->default('un')->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn('unit'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('unit'));
    }
};
