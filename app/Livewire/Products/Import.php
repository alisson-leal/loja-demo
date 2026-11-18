<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Importar produtos')]
class Import extends Component
{
    use HandlesBusinessRules;
    use WithFileUploads;

    public $file;

    /** Chave da análise guardada em cache, para não trafegar a planilha a cada clique. */
    #[Locked]
    public ?string $token = null;

    public array $errorsFound = [];

    public array $summary = [];

    public bool $withStock = true;

    public function mount(): void
    {
        Gate::authorize('produtos.criar');
    }

    public function analyze(ProductImportService $import): void
    {
        Gate::authorize('produtos.criar');
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']], [
            'file.required' => 'Escolha o arquivo da planilha.',
            'file.mimes' => 'Salve a planilha como CSV (no Excel: Arquivo > Salvar como > CSV).',
            'file.max' => 'O arquivo pode ter no máximo 4 MB.',
        ]);

        $result = $import->analyze($this->file->getRealPath());
        $this->errorsFound = $result['errors'];
        $this->summary = $result['summary'];
        $this->token = (string) Str::uuid();
        Cache::put("product-import:{$this->token}", $result['products'], now()->addMinutes(30));
        $this->reset('file');
    }

    public function import(ProductImportService $import): void
    {
        Gate::authorize('produtos.criar');
        $products = $this->token ? Cache::get("product-import:{$this->token}") : null;

        if ($products === null || $this->errorsFound !== []) {
            session()->now('error', 'Envie a planilha de novo e corrija os erros antes de importar.');

            return;
        }

        $withStock = $this->withStock && Gate::allows('estoque.entrada');
        $count = 0;

        if ($this->attempt(function () use ($import, $products, $withStock, &$count) {
            $count = $import->import($products, Auth::user(), $withStock);
        }, 'Importado.')) {
            Cache::forget("product-import:{$this->token}");
            session()->flash('success', "{$count} produto(s) importado(s).".($withStock ? ' O estoque inicial entrou como entrada de mercadorias.' : ''));
            $this->redirectRoute('products.index');
        }
    }

    public function render(): View
    {
        return view('livewire.products.import', [
            'columns' => ProductImportService::COLUMNS,
            'canEnterStock' => Gate::allows('estoque.entrada'),
        ]);
    }
}
