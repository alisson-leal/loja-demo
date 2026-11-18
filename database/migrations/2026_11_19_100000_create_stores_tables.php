<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Várias lojas: cada loja tem estoque, caixa e vendas próprios. Os dados que
 * já existem passam a pertencer à loja principal.
 */
return new class extends Migration
{
    /** Tabelas que passam a saber de qual loja são. */
    private const SCOPED = ['stock_movements', 'stock_entries', 'stock_reservations', 'inventories', 'cash_sessions', 'sales', 'consignments'];

    private const GRANTS = [
        'Gerente' => ['lojas.ver_todas', 'estoque.transferir'],
        'Estoquista' => ['estoque.transferir'],
    ];

    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('code', 10)->unique();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('phone', 20)->nullable();
            $table->string('address', 190)->nullable();
            $table->string('city', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('store_user', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_id', 'user_id']);
        });

        // Saldo de cada loja. As colunas de product_variants viram o total da rede.
        Schema::create('store_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'product_variant_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 20)->default('in_transit')->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        $now = now();
        $mainId = DB::table('stores')->insertGetId([
            'name' => 'Loja principal', 'code' => 'PRINC', 'is_main' => true, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (self::SCOPED as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete());
            DB::table($table)->update(['store_id' => $mainId]);
        }

        // Despesas podem ser de uma loja ou gerais da rede (sem loja).
        Schema::table('payables', fn (Blueprint $t) => $t->foreignId('store_id')->nullable()->after('id')->constrained()->nullOnDelete());

        DB::table('product_variants')->where(fn ($q) => $q->where('stock_on_hand', '!=', 0)->orWhere('stock_reserved', '!=', 0))
            ->orderBy('id')->select('id', 'stock_on_hand', 'stock_reserved')->get()
            ->chunk(500)
            ->each(fn ($rows) => DB::table('store_stocks')->insert($rows->map(fn ($v) => [
                'store_id' => $mainId, 'product_variant_id' => $v->id, 'on_hand' => $v->stock_on_hand,
                'reserved' => $v->stock_reserved, 'created_at' => $now, 'updated_at' => $now,
            ])->all()));

        DB::table('users')->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('store_user')->insert(['store_id' => $mainId, 'user_id' => $id]));

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
        Schema::table('payables', fn (Blueprint $t) => $t->dropConstrainedForeignId('store_id'));

        foreach (self::SCOPED as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('store_id'));
        }

        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('store_stocks');
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('stores');
    }
};
