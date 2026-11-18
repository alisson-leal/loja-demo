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
        'Gerente' => ['comissoes.ver_propria', 'comissoes.ver', 'comissoes.fechar'],
        'Vendedor' => ['comissoes.ver_propria'],
    ];

    public function up(): void
    {
        // Percentual de comissão do vendedor sobre as vendas líquidas. Nulo: não recebe comissão.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('phone');
        });

        // Fechamento mensal: congela os valores e gera a conta a pagar de cada vendedor.
        Schema::create('commission_closings', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('sales_total', 12, 2);
            $table->decimal('returns_total', 12, 2);
            $table->decimal('base_total', 12, 2);
            $table->decimal('percent', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->foreignId('payable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['period', 'user_id']);
        });

        $now = now();
        DB::table('expense_categories')->insertOrIgnore([
            'name' => 'Comissões', 'system_key' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

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
        Schema::dropIfExists('commission_closings');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('commission_percent'));
    }
};
