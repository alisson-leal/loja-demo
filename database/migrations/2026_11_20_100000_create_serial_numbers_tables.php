<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Número de série e garantia. Produtos com controle de série têm cada unidade
 * registrada; a garantia (em meses) é copiada para o item no momento da venda.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Gerente' => ['vendas.garantia'],
        'Vendedor' => ['vendas.garantia'],
        'Estoquista' => ['vendas.garantia'],
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('tracks_serial')->default(false)->after('is_active');
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('tracks_serial');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->json('serials')->nullable()->after('total');
            $table->date('warranty_until')->nullable()->after('serials');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->json('serials')->nullable()->after('quantity');
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('serial', 60);
            $table->string('status', 20)->index();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_variant_id', 'serial']);
            $table->index('serial');
        });

        Schema::create('serial_number_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_number_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('description', 255);
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

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
        Schema::dropIfExists('serial_number_events');
        Schema::dropIfExists('serial_numbers');
        Schema::table('stock_transfer_items', fn (Blueprint $t) => $t->dropColumn('serials'));
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn(['serials', 'warranty_until']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['tracks_serial', 'warranty_months']));
    }
};
