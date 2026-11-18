<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CustomerExportController;
use App\Http\Controllers\LabelPrintController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\SalesPrintController;
use App\Http\Controllers\UnsubscribeController;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Cash\Current as CashCurrent;
use App\Livewire\Cash\History as CashHistory;
use App\Livewire\Cash\Report as CashReport;
use App\Livewire\Catalog\Attributes as AttributeIndex;
use App\Livewire\Catalog\Brands as BrandIndex;
use App\Livewire\Catalog\Categories as CategoryIndex;
use App\Livewire\Catalog\PaymentMethods as PaymentMethodIndex;
use App\Livewire\Customers\Birthdays as CustomerBirthdays;
use App\Livewire\Customers\Form as CustomerForm;
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Dashboard;
use App\Livewire\Finance\CashFlow;
use App\Livewire\Finance\Crediario;
use App\Livewire\Finance\ExpenseCategories;
use App\Livewire\Finance\PayableForm;
use App\Livewire\Finance\Payables;
use App\Livewire\Finance\Receivables;
use App\Livewire\Marketing\Automations;
use App\Livewire\Marketing\CampaignForm;
use App\Livewire\Marketing\CampaignIndex;
use App\Livewire\Marketing\CampaignShow;
use App\Livewire\Marketing\WhatsappQueue;
use App\Livewire\Products\Form as ProductForm;
use App\Livewire\Products\Import as ProductImport;
use App\Livewire\Products\Index as ProductIndex;
use App\Livewire\Reports\Commissions;
use App\Livewire\Reports\ProductsReport;
use App\Livewire\Reports\ResultReport;
use App\Livewire\Reports\SalesReport;
use App\Livewire\Roles\Form as RoleForm;
use App\Livewire\Roles\Index as RoleIndex;
use App\Livewire\Sales\ConsignmentForm;
use App\Livewire\Sales\ConsignmentIndex;
use App\Livewire\Sales\ConsignmentShow;
use App\Livewire\Sales\Index as SaleIndex;
use App\Livewire\Sales\Pos;
use App\Livewire\Sales\ReturnForm as SaleReturnForm;
use App\Livewire\Sales\Show as SaleShow;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Livewire\Stock\Balance as StockBalance;
use App\Livewire\Stock\Entry as StockEntryForm;
use App\Livewire\Stock\History as StockHistory;
use App\Livewire\Stock\InventoryCount;
use App\Livewire\Stock\InventoryIndex;
use App\Livewire\Stock\Labels;
use App\Livewire\Stock\Movement as StockMovementForm;
use App\Livewire\Stock\VariantDetail;
use App\Livewire\Suppliers\Form as SupplierForm;
use App\Livewire\Suppliers\Index as SupplierIndex;
use App\Livewire\Users\Form as UserForm;
use App\Livewire\Users\Index as UserIndex;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media');

