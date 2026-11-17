<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Catalog\Attributes;
use App\Livewire\Catalog\Categories;
use App\Livewire\Dashboard;
use App\Livewire\Products\Form;
use App\Livewire\Suppliers\Form as SupplierForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariationAttribute;
use App\Services\CatalogService;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Category $category;

    private VariationAttribute $size;

    private VariationAttribute $color;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Roupas']);
        $this->size = VariationAttribute::create(['name' => 'Tamanho', 'position' => 1]);
        $this->color = VariationAttribute::create(['name' => 'Cor', 'position' => 2]);

        foreach (['2', '4'] as $value) {
            $this->size->values()->create(['value' => $value]);
        }

        foreach (['Rosa', 'Azul'] as $value) {
            $this->color->values()->create(['value' => $value]);
        }
    }

    private function fillBasics($component)
    {
        return $component->set('name', 'Camiseta Infantil')
            ->set('categoryId', (string) $this->category->id)
            ->set('costPrice', '20.00')
            ->set('salePrice', '50.00');
    }

    public function test_simple_product_gets_one_sku_and_automatic_codes(): void
    {
        $this->fillBasics(Livewire::actingAs($this->admin())->test(Form::class))
            ->call('applyBasePrices')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('products.index'));

        $product = Product::sole();
        $this->assertSame('LD'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT), $product->internal_code);
        $this->assertSame($product->internal_code, $product->sku);

        $variant = ProductVariant::sole();
        $this->assertSame($product->sku, $variant->sku);
        $this->assertSame('50.00', $variant->sale_price);
        $this->assertSame('20.00', $variant->cost_price);
        $this->assertSame(0, $variant->stock_on_hand);
    }

    public function test_combinations_generate_one_independent_sku_each(): void
    {
        $sizeIds = $this->size->values->pluck('id')->map(fn ($id) => (string) $id)->all();
        $colorIds = $this->color->values->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->fillBasics(Livewire::actingAs($this->admin())->test(Form::class))
            ->set("selectedValues.{$this->size->id}", $sizeIds)
            ->set("selectedValues.{$this->color->id}", $colorIds)
            ->call('generate')
            ->assertCount('rows', 4)
            ->call('save')
            ->assertHasNoErrors();

        $variants = ProductVariant::with('values.attribute', 'product')->get();
        $this->assertCount(4, $variants);
        $this->assertCount(4, $variants->pluck('sku')->unique());
        $this->assertContains('2 / Rosa', $variants->map->label()->all());

        app(StockService::class)->register($variants[0], MovementType::Entry, 3, 'Teste', null);
        $this->assertSame(3, $variants[0]->fresh()->stock_on_hand);
        $this->assertSame(0, $variants[1]->fresh()->stock_on_hand);
    }

    public function test_new_attribute_and_value_work_without_code_changes(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Attributes::class)
            ->set('newAttribute', 'Tecido')
            ->call('addAttribute')
            ->assertHasNoErrors();

        $fabric = VariationAttribute::where('name', 'Tecido')->sole();

        Livewire::actingAs($admin)->test(Attributes::class)
            ->set("newValues.{$fabric->id}", 'Algodão')
            ->call('addValue', $fabric->id)
            ->assertHasNoErrors();

        $value = $fabric->values()->sole();

        $this->fillBasics(Livewire::actingAs($admin)->test(Form::class))
            ->set("selectedValues.{$fabric->id}", [(string) $value->id])
            ->call('generate')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Algodão', ProductVariant::with('values.attribute')->sole()->label());
    }

    public function test_duplicate_sku_or_barcode_is_refused(): void
    {
        $sizeIds = $this->size->values->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->fillBasics(Livewire::actingAs($this->admin())->test(Form::class))
            ->set("selectedValues.{$this->size->id}", $sizeIds)
            ->call('generate')
            ->set('rows.0.barcode', '789')
            ->set('rows.1.barcode', '789')
            ->call('save');

        $this->assertSame(0, Product::count());
    }

    public function test_variant_with_movements_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $this->fillBasics(Livewire::actingAs($admin)->test(Form::class))->call('save');
        $product = Product::sole();
        $variant = ProductVariant::sole();
        app(StockService::class)->register($variant, MovementType::Entry, 1, 'Teste', $admin);

        $this->expectException(BusinessRuleException::class);
        app(ProductService::class)->save($admin, $product, [
            'name' => $product->name, 'description' => null, 'category_id' => $product->category_id,
            'brand_id' => null, 'supplier_id' => null, 'internal_code' => null, 'sku' => null,
            'cost_price' => '20', 'sale_price' => '50', 'is_active' => true,
        ], [[
            'id' => null, 'value_ids' => [$this->size->values->first()->id], 'sku' => 'NOVO', 'barcode' => '',
            'cost_price' => '20', 'sale_price' => '50', 'min_stock' => '0', 'is_active' => true,
        ]], [$variant->id]);
    }

    public function test_cost_changes_are_recorded_and_hidden_from_users_without_permission(): void
    {
        $admin = $this->admin();
        $this->fillBasics(Livewire::actingAs($admin)->test(Form::class))
            ->call('applyBasePrices')
            ->call('save');

        $variant = ProductVariant::sole();
        $this->assertDatabaseHas('variant_cost_history', ['product_variant_id' => $variant->id, 'previous_cost' => '0.00', 'new_cost' => '20.00']);

        $editor = $this->userWith(['produtos.editar']);

        Livewire::actingAs($editor)->test(Form::class, ['product' => $variant->product_id])
            ->assertSet('rows.0.cost_price', '0.00')
            ->assertDontSee('Custo base')
            ->set('rows.0.sale_price', '60.00')
            ->call('save')
            ->assertHasNoErrors();

        $variant->refresh();
        $this->assertSame('20.00', $variant->cost_price);
        $this->assertSame('60.00', $variant->sale_price);
        $this->assertSame('20.00', $variant->product->cost_price);
    }

    public function test_images_are_stored_validated_and_served_without_symlink(): void
    {
        Storage::fake('public');

        $this->fillBasics(Livewire::actingAs($this->admin())->test(Form::class))
            ->set('uploads', [UploadedFile::fake()->image('foto.jpg', 400, 400)])
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        $image = Product::sole()->images()->sole();
        Storage::disk('public')->assertExists($image->path);
        $this->get($image->url())->assertOk();
        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/outra-pasta/arquivo.txt')->assertNotFound();
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('uploads', [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')])
            ->assertHasErrors('uploads.0');
    }

    public function test_product_pages_require_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/produtos')->assertForbidden();
        $this->actingAs($this->userWith(['produtos.ver']))->get('/produtos')->assertOk();
        $this->actingAs($this->userWith(['produtos.ver']))->get('/produtos/novo')->assertForbidden();
    }

    public function test_category_in_use_cannot_be_deleted_and_nesting_is_limited(): void
    {
        $child = Category::create(['name' => 'Vestidos', 'parent_id' => $this->category->id]);
        $catalog = app(CatalogService::class);

        Livewire::actingAs($this->admin())->test(Categories::class)->call('delete', $this->category->id);
        $this->assertDatabaseHas('categories', ['id' => $this->category->id]);

        $this->expectException(BusinessRuleException::class);
        $catalog->saveCategory(null, 'Terceiro nível', $child->id, true);
    }

    public function test_supplier_form_normalizes_document_and_validates_length(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(SupplierForm::class)
            ->set('name', 'Malharia X')
            ->set('document', '12.345.678/0001-90')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('12345678000190', Supplier::sole()->document);

        Livewire::actingAs($admin)->test(SupplierForm::class)
            ->set('name', 'Outro')
            ->set('document', '123')
            ->call('save')
            ->assertHasErrors('document');
    }

    public function test_dashboard_shows_stock_indicators_filtered_by_category(): void
    {
        $other = Category::create(['name' => 'Calçados']);

        foreach ([[$this->category, 'A'], [$other, 'B']] as [$category, $sku]) {
            $product = Product::create(['name' => "Produto {$sku}", 'category_id' => $category->id]);
            $variant = new ProductVariant(['product_id' => $product->id, 'sku' => $sku, 'min_stock' => 5]);
            $variant->stock_on_hand = 2;
            $variant->save();
        }

        Livewire::actingAs($this->userWith(['dashboard.ver', 'estoque.ver']))->test(Dashboard::class)
            ->assertViewHas('stock', fn ($stock) => $stock['low'] === 2 && $stock['products'] === 2)
            ->set('categoryId', (string) $other->id)
            ->assertViewHas('stock', fn ($stock) => $stock['low'] === 1 && $stock['products'] === 1);
    }

    public function test_variation_attributes_are_listed_on_the_screens(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Attributes::class)->assertSee('Tamanho')->assertSee('Rosa');
        Livewire::actingAs($admin)->test(Form::class)->assertSee('Tamanho')->assertSee('Rosa');
    }

    public function test_default_roles_receive_phase_two_permissions(): void
    {
        $this->assertTrue(Role::findByName('Estoquista')->hasPermissionTo('estoque.inventario'));
        $this->assertTrue(Role::findByName('Vendedor')->hasPermissionTo('produtos.ver'));
        $this->assertFalse(Role::findByName('Vendedor')->hasPermissionTo('produtos.ver_custo'));
        $this->assertFalse(Role::findByName('Gerente')->hasPermissionTo('estoque.negativo'));
    }
}
