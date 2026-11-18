<?php

namespace Database\Seeders;

use App\Enums\CashMovementType;
use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Models\CashSession;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\ExpenseCategory;
use App\Models\MarketingAutomation;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariationAttribute;
use App\Models\VariationValue;
use App\Services\AuditLogger;
use App\Services\BirthdayAutomation;
use App\Services\CampaignService;
use App\Services\CashService;
use App\Services\CatalogService;
use App\Services\CommissionService;
use App\Services\ConsentService;
use App\Services\ConsignmentService;
use App\Services\CrediarioService;
use App\Services\CustomerService;
use App\Services\MarketingEmailSender;
use App\Services\PayableService;
use App\Services\ProductService;
use App\Services\ReceivableService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\StockEntryService;
use App\Services\StockService;
use App\Services\WhatsAppQueueService;
use App\Support\CustomerFilter;
use App\Support\Money;
use App\Support\PermissionCatalog;
use App\Support\SaleCalculator;
use App\Support\StoreSegment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loja de demonstração: dados FICTÍCIOS de uma loja de moda infantil, dos
 * últimos meses até ontem. Tudo passa pelos serviços do sistema (estoque,
 * caixa, vendas, financeiro e marketing), com as datas simuladas, para que os
 * números fechem entre si como em uma loja real. Não roda se já houver vendas.
 */
class DemoDataSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['email' => 'admin@lojademo.test', 'name' => 'Administrador Demo', 'role' => PermissionCatalog::ADMIN_ROLE, 'hint' => 'Acesso completo ao sistema'],
        ['email' => 'gerente@lojademo.test', 'name' => 'Carla Mendes', 'role' => 'Gerente', 'hint' => 'Financeiro, relatórios, marketing e equipe'],
        ['email' => 'vendedora@lojademo.test', 'name' => 'Juliana Rocha', 'role' => 'Vendedor', 'hint' => 'Frente de caixa, clientes e fila do WhatsApp'],
        ['email' => 'estoque@lojademo.test', 'name' => 'Marcos Silva', 'role' => 'Estoquista', 'hint' => 'Produtos, entradas e inventário'],
    ];

    private const FIRST_NAMES = ['Ana', 'Beatriz', 'Camila', 'Daniela', 'Eduarda', 'Fernanda', 'Gabriela', 'Helena', 'Isabela', 'Jéssica', 'Karina', 'Larissa', 'Mariana', 'Natália', 'Patrícia', 'Raquel', 'Sabrina', 'Tatiane', 'Vanessa', 'Yasmin', 'Aline', 'Bruna', 'Carolina', 'Débora', 'Elaine', 'Flávia', 'Giovana', 'Lívia', 'Priscila', 'Renata', 'Rafael', 'Bruno', 'Carlos', 'Diego', 'Felipe', 'Gustavo', 'Henrique', 'Leonardo', 'Marcelo', 'Thiago'];

    private const LAST_NAMES = ['Almeida', 'Barbosa', 'Cardoso', 'Carvalho', 'Castro', 'Costa', 'Dias', 'Ferreira', 'Gomes', 'Lima', 'Lopes', 'Martins', 'Melo', 'Moura', 'Nascimento', 'Oliveira', 'Pereira', 'Ramos', 'Ribeiro', 'Rocha', 'Santos', 'Silva', 'Soares', 'Sousa', 'Teixeira', 'Vieira', 'Araújo', 'Batista', 'Freitas', 'Monteiro'];

    private const CHILD_NAMES = ['Alice', 'Laura', 'Manuela', 'Sofia', 'Valentina', 'Cecília', 'Lorena', 'Maitê', 'Heloísa', 'Isis', 'Miguel', 'Arthur', 'Gael', 'Theo', 'Davi', 'Bernardo', 'Heitor', 'Ravi', 'Samuel', 'Benício'];

    private const NEIGHBORHOODS = ['Centro', 'Jóquei', 'Fátima', 'Ininga', 'São Cristóvão', 'Horto', 'Morada do Sol', 'Dirceu', 'Mocambinho', 'Planalto', 'Noivos', 'Cabral', 'Ilhotas', 'Piçarra'];

    /** [nome, categoria, subcategoria, marca, preço, tamanhos, cores, popularidade] */
    private const PRODUCTS = [
        ['Vestido Floral Primavera', 'Meninas', 'Vestidos', 'Algodão Doce', 129.90, 'kids', ['Rosa', 'Amarelo'], 5],
        ['Vestido Jeans com Babado', 'Meninas', 'Vestidos', 'Arco-Íris Kids', 149.90, 'kids', [], 3],
        ['Vestido de Festa Tule', 'Meninas', 'Vestidos', 'Algodão Doce', 219.90, 'kids', ['Rosa', 'Branco'], 2],
        ['Vestido Moletom Listrado', 'Meninas', 'Vestidos', 'Mundo Miúdo', 99.90, 'kids', [], 3],
        ['Blusa Ciganinha', 'Meninas', 'Blusas', 'Arco-Íris Kids', 69.90, 'kids', ['Branco', 'Rosa'], 4],
        ['Blusa Manga Longa Coração', 'Meninas', 'Blusas', 'Mundo Miúdo', 59.90, 'kids', [], 3],
        ['Cropped Canelado', 'Meninas', 'Blusas', 'Brincar & Vestir', 49.90, 'kids', ['Rosa', 'Amarelo', 'Verde'], 4],
        ['Short Jeans Destroyed', 'Meninas', 'Saias e shorts', 'Arco-Íris Kids', 79.90, 'kids', [], 4],
        ['Saia Tutu', 'Meninas', 'Saias e shorts', 'Algodão Doce', 69.90, 'kids', ['Rosa', 'Branco'], 3],
        ['Conjunto Moletom Unicórnio', 'Meninas', 'Conjuntos', 'Mundo Miúdo', 139.90, 'kids', [], 4],
        ['Conjunto Legging e Bata', 'Meninas', 'Conjuntos', 'Brincar & Vestir', 99.90, 'kids', [], 3],
        ['Camiseta Dinossauro', 'Meninos', 'Camisetas', 'Pequenos Passos', 49.90, 'kids', ['Azul', 'Verde'], 5],
        ['Camiseta Futebol', 'Meninos', 'Camisetas', 'Brincar & Vestir', 54.90, 'kids', ['Azul', 'Vermelho'], 4],
        ['Camiseta Polo Piquet', 'Meninos', 'Camisetas', 'Pequenos Passos', 69.90, 'kids', ['Azul', 'Branco'], 3],
        ['Regata Surf', 'Meninos', 'Camisetas', 'Mundo Miúdo', 39.90, 'kids', [], 3],
        ['Bermuda Sarja', 'Meninos', 'Bermudas', 'Pequenos Passos', 79.90, 'kids', [], 4],
        ['Bermuda Moletom', 'Meninos', 'Bermudas', 'Brincar & Vestir', 59.90, 'kids', [], 3],
        ['Bermuda Tactel Praia', 'Meninos', 'Bermudas', 'Mundo Miúdo', 49.90, 'kids', ['Azul', 'Verde'], 3],
        ['Calça Jeans Slim', 'Meninos', 'Calças', 'Arco-Íris Kids', 119.90, 'kids', [], 3],
        ['Calça Jogger', 'Meninos', 'Calças', 'Pequenos Passos', 89.90, 'kids', [], 3],
        ['Conjunto Super-Herói', 'Meninos', 'Conjuntos', 'Brincar & Vestir', 109.90, 'kids', [], 4],
        ['Conjunto Esportivo', 'Meninos', 'Conjuntos', 'Pequenos Passos', 129.90, 'kids', [], 2],
        ['Body Manga Curta Kit 3', 'Bebês', 'Bodies', 'Algodão Doce', 89.90, 'baby', [], 5],
        ['Body Manga Longa Bichinhos', 'Bebês', 'Bodies', 'Pequenos Passos', 39.90, 'baby', ['Branco', 'Amarelo'], 4],
        ['Macacão Plush Ursinho', 'Bebês', 'Macacões', 'Algodão Doce', 119.90, 'baby', [], 3],
        ['Macacão Suedine Estampado', 'Bebês', 'Macacões', 'Mundo Miúdo', 89.90, 'baby', [], 3],
        ['Tênis Infantil Luzinha', 'Calçados', null, 'Passinho Feliz', 149.90, 'shoes', [], 4],
        ['Sandália Glitter', 'Calçados', null, 'Passinho Feliz', 79.90, 'shoes', [], 3],
        ['Chinelo Personagens', 'Calçados', null, 'Passinho Feliz', 34.90, 'shoes', [], 4],
        ['Kit Laços de Cabelo', 'Acessórios', null, 'Algodão Doce', 29.90, 'none', [], 3],
        ['Boné Aba Reta', 'Acessórios', null, 'Brincar & Vestir', 49.90, 'none', ['Azul', 'Vermelho'], 2],
        ['Mochila Escolar Estrelas', 'Acessórios', null, 'Mundo Miúdo', 169.90, 'none', ['Rosa', 'Azul'], 2],
        ['Meia Kit 3 Pares', 'Acessórios', null, 'Pequenos Passos', 29.90, 'none', [], 4],
    ];

    private const SIZES = [
        'kids' => ['2', '4', '6', '8', '10', '12'],
        'baby' => ['P', 'M', 'G'],
        'shoes' => ['22', '24', '26', '28', '30', '32'],
        'none' => [],
    ];

    private const SUPPLIERS = [
        'Algodão Doce' => 'Confecções Algodão Doce Ltda',
        'Arco-Íris Kids' => 'Arco-Íris Moda Infantil Ltda',
        'Mundo Miúdo' => 'Mundo Miúdo Indústria Têxtil',
        'Brincar & Vestir' => 'Brincar & Vestir Confecções',
        'Pequenos Passos' => 'Pequenos Passos Distribuidora',
        'Passinho Feliz' => 'Passinho Feliz Calçados',
    ];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, int> método => id */
    private array $methods = [];

    /** @var array<int, int> id da variação => saldo em estoque, espelho para escolher o que vender */
    private array $stock = [];

    /** @var array<int, array{weight: int, price: int, supplier: int, cost: string}> */
    private array $variants = [];

    /** @var list<int> clientes, com repetição dos fiéis para comprarem mais */
    private array $customerPool = [];

    /** @var array<string, list<array{sale: int, refund: ?int}>> devoluções agendadas por dia */
    private array $returns = [];

    /** @var array<string, list<int>> condicionais que a cliente volta para acertar, por dia */
    private array $consignmentVisits = [];

    private int $customerSeq = 0;

    private int $barcodeSeq = 0;

    public function __construct(private int $months = 6) {}

    public function run(): void
    {
        if (Sale::exists()) {
            $this->command?->info('Já existem vendas: os dados de demonstração não foram gerados de novo.');

            return;
        }

        mt_srand(20261117);
        $this->call([RolesAndPermissionsSeeder::class, VariationAttributesSeeder::class, PaymentMethodsSeeder::class]);

        // Nenhum e-mail sai durante a geração, e os de marketing não esperam o limite por hora.
        config(['mail.default' => 'array', 'loja.marketing.email_per_hour' => 1000000]);

        $today = CarbonImmutable::today();
        $start = $today->subMonthsNoOverflow($this->months);

        try {
            // Cada etapa grava de uma vez: muito mais rápido que confirmar operação por operação.
            DB::transaction(function () use ($start, $today) {
                $this->at($start->setTime(8, 0));
                $this->createUsers();
                $this->paymentTerms();
                $this->catalog();
                $this->initialStock($start);
                $this->createCustomers($start, 170);
                $this->at($start->setTime(8, 0));
                $this->segments();
                $this->automations();
                $this->fixedBills($start, $today);
            });

            for ($day = $start; $day->lt($today); $day = $day->addDay()) {
                $this->simulateDay($day, $start, $today);
            }

            $this->at($today->subDay()->setTime(20, 0));
            DB::transaction(fn () => $this->finalTouches($today));
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Loja de demonstração gerada: '.Sale::count().' vendas, '.Customer::count().' clientes, '.ProductVariant::count().' SKUs.');
    }

    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow(Carbon::instance($moment->toDateTime()));
    }

    private function chance(float $percent): bool
    {
        return mt_rand(1, 10000) <= $percent * 100;
    }

    /** @template T @param list<T> $items @return T */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function phone(): string
    {
        return '869'.mt_rand(8, 9).str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function createUsers(): void
    {
        $accounts = array_merge(self::ACCOUNTS, [
            ['email' => 'bruna@lojademo.test', 'name' => 'Bruna Lima', 'role' => 'Vendedor', 'hint' => ''],
        ]);

        foreach ($accounts as $account) {
            $user = new User(['name' => $account['name'], 'email' => $account['email'], 'password' => config('loja.demo.password')]);
            $user->forceFill(['must_change_password' => false, 'password_changed_at' => now()])->save();
            $user->assignRole($account['role']);
            $this->users[Str::before($account['email'], '@')] = $user;
        }

        $this->users['vendedora']->forceFill(['commission_percent' => 3])->save();
        $this->users['bruna']->forceFill(['commission_percent' => 2.5])->save();
    }

    /** Código EAN-13 interno (prefixo 2, de uso da própria loja) com dígito verificador. */
    private function ean(int $sequence): string
    {
        $digits = '2000'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $digits.((10 - $sum % 10) % 10);
    }

    private function paymentTerms(): void
    {
        PaymentMethod::where('type', PaymentMethod::DEBIT)->update(['fee_percent' => 1.49, 'settlement_days' => 1]);
        PaymentMethod::where('type', PaymentMethod::CREDIT)->update(['fee_percent' => 3.19, 'installment_fee_percent' => 4.99, 'settlement_days' => 30, 'max_installments' => 6]);

        foreach (PaymentMethod::all() as $method) {
            $this->methods[$method->type] = $method->id;
        }
    }

    private function catalog(): void
    {
        $catalog = app(CatalogService::class);
        $admin = $this->users['admin'];

        $sizes = VariationAttribute::where('name', 'Tamanho')->sole();
        $colors = VariationAttribute::where('name', 'Cor')->sole();
        $shoeSizes = VariationAttribute::where('name', 'Numeração')->sole();

        foreach (self::SIZES['shoes'] as $number) {
            if (! $shoeSizes->values()->where('value', $number)->exists()) {
                $catalog->addValue($shoeSizes, $number);
            }
        }

        $valueId = fn (VariationAttribute $attribute, string $value) => VariationValue::where('variation_attribute_id', $attribute->id)->where('value', $value)->value('id');

        $categories = [];
        foreach (self::PRODUCTS as [, $parent, $child]) {
            $categories[$parent] ??= $catalog->saveCategory(null, $parent, null, true);
            if ($child) {
                $categories["{$parent}/{$child}"] ??= $catalog->saveCategory(null, $child, $categories[$parent]->id, true);
            }
        }

        $suppliers = [];
        foreach (self::SUPPLIERS as $brand => $name) {
            $suppliers[$brand] = $catalog->saveSupplier(null, [
                'name' => $name, 'contact_name' => $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES),
                'phone' => $this->phone(), 'whatsapp' => $this->phone(), 'email' => 'comercial@'.Str::slug($brand).'.example.com',
                'city' => $this->pick(['Fortaleza', 'São Paulo', 'Goiânia', 'Recife']), 'state' => $this->pick(['CE', 'SP', 'GO', 'PE']),
                'is_active' => true,
            ]);
            Brand::firstOrCreate(['name' => $brand], ['is_active' => true]);
        }

        foreach (self::PRODUCTS as [$name, $parent, $child, $brand, $price, $sizeKind, $productColors, $popularity]) {
            $cost = round($price * mt_rand(40, 50) / 100, 2);
            $sizeIds = array_map(fn ($s) => $valueId($sizeKind === 'shoes' ? $shoeSizes : $sizes, $s), self::SIZES[$sizeKind]);
            $colorIds = array_map(fn ($c) => $valueId($colors, $c), $productColors);

            $combinations = [[]];
            foreach (array_filter([$sizeIds, $colorIds]) as $group) {
                $combinations = array_merge(...array_map(fn ($combo) => array_map(fn ($id) => [...$combo, $id], $group), $combinations));
            }

            $product = app(ProductService::class)->save($admin, null, [
                'name' => $name,
                'description' => "{$name} da marca {$brand}. Produto fictício da loja de demonstração.",
                'category_id' => $categories[$child ? "{$parent}/{$child}" : $parent]->id,
                'brand_id' => Brand::where('name', $brand)->value('id'),
                'supplier_id' => $suppliers[$brand]->id,
                'sale_price' => number_format($price, 2, '.', ''),
                'cost_price' => number_format($cost, 2, '.', ''),
                'is_active' => true,
                'internal_code' => null,
                'sku' => null,
            ], array_map(fn (array $valueIds) => [
                'id' => null, 'value_ids' => $valueIds, 'sku' => null, 'barcode' => $this->ean(++$this->barcodeSeq),
                'sale_price' => number_format($price, 2, '.', ''), 'cost_price' => number_format($cost, 2, '.', ''),
                'min_stock' => 2, 'is_active' => true,
            ], $combinations));

            foreach ($product->variants()->with('values')->get() as $variant) {
                $sizeValue = $variant->values->first()?->value;
                // Tamanhos intermediários vendem mais.
                $sizeBoost = in_array($sizeValue, ['4', '6', '8', 'M', '26', '28'], true) ? 2 : 1;
                $this->variants[$variant->id] = [
                    'weight' => $popularity * $sizeBoost,
                    'price' => Money::toCents($variant->sale_price),
                    'supplier' => $suppliers[$brand]->id,
                    'cost' => (string) $variant->cost_price,
                ];
            }
        }
    }

    private function initialStock(CarbonImmutable $start): void
    {
        $items = [];
        foreach ($this->variants as $id => $info) {
            $items[$info['supplier']][] = ['variant_id' => $id, 'quantity' => mt_rand(6, 10) + $info['weight'] * 2, 'unit_cost' => $info['cost']];
        }

        $this->registerPurchases($items, $start, 'Estoque inicial');
    }

    /** Entradas por fornecedor, cada uma com a conta a pagar parcelada da nota. */
    private function registerPurchases(array $itemsBySupplier, CarbonImmutable $day, ?string $note = null): void
    {
        $merchandise = ExpenseCategory::where('system_key', ExpenseCategory::MERCHANDISE)->value('id');

        foreach ($itemsBySupplier as $supplierId => $items) {
            $document = 'NF '.mt_rand(10000, 99999);
            app(StockEntryService::class)->register($this->users['estoque'], $supplierId, $document, $note, $items);

            $total = array_sum(array_map(fn ($i) => Money::toCents($i['unit_cost']) * $i['quantity'], $items));
            app(PayableService::class)->create([
                'description' => $document.' - '.Supplier::whereKey($supplierId)->value('name'),
                'expense_category_id' => $merchandise,
                'supplier_id' => $supplierId,
                'document' => $document,
                'due_date' => $day->addDays(30)->toDateString(),
                'amount_cents' => $total,
                'notes' => null,
            ], $total > 150000 ? 3 : 2, true, $this->users['gerente']);

            foreach ($items as $item) {
                $this->stock[$item['variant_id']] = ($this->stock[$item['variant_id']] ?? 0) + $item['quantity'];
            }
        }
    }

    private function createCustomers(CarbonImmutable $start, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->at($start->subDays(mt_rand(1, 500))->setTime(mt_rand(9, 18), mt_rand(0, 59)));
            $this->newCustomer();
        }
    }

    private function newCustomer(?string $source = null): Customer
    {
        $this->customerSeq++;
        $first = $this->pick(self::FIRST_NAMES);
        $last = $this->pick(self::LAST_NAMES);
        $middle = $this->pick(array_values(array_diff(self::LAST_NAMES, [$last])));
        $name = "{$first} {$middle} {$last}";
        $hasEmail = $this->chance(60);
        $children = [];

        if (StoreSegment::childrenEnabled() && $this->chance(75)) {
            foreach (range(1, mt_rand(1, 2)) as $c) {
                $birth = CarbonImmutable::today()->subDays(mt_rand(120, 11 * 365));
                $age = $birth->diffInYears(CarbonImmutable::today());
                $children[] = [
                    'id' => null,
                    'name' => $this->pick(self::CHILD_NAMES).' '.$last,
                    'birth_date' => $birth->toDateString(),
                    'clothing_size' => match (true) {
                        $age < 1 => 'M', $age < 2 => 'G', $age < 4 => '2', $age < 6 => '4', $age < 8 => '6', $age < 10 => '8', default => '10',
                    },
                ];
            }
        }

        $customer = app(CustomerService::class)->save(null, [
            'name' => $name,
            'birth_date' => CarbonImmutable::today()->subYears(mt_rand(22, 48))->subDays(mt_rand(0, 364))->toDateString(),
            'gender' => in_array($first, ['Rafael', 'Bruno', 'Carlos', 'Diego', 'Felipe', 'Gustavo', 'Henrique', 'Leonardo', 'Marcelo', 'Thiago'], true) ? 'masculino' : 'feminino',
            'whatsapp' => $this->phone(),
            'email' => $hasEmail ? Str::slug("{$first}.{$last}", '.').$this->customerSeq.'@example.com' : null,
            'neighborhood' => $this->pick(self::NEIGHBORHOODS),
            'city' => $this->chance(85) ? 'Teresina' : $this->pick(['Timon', 'Parnaíba', 'Picos', 'Floriano']),
        ], $children, [
            'whatsapp' => $this->chance(72),
            'email' => $hasEmail && $this->chance(70),
        ], $source ?? 'cadastro');

        // Parte dos clientes compra no crediário, com limite definido pela gerente.
        if ($this->chance(35)) {
            $customer->forceFill(['credit_limit' => $this->pick([300, 500, 800, 1000, 1500])])->save();
        }

        // Clientes fiéis aparecem mais vezes no sorteio das vendas.
        $repeat = $this->chance(25) ? 6 : ($this->chance(40) ? 2 : 1);
        array_push($this->customerPool, ...array_fill(0, $repeat, $customer->id));

        return $customer;
    }

    private function segments(): void
    {
        $segments = [
            'Clientes VIP (compraram R$ 600 ou mais)' => ['min_spent' => '600'],
            'Mães de crianças até 6 anos' => ['has_children' => '1', 'child_age_max' => '6'],
            'Inativos há mais de 90 dias' => ['idle_days' => '90'],
            'Clientes de Teresina' => ['city' => 'Teresina'],
        ];

        foreach ($segments as $name => $filters) {
            CustomerSegment::create(['name' => $name, 'filters' => CustomerFilter::normalize($filters), 'created_by' => $this->users['gerente']->id]);
        }
    }

    private function automations(): void
    {
        MarketingAutomation::query()->update(['enabled' => true]);
    }

    private function fixedBills(CarbonImmutable $start, CarbonImmutable $today): void
    {
        $payables = app(PayableService::class);
        $category = fn (string $name) => ExpenseCategory::where('name', $name)->value('id');
        $months = $this->months + 2;
        $firstMonth = $start->startOfMonth();
        $bill = fn (string $description, string $cat, int $day, int $cents, int $times) => $payables->create([
            'description' => $description, 'expense_category_id' => $category($cat), 'supplier_id' => null, 'document' => null,
            'due_date' => $firstMonth->day($day)->toDateString(), 'amount_cents' => $cents, 'notes' => null,
        ], $times, false, $this->users['gerente']);

        $bill('Aluguel da loja', 'Aluguel', 5, 320000, $months);
        $bill('Folha de pagamento e encargos', 'Salários e encargos', 5, 435000, $months);
        $bill('Honorários do contador', 'Contador', 10, 65000, $months);
        $bill('Internet e telefone', 'Internet e telefone', 20, 14990, $months);
        $bill('Impulsionamento no Instagram', 'Marketing', 1, 30000, $months);
        $bill('Sacolas e papel de seda', 'Embalagens e materiais', 25, 24000, $months);
        $bill('Tarifa da conta e maquininha', 'Taxas bancárias', 8, 8990, $months);

        for ($m = 0; $m < $months; $m++) {
            $month = $firstMonth->addMonthsNoOverflow($m);
            foreach ([['Energia elétrica', 12, mt_rand(38000, 56000)], ['Água', 15, mt_rand(8000, 11000)], ['Simples Nacional (DAS)', 20, mt_rand(150000, 260000)]] as [$name, $day, $cents]) {
                $payables->create([
                    'description' => $name.' - '.$month->locale('pt_BR')->translatedFormat('F/Y'), 'expense_category_id' => $category($name === 'Simples Nacional (DAS)' ? 'Impostos' : $name),
                    'supplier_id' => null, 'document' => null, 'due_date' => $month->day($day)->toDateString(), 'amount_cents' => $cents, 'notes' => null,
                ], 1, false, $this->users['gerente']);
            }
        }

        // O que venceu antes do período simulado já estava pago.
        foreach (Payable::open()->whereDate('due_date', '<', $start->toDateString())->get() as $old) {
            $payables->pay($old, Carbon::parse($old->due_date->toDateString()), $this->users['gerente']);
        }

        // O conserto fica vencido, para o alerta do financeiro aparecer.
        $payables->create(['description' => 'Conserto do ar-condicionado', 'expense_category_id' => $category('Manutenção'), 'supplier_id' => null,
            'document' => 'OS 3381', 'due_date' => $today->subDays(6)->toDateString(), 'amount_cents' => 38000, 'notes' => 'Aguardando nota do técnico.'], 1, false, $this->users['gerente']);
    }

    private function simulateDay(CarbonImmutable $day, CarbonImmutable $start, CarbonImmutable $today): void
    {
        $yesterday = $today->subDay();

        // O expediente grava junto; os agradecimentos das vendas entram na fila ao confirmar.
        DB::transaction(function () use ($day, $start, $today, $yesterday) {
            $this->at($day->setTime(8, 30));
            app(BirthdayAutomation::class)->expireStale();
            app(BirthdayAutomation::class)->run();

            if ($day->isMonday() && $day->gt($start)) {
                $this->restock($day);
                $this->confirmReceivables($day->subDay());
            }

            // No dia 1 o gerente fecha as comissões do mês anterior, a pagar no dia 5.
            if ($day->day === 1 && $day->gt($start)) {
                try {
                    app(CommissionService::class)->close($day->subMonth(), $day->setDay(5), $this->users['gerente']);
                } catch (BusinessRuleException) {
                }
            }

            $this->payBills($day, $today);
            $this->campaigns($day, $start, $yesterday);

            if (! $day->isSunday()) {
                $this->salesDay($day);
            }

            $this->at($day->setTime(19, 30));
        });

        DB::transaction(function () use ($day, $yesterday) {
            $this->at($day->setTime(20, 30));
            $this->workWhatsappQueue($day, $yesterday);
            $this->sendEmails();
        });
    }

    private function salesDay(CarbonImmutable $day): void
    {
        $sellers = [$this->users['vendedora'], $this->users['bruna']];
        $cash = app(CashService::class);

        // Acessos da equipe, para o painel e a auditoria mostrarem o uso diário.
        $team = $day->isSaturday() ? $sellers : [...$sellers, $this->users['gerente']];
        if ($day->isMonday()) {
            $team[] = $this->users['estoque'];
        }
        foreach ($team as $member) {
            $this->at($day->setTime(8, mt_rand(40, 58)));
            app(AuditLogger::class)->record('auth.login', "{$member->name} entrou no sistema.", $member, userId: $member->id);
        }

        foreach ($sellers as $seller) {
            $this->at($day->setTime(9, mt_rand(0, 10)));
            $cash->open($seller, 15000);
        }

        $base = [1 => 6, 2 => 7, 3 => 7, 4 => 8, 5 => 10, 6 => 14][$day->dayOfWeekIso];
        $season = match (true) {
            $day->month === 10 && $day->day <= 11 => 1.9,
            $day->month === 8 && $day->day <= 9 => 1.3,
            $day->month === 12 => 1.6,
            in_array($day->month, [6, 7], true) => 1.15,
            $day->month === 11 => 1.2,
            default => 1.0,
        };
        $count = max(2, (int) round($base * $season * mt_rand(70, 130) / 100));

        $minutes = [];
        for ($i = 0; $i < $count; $i++) {
            $minutes[] = mt_rand(9 * 60 + 15, 18 * 60 + 45);
        }
        sort($minutes);

        $this->at($day->setTime(9, 30));
        $this->receiveInstallments($day, $sellers);
        $this->settleConsignments($day, $sellers);

        foreach ($minutes as $minute) {
            $this->at($day->setTime(intdiv($minute, 60), $minute % 60, mt_rand(0, 59)));
            $this->sell($this->pick($sellers));
        }

        if (in_array($day->dayOfWeekIso, [2, 4, 6], true) && $this->chance(45)) {
            $this->at($day->setTime(mt_rand(10, 17), mt_rand(0, 59)));
            $this->openConsignment($day, $this->pick($sellers));
        }

        foreach ($this->returns[$day->toDateString()] ?? [] as $return) {
            $this->at($day->setTime(16, mt_rand(0, 59)));
            $this->registerReturn($return['sale'], $return['refund'], $this->pick($sellers));
        }

        foreach ($sellers as $seller) {
            $this->closeCash($seller, $day);
        }
    }

    private function sell(User $seller): void
    {
        $lines = [];
        $wanted = $this->chance(55) ? 1 : ($this->chance(65) ? 2 : 3);

        for ($i = 0; $i < $wanted * 3 && count($lines) < $wanted; $i++) {
            $id = $this->weightedVariant();
            $quantity = $this->chance(85) ? 1 : 2;

            if ($id !== null && ! isset($lines[$id]) && ($this->stock[$id] ?? 0) >= $quantity) {
                $lines[$id] = $quantity;
            }
        }

        if ($lines === []) {
            return;
        }

        $customerId = match (true) {
            $this->chance(58) => $this->pick($this->customerPool),
            $this->chance(18) => $this->newCustomer('pdv')->id,
            default => null,
        };

        $cart = [];
        $calcLines = [];
        foreach ($lines as $id => $quantity) {
            $cart[] = ['variant_id' => $id, 'quantity' => $quantity, 'discount' => 0];
            $calcLines[] = ['unit_price' => $this->variants[$id]['price'], 'quantity' => $quantity, 'discount' => 0];
        }

        $gross = SaleCalculator::calculate($calcLines, 0)['total'];
        // Arredonda para baixo: o desconto nunca passa do limite de 10% do vendedor.
        $discount = $this->chance(12) ? intdiv($gross * $this->pick([5, 10]), 100) : 0;
        $total = SaleCalculator::calculate($calcLines, $discount)['total'];

        $payments = $this->payments($total);

        // Cliente com limite de crediário às vezes parcela no carnê.
        if ($customerId && $total >= 8000 && $this->chance(30) && Customer::whereKey($customerId)->whereNotNull('credit_limit')->exists()) {
            $payments = [['method_id' => $this->methods[PaymentMethod::CREDIARIO], 'amount' => $total, 'installments' => $this->pick([2, 3, 3, 4, 5])]];
        }

        try {
            $sale = app(SaleService::class)->finalize($seller, $cart, $payments, $customerId, $discount, null, (string) Str::uuid());
        } catch (BusinessRuleException) {
            return;
        }

        foreach ($lines as $id => $quantity) {
            $this->stock[$id] -= $quantity;
        }

        if ($this->chance(0.7)) {
            app(SaleService::class)->cancel($seller, $sale, $this->pick(['Cliente desistiu da compra', 'Venda lançada em duplicidade', 'Erro no tamanho escolhido']), $this->users['gerente']);
            foreach ($lines as $id => $quantity) {
                $this->stock[$id] += $quantity;
            }

            return;
        }

        if ($this->chance(2.5)) {
            $when = CarbonImmutable::instance(now())->addDays(mt_rand(1, 6))->toDateString();
            $this->returns[$when][] = ['sale' => $sale->id, 'refund' => $this->chance(30) ? $this->methods[PaymentMethod::PIX] : null];
        }
    }

    /**
     * Clientes pagam as parcelas do crediário: a maioria em dia, alguns com
     * poucos dias de atraso, outros bem atrasados e alguns ainda devendo.
     */
    private function receiveInstallments(CarbonImmutable $day, array $sellers): void
    {
        $due = Receivable::crediario()->pending()->whereDate('due_date', '<=', $day->toDateString())->get();

        foreach ($due as $installment) {
            $bucket = ($installment->id * 37) % 100;
            $delay = match (true) {
                $bucket < 68 => 0,
                $bucket < 84 => $installment->id % 5 + 1,
                $bucket < 93 => $installment->id % 16 + 10,
                default => null,
            };

            if ($delay === null || $installment->due_date->copy()->addDays($delay)->gt($day)) {
                continue;
            }

            try {
                app(CrediarioService::class)->receive($installment, $this->methods[$this->chance(60) ? PaymentMethod::PIX : PaymentMethod::CASH], false, $this->pick($sellers));
            } catch (BusinessRuleException) {
            }
        }
    }

    /** Uma cliente leva peças para provar em casa. */
    private function openConsignment(CarbonImmutable $day, User $seller): void
    {
        $customerId = $this->pick($this->customerPool);
        $items = [];

        foreach (range(1, mt_rand(2, 4)) as $i) {
            $id = $this->weightedVariant();
            if ($id !== null && ! isset($items[$id]) && ($this->stock[$id] ?? 0) >= 1) {
                $items[$id] = 1;
            }
        }

        if ($items === []) {
            return;
        }

        try {
            $consignment = app(ConsignmentService::class)->open($seller, $customerId,
                array_map(fn ($id) => ['variant_id' => $id, 'quantity' => 1], array_keys($items)), $day->addDays(3), null);
        } catch (BusinessRuleException) {
            return;
        }

        foreach ($items as $id => $quantity) {
            $this->stock[$id] -= $quantity;
        }

        // A cliente volta no prazo ou um pouco depois; algumas ainda estão com as peças.
        $this->consignmentVisits[$day->addDays(3 + ($consignment->id % 6 === 0 ? 2 : 0))->toDateString()][] = $consignment->id;
    }

    private function settleConsignments(CarbonImmutable $day, array $sellers): void
    {
        foreach ($this->consignmentVisits[$day->toDateString()] ?? [] as $id) {
            $consignment = Consignment::with('items')->find($id);
            $seller = $this->pick($sellers);
            $returned = [];

            foreach ($consignment->items as $item) {
                $returned[$item->id] = $this->chance(45) ? 0 : $item->quantity;
            }

            $this->at($day->setTime(mt_rand(10, 17), mt_rand(0, 59)));
            $kept = app(ConsignmentService::class)->settle($consignment, $returned, $seller);

            foreach ($consignment->items as $item) {
                $this->stock[$item->product_variant_id] += $returned[$item->id];
            }

            if ($kept === 0) {
                continue;
            }

            $consignment->refresh();
            $cart = app(ConsignmentService::class)->cartFor($consignment);
            $total = $consignment->items->sum(fn ($i) => Money::toCents($i->unit_price) * $i->kept_quantity);

            try {
                app(SaleService::class)->finalize($seller, array_map(fn ($line) => ['variant_id' => $line['variant_id'], 'quantity' => (int) $line['quantity'], 'discount' => 0], $cart),
                    [['method_id' => $this->methods[$this->chance(50) ? PaymentMethod::PIX : PaymentMethod::CREDIT], 'amount' => $total]],
                    $consignment->customer_id, 0, null, (string) Str::uuid(), $consignment->id);
            } catch (BusinessRuleException) {
                app(ConsignmentService::class)->closeWithoutSale($consignment, $seller);
                foreach ($consignment->items as $item) {
                    $this->stock[$item->product_variant_id] += $item->kept_quantity;
                }
            }
        }
    }

    private function weightedVariant(): ?int
    {
        $available = array_filter($this->variants, fn ($info, $id) => ($this->stock[$id] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);

        if ($available === []) {
            return null;
        }

        $total = array_sum(array_column($available, 'weight'));
        $roll = mt_rand(1, $total);

        foreach ($available as $id => $info) {
            $roll -= $info['weight'];
            if ($roll <= 0) {
                return $id;
            }
        }

        return array_key_first($available);
    }

    /** @return list<array<string, int>> */
    private function payments(int $total): array
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 6 && $total > 4000) {
            $pix = intdiv($total, 2);

            return [
                ['method_id' => $this->methods[PaymentMethod::PIX], 'amount' => $pix],
                ['method_id' => $this->methods[PaymentMethod::CASH], 'amount' => (int) (ceil(($total - $pix) / 1000) * 1000)],
            ];
        }

        return match (true) {
            $roll <= 40 => [['method_id' => $this->methods[PaymentMethod::PIX], 'amount' => $total]],
            $roll <= 70 => [['method_id' => $this->methods[PaymentMethod::CREDIT], 'amount' => $total,
                'installments' => $total >= 15000 ? $this->pick([1, 2, 2, 3, 3, 4]) : 1]],
            $roll <= 88 => [['method_id' => $this->methods[PaymentMethod::DEBIT], 'amount' => $total]],
            default => [['method_id' => $this->methods[PaymentMethod::CASH], 'amount' => $this->chance(50) ? $total : (int) (ceil($total / 1000) * 1000)]],
        };
    }

    private function registerReturn(int $saleId, ?int $refundMethodId, User $seller): void
    {
        $sale = Sale::with('items')->find($saleId);
        $item = $sale?->items->first(fn ($i) => $i->returnableQuantity() > 0);

        if (! $sale?->isCompleted() || ! $item) {
            return;
        }

        try {
            app(SaleReturnService::class)->register($seller, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]],
                $refundMethodId ? 'Produto com defeito' : 'Troca de tamanho', $refundMethodId, $this->users['gerente']);
            $this->stock[$item->product_variant_id] = ($this->stock[$item->product_variant_id] ?? 0) + 1;
        } catch (BusinessRuleException) {
        }
    }

    private function closeCash(User $seller, CarbonImmutable $day): void
    {
        $cash = app(CashService::class);
        $session = $cash->currentFor($seller);

        if (! $session) {
            return;
        }

        $this->at($day->setTime(18, 55));
        $expected = $cash->expectedCents($session);

        if ($expected > 60000) {
            $cash->registerManual($seller, CashMovementType::Bleed, $expected - 15000, 'Depósito no banco');
            $expected = 15000;
        }

        $this->at($day->setTime(19, mt_rand(0, 15)));
        $short = $this->chance(6) ? 200 : 0;
        $cash->close($seller, CashSession::find($session->id), $expected - $short, $short ? 'Diferença de troco' : null);
    }

    private function restock(CarbonImmutable $day): void
    {
        $this->at($day->setTime(10, 0));
        $items = [];

        foreach ($this->variants as $id => $info) {
            if (($this->stock[$id] ?? 0) <= 3 && $this->chance(85)) {
                $items[$info['supplier']][] = ['variant_id' => $id, 'quantity' => mt_rand(5, 9) + $info['weight'], 'unit_cost' => $info['cost']];
            }
        }

        $this->registerPurchases($items, $day);

        // De vez em quando uma peça com defeito sai do estoque.
        if ($this->chance(20)) {
            $id = $this->weightedVariant();
            if ($id !== null) {
                app(StockService::class)->register(ProductVariant::find($id), MovementType::Damage, 1, 'Peça com defeito de costura', $this->users['estoque']);
                $this->stock[$id]--;
            }
        }
    }

    /** O gerente confere o extrato: cartões que já caíram entram como recebidos. */
    private function confirmReceivables(CarbonImmutable $until): void
    {
        $pending = Receivable::pending()->whereDate('due_date', '<=', $until->toDateString())->get(['id', 'due_date'])->groupBy(fn ($r) => $r->due_date->toDateString());

        foreach ($pending as $date => $rows) {
            app(ReceivableService::class)->confirm($rows->pluck('id')->all(), Carbon::parse($date), $this->users['gerente']);
        }
    }

    private function payBills(CarbonImmutable $day, CarbonImmutable $today): void
    {
        $payables = app(PayableService::class);
        $due = Payable::open()->whereDate('due_date', $day->toDateString())->get();

        foreach ($due as $payable) {
            // A conta de água do mês passado e o conserto ficam em aberto, como pendências reais.
            $lastMonthWater = str_starts_with($payable->description, 'Água') && $payable->due_date->isSameMonth($today->subMonthNoOverflow());
            if ($lastMonthWater || $payable->description === 'Conserto do ar-condicionado') {
                continue;
            }

            $payables->pay($payable, Carbon::parse($day->toDateString()), $this->users['gerente']);
        }
    }

    private function campaigns(CarbonImmutable $day, CarbonImmutable $start, CarbonImmutable $yesterday): void
    {
        $service = app(CampaignService::class);
        $manager = $this->users['gerente'];
        $winter = $start->addDays(40);
        $kids = $day->setDate($day->year, 10, 1);
        $childrenDay = $kids->between($start->addDays(7), $yesterday->subDays(3)) ? $kids : $start->addDays(100);

        $plans = [
            $winter->toDateString() => ['Liquida de Inverno', 'whatsapp', null, null,
                "Olá, {primeiro_nome}! ❄️\nA Liquida de Inverno da {loja} começou: peças com até 40% de desconto.\nCorre que os tamanhos estão acabando!"],
            $childrenDay->toDateString() => ['Semana das Crianças', 'email', 'Mães de crianças até 6 anos', 'Presentes para a Semana das Crianças, {primeiro_nome}!',
                "Olá, {primeiro_nome}!\n\nA Semana das Crianças chegou à {loja} com novidades de vestidos, conjuntos e calçados.\n\nVenha escolher o presente perfeito. Embrulhamos para presente sem custo!"],
            $yesterday->toDateString() => ['Novidades de Verão', 'whatsapp', 'Clientes VIP (compraram R$ 600 ou mais)', null,
                "Oi, {primeiro_nome}! ☀️\nChegou a coleção de Verão na {loja}: vestidos leves, bermudas e muito colorido.\nQuer que eu separe alguma peça para você?"],
        ];

        if (! isset($plans[$day->toDateString()])) {
            return;
        }

        [$name, $channel, $segment, $subject, $body] = $plans[$day->toDateString()];
        $this->at($day->setTime(10, 15));
        $segmentModel = $segment ? CustomerSegment::where('name', $segment)->first() : null;

        $campaign = $service->save(null, [
            'name' => $name, 'channel' => $channel, 'segment_name' => $segmentModel?->name,
            'filters' => $segmentModel?->filters ?? [], 'subject' => $subject, 'body' => $body,
        ], $manager);

        try {
            $service->launch($campaign, $manager);
        } catch (BusinessRuleException) {
        }

        if ($day->equalTo($yesterday)) {
            $service->save(null, [
                'name' => 'Black Friday', 'channel' => 'email', 'segment_name' => null, 'filters' => [],
                'subject' => 'Black Friday {loja}: até 50% em peças selecionadas',
                'body' => "Olá, {primeiro_nome}!\n\nNa sexta-feira a {loja} terá descontos de até 50% em peças selecionadas.\n\nAnote na agenda!",
            ], $manager);
        }
    }

    /** As vendedoras tratam a fila do WhatsApp no fim do dia. A campanha de ontem fica pendente para a demonstração. */
    private function workWhatsappQueue(CarbonImmutable $day, CarbonImmutable $yesterday): void
    {
        $queue = app(WhatsAppQueueService::class);
        $messages = MarketingMessage::whatsappQueue()->with('customer', 'campaign')->get();

        foreach ($messages as $message) {
            if ($message->campaign && $day->equalTo($yesterday)) {
                continue;
            }

            // Os agradecimentos do último dia ficam na fila para quem testar a demonstração.
            if ($day->equalTo($yesterday) && $message->automation === MarketingAutomation::PURCHASE_THANKS && $this->chance(60)) {
                continue;
            }

            $seller = $this->pick([$this->users['vendedora'], $this->users['bruna']]);
            $roll = mt_rand(1, 100);

            // Quem pede para sair tem as outras mensagens pendentes descartadas na hora:
            // essas já não estão mais na fila quando chega a vez delas.
            try {
                match (true) {
                    $roll <= 86 => $queue->markSent($message, $seller),
                    $roll <= 88 => $queue->optOut($message, $seller),
                    $roll <= 95 => $queue->skip($message, $seller),
                    default => null,
                };
            } catch (BusinessRuleException) {
            }
        }
    }

    private function sendEmails(): void
    {
        $sender = app(MarketingEmailSender::class);

        while (MarketingMessage::where('channel', 'email')->where('status', MarketingMessage::PENDING)
            ->where(fn ($q) => $q->whereNull('campaign_id')->orWhereHas('campaign', fn ($c) => $c->where('status', MarketingCampaign::SENDING)))
            ->exists()) {
            if (array_sum($sender->sendBatch()) === 0) {
                break;
            }
        }
    }

    private function finalTouches(CarbonImmutable $today): void
    {
        // Um consentimento revogado pelo link do e-mail, para o histórico LGPD ter exemplo.
        $customer = Customer::where('email_opt_in', true)->inRandomOrder()->first();
        if ($customer) {
            app(ConsentService::class)->set($customer, 'email', false, 'descadastro');
        }
    }
}