// Link assinado enviado nos e-mails de marketing; o POST fica fora do CSRF (descadastro com um clique).
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('/descadastrar/{customer}', [UnsubscribeController::class, 'show'])->whereNumber('customer')->name('marketing.unsubscribe');
    Route::post('/descadastrar/{customer}', [UnsubscribeController::class, 'store'])->whereNumber('customer');
});

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/esqueci-a-senha', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/esqueci-a-senha', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');

    Route::livewire('/', Dashboard::class)->name('dashboard');
    Route::livewire('/minha-senha', ChangePassword::class)->name('account.password');
    Route::livewire('/sessoes', SessionIndex::class)->name('sessions.index');

    Route::livewire('/usuarios', UserIndex::class)->middleware('can:usuarios.ver')->name('users.index');
    Route::livewire('/usuarios/novo', UserForm::class)->middleware('can:usuarios.criar')->name('users.create');
    Route::livewire('/usuarios/{user}/editar', UserForm::class)->middleware('can:usuarios.editar')
        ->whereNumber('user')->name('users.edit');

    Route::livewire('/perfis', RoleIndex::class)->middleware('can:perfis.ver')->name('roles.index');
    Route::livewire('/perfis/novo', RoleForm::class)->middleware('can:perfis.criar')->name('roles.create');
    Route::livewire('/perfis/{role}/editar', RoleForm::class)->middleware('can:perfis.editar')
        ->whereNumber('role')->name('roles.edit');

    Route::livewire('/auditoria', AuditIndex::class)->middleware('can:auditoria.ver')->name('audit.index');

    Route::livewire('/pdv', Pos::class)->middleware('can:pdv.vender')->name('pos');

    Route::livewire('/caixa', CashCurrent::class)->middleware('can:caixa.operar')->name('cash.current');
    Route::livewire('/caixa/historico', CashHistory::class)->name('cash.history');
    Route::livewire('/caixa/{session}', CashReport::class)->whereNumber('session')->name('cash.report');

    Route::livewire('/vendas', SaleIndex::class)->name('sales.index');
    Route::livewire('/vendas/{sale}', SaleShow::class)->whereNumber('sale')->name('sales.show');
    Route::get('/vendas/{sale}/comprovante', [SaleReceiptController::class, 'show'])->whereNumber('sale')->name('sales.receipt');
    Route::livewire('/vendas/{sale}/devolucao', SaleReturnForm::class)->whereNumber('sale')->name('sales.return');

    Route::livewire('/condicionais', ConsignmentIndex::class)->name('consignments.index');
    Route::livewire('/condicionais/nova', ConsignmentForm::class)->middleware('can:condicional.criar')->name('consignments.create');
    Route::livewire('/condicionais/{consignment}', ConsignmentShow::class)->whereNumber('consignment')->name('consignments.show');
    Route::get('/condicionais/{consignment}/imprimir', [SalesPrintController::class, 'consignment'])->whereNumber('consignment')->name('consignments.print');

    Route::livewire('/crediario', Crediario::class)->name('crediario.index');
    Route::get('/crediario/carne/{sale}', [SalesPrintController::class, 'carne'])->whereNumber('sale')->name('crediario.carne');

    Route::livewire('/clientes', CustomerIndex::class)->middleware('can:clientes.ver')->name('customers.index');
    Route::livewire('/clientes/novo', CustomerForm::class)->middleware('can:clientes.criar')->name('customers.create');
    Route::get('/clientes/exportar', [CustomerExportController::class, 'download'])->middleware('can:clientes.exportar')->name('customers.export');
    Route::livewire('/clientes/aniversariantes', CustomerBirthdays::class)->middleware('can:clientes.ver')->name('customers.birthdays');
    Route::livewire('/clientes/{customer}', CustomerShow::class)->middleware('can:clientes.ver')->whereNumber('customer')->name('customers.show');
    Route::livewire('/clientes/{customer}/editar', CustomerForm::class)->middleware('can:clientes.editar')->whereNumber('customer')->name('customers.edit');

    Route::livewire('/marketing/campanhas', CampaignIndex::class)->middleware('can:marketing.ver')->name('marketing.campaigns.index');
    Route::livewire('/marketing/campanhas/nova', CampaignForm::class)->middleware('can:marketing.campanhas')->name('marketing.campaigns.create');
    Route::livewire('/marketing/campanhas/{campaign}', CampaignShow::class)->middleware('can:marketing.ver')->whereNumber('campaign')->name('marketing.campaigns.show');
    Route::livewire('/marketing/campanhas/{campaign}/editar', CampaignForm::class)->middleware('can:marketing.campanhas')->whereNumber('campaign')->name('marketing.campaigns.edit');
    Route::livewire('/marketing/whatsapp', WhatsappQueue::class)->middleware('can:marketing.whatsapp')->name('marketing.whatsapp');
    Route::livewire('/marketing/automacoes', Automations::class)->middleware('can:marketing.automacoes')->name('marketing.automations');

    Route::livewire('/financeiro/contas-a-pagar', Payables::class)->middleware('can:financeiro.ver')->name('finance.payables');
    Route::livewire('/financeiro/contas-a-pagar/nova', PayableForm::class)->middleware('can:financeiro.pagar')->name('finance.payables.create');
    Route::livewire('/financeiro/contas-a-pagar/{payable}/editar', PayableForm::class)->middleware('can:financeiro.pagar')->whereNumber('payable')->name('finance.payables.edit');
    Route::livewire('/financeiro/categorias', ExpenseCategories::class)->middleware('can:financeiro.pagar')->name('finance.categories');
    Route::livewire('/financeiro/recebimentos', Receivables::class)->middleware('can:financeiro.ver')->name('finance.receivables');
    Route::livewire('/financeiro/fluxo-de-caixa', CashFlow::class)->middleware('can:financeiro.ver')->name('finance.cash-flow');

    Route::livewire('/relatorios/vendas', SalesReport::class)->middleware('can:relatorios.vendas')->name('reports.sales');
    Route::livewire('/relatorios/resultado', ResultReport::class)->middleware('can:relatorios.resultado')->name('reports.result');
    Route::livewire('/relatorios/produtos', ProductsReport::class)->middleware('can:relatorios.produtos')->name('reports.products');
    Route::livewire('/relatorios/comissoes', Commissions::class)->name('reports.commissions');

    Route::livewire('/estoque/etiquetas', Labels::class)->middleware('can:produtos.ver')->name('stock.labels');
    Route::get('/estoque/etiquetas/imprimir', [LabelPrintController::class, 'show'])->middleware('can:produtos.ver')->name('stock.labels.print');

    Route::livewire('/catalogo/formas-pagamento', PaymentMethodIndex::class)->middleware('can:cadastros.ver')->name('payment-methods.index');

    Route::livewire('/catalogo/categorias', CategoryIndex::class)->middleware('can:cadastros.ver')->name('categories.index');
    Route::livewire('/catalogo/marcas', BrandIndex::class)->middleware('can:cadastros.ver')->name('brands.index');
    Route::livewire('/catalogo/variacoes', AttributeIndex::class)->middleware('can:cadastros.ver')->name('attributes.index');

    Route::livewire('/fornecedores', SupplierIndex::class)->middleware('can:fornecedores.ver')->name('suppliers.index');
    Route::livewire('/fornecedores/novo', SupplierForm::class)->middleware('can:fornecedores.criar')->name('suppliers.create');
    Route::livewire('/fornecedores/{supplier}/editar', SupplierForm::class)->middleware('can:fornecedores.editar')
        ->whereNumber('supplier')->name('suppliers.edit');

    Route::livewire('/produtos', ProductIndex::class)->middleware('can:produtos.ver')->name('products.index');
    Route::livewire('/produtos/novo', ProductForm::class)->middleware('can:produtos.criar')->name('products.create');
    Route::livewire('/produtos/importar', ProductImport::class)->middleware('can:produtos.criar')->name('products.import');
    Route::get('/produtos/importar/modelo', fn () => response(ProductImportService::template(), 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="modelo-importacao-produtos.csv"',
    ]))->middleware('can:produtos.criar')->name('products.import.template');
    Route::livewire('/produtos/{product}/editar', ProductForm::class)->middleware('can:produtos.editar')
        ->whereNumber('product')->name('products.edit');

    Route::livewire('/estoque', StockBalance::class)->middleware('can:estoque.ver')->name('stock.index');
    Route::livewire('/estoque/sku/{variant}', VariantDetail::class)->middleware('can:estoque.ver')
        ->whereNumber('variant')->name('stock.variant');
    Route::livewire('/estoque/entrada', StockEntryForm::class)->middleware('can:estoque.entrada')->name('stock.entry');
    Route::livewire('/estoque/movimentar', StockMovementForm::class)->name('stock.movement');
    Route::livewire('/estoque/movimentacoes', StockHistory::class)->middleware('can:estoque.ver')->name('stock.history');
    Route::livewire('/estoque/inventarios', InventoryIndex::class)->middleware('can:estoque.inventario')->name('stock.inventories');
    Route::livewire('/estoque/inventarios/{inventory}', InventoryCount::class)->middleware('can:estoque.inventario')
        ->whereNumber('inventory')->name('stock.inventory');
});
