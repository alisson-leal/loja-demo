<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GRANTS = [
        'Gerente' => ['crediario.vender', 'crediario.receber', 'crediario.gerir', 'condicional.criar', 'condicional.acertar'],
        'Vendedor' => ['crediario.vender', 'crediario.receber', 'condicional.criar', 'condicional.acertar'],
    ];

    public function up(): void
    {
        // Crediário: multa e juros por atraso, definidos na forma de pagamento.
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->decimal('late_fee_percent', 5, 2)->default(0)->after('max_installments');
            $table->decimal('interest_percent', 5, 2)->default(0)->after('late_fee_percent');
        });

        // Recebimento das parcelas do crediário, com o que foi cobrado de multa e juros.
        Schema::table('receivables', function (Blueprint $table) {
            $table->decimal('received_amount', 10, 2)->nullable()->after('received_at');
            $table->boolean('charges_waived')->default(false)->after('received_amount');
            $table->foreignId('received_method_id')->nullable()->after('charges_waived')->constrained('payment_methods')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 10, 2)->nullable()->after('notes');
        });

        // Venda condicional: o cliente leva as peças para provar; o estoque fica reservado até o acerto.
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->date('due_date')->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('description', 220);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('kept_quantity')->default(0);
            $table->timestamps();
        });

        $now = now();
        // Instalação em uso ganha o crediário aqui; numa instalação nova ele vem do PaymentMethodsSeeder.
        if (DB::table('payment_methods')->exists() && ! DB::table('payment_methods')->where('type', 'crediario')->exists()) {
            DB::table('payment_methods')->insert([
                'name' => 'Crediário (carnê)', 'type' => 'crediario', 'is_active' => true,
                'position' => (int) DB::table('payment_methods')->max('position') + 1,
                'fee_percent' => 0, 'installment_fee_percent' => null, 'settlement_days' => 30, 'max_installments' => 6,
                'late_fee_percent' => 2, 'interest_percent' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            foreach ($role ? $permissions : [] as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('credit_limit'));
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_method_id');
            $table->dropColumn(['received_amount', 'charges_waived']);
        });
        Schema::table('payment_methods', fn (Blueprint $table) => $table->dropColumn(['late_fee_percent', 'interest_percent']));
    }
};
