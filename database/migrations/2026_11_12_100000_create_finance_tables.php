<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Condições da maquininha: taxa e prazo de recebimento de cada forma de pagamento.
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->decimal('fee_percent', 5, 2)->default(0)->after('is_active');
            $table->decimal('installment_fee_percent', 5, 2)->nullable()->after('fee_percent');
            $table->unsignedSmallInteger('settlement_days')->default(0)->after('installment_fee_percent');
            $table->unsignedTinyInteger('max_installments')->default(1)->after('settlement_days');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('installments')->default(1)->after('received_amount');
        });

        // Contas a receber: o dinheiro de cada pagamento de venda, na data em que cai.
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('installment')->default(1);
            $table->unsignedTinyInteger('installments')->default(1);
            $table->date('due_date')->index();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->date('received_at')->nullable()->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            // Categorias com regra própria: devoluções e compra de mercadorias não entram como despesa no resultado.
            $table->string('system_key', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->string('description', 150);
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document', 60)->nullable();
            $table->date('due_date')->index();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('open')->index();
            $table->date('paid_at')->nullable()->index();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('series')->nullable()->index();
            $table->unsignedSmallInteger('installment')->default(1);
            $table->unsignedSmallInteger('installments')->default(1);
            $table->nullableMorphs('source');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
        });

        $now = now();
        $categories = [
            ['Compra de mercadorias', 'mercadorias'],
            ['Devoluções a clientes', 'devolucoes'],
            ['Aluguel', null],
            ['Energia elétrica', null],
            ['Água', null],
            ['Internet e telefone', null],
            ['Salários e encargos', null],
            ['Impostos', null],
            ['Contador', null],
            ['Taxas bancárias', null],
            ['Marketing', null],
            ['Manutenção', null],
            ['Embalagens e materiais', null],
            ['Outras despesas', null],
        ];

        DB::table('expense_categories')->insert(array_map(fn (array $c) => [
            'name' => $c[0], 'system_key' => $c[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $categories));

        // Vendas anteriores ao financeiro entram como já recebidas, sem taxa, na data da venda.
        DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.status', 'completed')
            ->where('payment_methods.type', '!=', 'store_credit')
            ->where('sale_payments.amount', '>', 0)
            ->orderBy('sale_payments.id')
            ->select('sale_payments.id', 'sale_payments.sale_id', 'sale_payments.payment_method_id', 'sale_payments.amount', 'sales.created_at')
            ->get()
            ->chunk(200)
            ->each(fn ($rows) => DB::table('receivables')->insert($rows->map(fn ($row) => [
                'sale_id' => $row->sale_id,
                'sale_payment_id' => $row->id,
                'payment_method_id' => $row->payment_method_id,
                'due_date' => substr($row->created_at, 0, 10),
                'gross_amount' => $row->amount,
                'fee_amount' => 0,
                'net_amount' => $row->amount,
                'status' => 'received',
                'received_at' => substr($row->created_at, 0, 10),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('receivables');
        Schema::table('sale_payments', fn (Blueprint $table) => $table->dropColumn('installments'));
        Schema::table('payment_methods', fn (Blueprint $table) => $table->dropColumn(['fee_percent', 'installment_fee_percent', 'settlement_days', 'max_installments']));
    }
};
