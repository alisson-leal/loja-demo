<?php

namespace Tests\Feature;

use App\Livewire\Products\Import;
use App\Models\Product;
use App\Models\VariationAttribute;
use App\Support\StoreSegment;
use Database\Seeders\VariationAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class SegmentsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_segments_and_children_features(): void
    {
        $expected = ['infantil' => true, 'misto' => true, 'adulto' => false, 'eletronicos' => false, 'armarinho' => false, 'variedades' => false];

        foreach ($expected as $segment => $children) {
            config(['loja.segment' => $segment]);
            $this->assertSame($segment, StoreSegment::current());
            $this->assertSame($children, StoreSegment::childrenEnabled(), $segment);
            $this->assertSame(in_array($segment, ['infantil', 'adulto', 'misto'], true), StoreSegment::isFashion(), $segment);
        }

        config(['loja.segment' => 'desconhecido']);
        $this->assertSame('infantil', StoreSegment::current(), 'Valor inválido volta ao padrão.');
    }

    public function test_electronics_store_starts_with_its_own_variations(): void
    {
        VariationAttribute::query()->each(fn ($a) => $a->values()->delete());
        VariationAttribute::query()->delete();
        config(['loja.segment' => 'eletronicos']);

        $this->seed(VariationAttributesSeeder::class);

        $this->assertSame(['Voltagem', 'Capacidade', 'Cor', 'Modelo'], VariationAttribute::orderBy('position')->pluck('name')->all());
        $this->assertSame(['110 V', '220 V', 'Bivolt'], VariationAttribute::where('name', 'Voltagem')->sole()->values()->orderBy('position')->pluck('value')->all());
    }

    public function test_import_uses_the_segment_variation_and_accepts_old_header(): void
    {
        config(['loja.segment' => 'eletronicos']);
        $this->actingAs($this->userWith(['produtos.criar', 'produtos.ver', 'produtos.ver_custo', 'estoque.entrada']));

        $csv = "nome;categoria;voltagem;cor;preco_venda\nLiquidificador;Eletrodomésticos;110 V;Preto;189,90\nLiquidificador;Eletrodomésticos;220 V;Preto;189,90\nCabo USB-C;Acessórios;;;29,90\n";
        Livewire::test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('eletronicos.csv', $csv))
            ->call('analyze')
            ->assertSee('2</strong> produto(s)', false)
            ->call('import')
            ->assertRedirect(route('products.index'));

        $this->assertSame(2, Product::where('name', 'Liquidificador')->sole()->variants()->count());
        $this->assertTrue(VariationAttribute::where('name', 'Voltagem')->sole()->values()->where('value', '220 V')->exists());
        $this->assertSame(1, Product::where('name', 'Cabo USB-C')->sole()->variants()->count(), 'Produto sem variação.');

        $template = $this->get(route('products.import.template'))->assertOk()->getContent();
        $this->assertStringContainsString('variacao', $template);
        $this->assertStringContainsString('Liquidificador Turbo', $template);
        $this->assertStringNotContainsString('Camiseta', $template);
    }

    public function test_haberdashery_template_and_help_texts(): void
    {
        config(['loja.segment' => 'armarinho']);
        $this->actingAs($this->userWith(['produtos.criar', 'produtos.ver', 'cadastros.ver']));

        $this->assertSame('Largura', StoreSegment::primaryVariation());
        $content = $this->get(route('products.import.template'))->getContent();
        $this->assertStringContainsString('Fita de Cetim', $content);

        $this->get(route('products.import'))->assertOk()->assertSee('vira a variação "Largura"', false);
        $this->get(route('help.show', 'cadastros'))->assertOk()->assertSee('Voltagem');
    }
}
