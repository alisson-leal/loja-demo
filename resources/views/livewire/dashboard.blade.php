@php use App\Support\Money; @endphp
<div>
    <x-flash />

    @if (! $allowed)
        <div class="card"><div class="card-body">
            <h2 class="h5">Olá, {{ auth()->user()->name }}!</h2>
            <p class="mb-0 text-body-secondary">Seu perfil não inclui acesso aos indicadores do painel. Use o menu para acessar suas áreas.</p>
        </div></div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3"><x-stat-card label="Usuários ativos" :value="$summary['active_users']" icon="person-check" /></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Usuários bloqueados" :value="$summary['blocked_users']" icon="person-slash" tone="blue" /></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Perfis de acesso" :value="$summary['roles']" icon="shield-lock" /></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Sessões ativas" :value="$summary['active_sessions']" icon="laptop" tone="blue" /></div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label for="from" class="form-label">De</label>
                        <input id="from" type="date" class="form-control" wire:model.live="from">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="to" class="form-label">Até</label>
                        <input id="to" type="date" class="form-control" wire:model.live="to">
                    </div>
                    @if ($categories)
                        <div class="col-12 col-md-3">
                            <label for="filter-category" class="form-label">Categoria</label>
                            <select id="filter-category" class="form-select" wire:model.live="categoryId">
                                <option value="">Todas</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-3">
                        <label for="filter-user" class="form-label">Usuário</label>
                        <select id="filter-user" class="form-select" wire:model.live="userId">
                            <option value="">Todos</option>
                            @foreach ($users as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-store-filter :options="$storeOptions" />
                    <div class="col-12 col-md-3">
                        <div class="btn-group w-100" role="group" aria-label="Períodos rápidos">
                            <button type="button" class="btn btn-outline-primary" wire:click="setPeriod(1)">Hoje</button>
                            <button type="button" class="btn btn-outline-primary" wire:click="setPeriod(7)">7 dias</button>
                            <button type="button" class="btn btn-outline-primary" wire:click="setPeriod(30)">30 dias</button>
                        </div>
                    </div>
                </div>
                @if ($rangeError)
                    <div class="alert alert-warning mt-3 mb-0" role="alert">{{ $rangeError }}</div>
                @endif
            </div>
        </div>

        @if ($canSeeSales)
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vendas hoje" :cents="$sales['today']" icon="bag-check" /></div>
                <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Faturamento do mês" :cents="$sales['month']" icon="calendar3" tone="blue" /></div>
                <div class="col-12 col-sm-6 col-xl-3"><x-stat-card label="Vendas no período" :value="$sales['count']" icon="receipt" /></div>
                <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Ticket médio" :cents="$sales['ticket']" icon="graph-up-arrow" tone="blue" /></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="card-title h6">Evolução das vendas</h2>
                            <p class="small text-body-secondary">
                                {{ Money::format($sales['period'] / 100) }} no período, contra {{ Money::format($sales['previous'] / 100) }} no período anterior
                                @if ($sales['variation'] !== null)
                                    <span @class(['fw-bold', 'text-success' => $sales['variation'] >= 0, 'text-danger' => $sales['variation'] < 0])>({{ $sales['variation'] >= 0 ? '+' : '' }}{{ number_format($sales['variation'], 1, ',', '.') }}%)</span>
                                @endif
                            </p>
                            <div class="chart-box" data-chart='@json($salesChart)' data-chart-label="Vendas (R$)">
                                <canvas wire:ignore role="img" aria-label="Gráfico de vendas por dia"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="card-title h6">Por forma de pagamento</h2>
                            @forelse ($byMethod as $row)
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span>{{ $row->name }}</span><strong>{{ Money::format($row->total) }}</strong>
                                </div>
                            @empty
                                <p class="small text-body-secondary mb-0">Nenhuma venda no período.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="card-title h6">Produtos mais vendidos</h2>
                            @if ($topProducts->isEmpty())
                                <p class="small text-body-secondary mb-0">Nenhuma venda no período.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead><tr><th scope="col">Item</th><th scope="col">Qtde.</th><th scope="col" class="text-end">Total</th></tr></thead>
                                        <tbody>
                                            @foreach ($topProducts as $row)
                                                <tr wire:key="top-{{ $loop->index }}"><td>{{ $row->description }}</td><td class="text-nowrap">{{ \App\Support\Quantity::format($row->quantity, $row->unit) }}</td><td class="text-end text-nowrap">{{ Money::format($row->total) }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="card-title h6">Últimas vendas</h2>
                            @if ($recentSales->isEmpty())
                                <p class="small text-body-secondary mb-0">Nenhuma venda registrada.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead><tr><th scope="col">Venda</th><th scope="col">Data</th><th scope="col">Operador</th><th scope="col" class="text-end">Total</th></tr></thead>
                                        <tbody>
                                            @foreach ($recentSales as $sale)
                                                <tr wire:key="recent-sale-{{ $sale->id }}">
                                                    <td><a href="{{ route('sales.show', $sale->id) }}">#{{ $sale->number() }}</a>@unless ($sale->isCompleted()) <span class="badge text-bg-danger">Cancelada</span>@endunless</td>
                                                    <td class="text-nowrap">{{ $sale->created_at->format('d/m H:i') }}</td>
                                                    <td>{{ $sale->user->name }}</td>
                                                    <td class="text-end text-nowrap">{{ Money::format($sale->total) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title h6">Acessos por dia</h2>
                        <p class="small text-body-secondary">
                            {{ number_format($totals['logins'], 0, ',', '.') }} {{ $totals['logins'] === 1 ? 'acesso' : 'acessos' }} e
                            {{ number_format($totals['failures'], 0, ',', '.') }} {{ $totals['failures'] === 1 ? 'tentativa recusada' : 'tentativas recusadas' }} no período.
                        </p>
                        <div class="chart-box" data-chart='@json($chart)' data-chart-label="Acessos">
                            <canvas wire:ignore role="img" aria-label="Gráfico de acessos ao sistema por dia"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title h6">Atividades recentes</h2>
                        @if ($recent === null)
                            <p class="small text-body-secondary mb-0">Seu perfil não inclui acesso à auditoria.</p>
                        @elseif ($recent->isEmpty())
                            <p class="small text-body-secondary mb-0">Nenhuma atividade registrada.</p>
                        @else
                            <ul class="list-unstyled activity-list mb-0">
                                @foreach ($recent as $log)
                                    <li wire:key="log-{{ $log->id }}">
                                        <span>{{ $log->description }}</span>
                                        <time class="small text-body-secondary">{{ $log->created_at->format('d/m H:i') }}</time>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($customers)
            <div class="row g-3 mb-3">
                <div class="col-6"><x-stat-card label="Clientes cadastrados" :value="$customers['total']" icon="person-hearts" /></div>
                <div class="col-6"><x-stat-card label="Clientes novos no período" :value="$customers['new']" icon="person-plus" tone="blue" /></div>
            </div>
        @endif

        @if ($canSeeStock)
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title h6 mb-3">Estoque</h2>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-stat-card label="Produtos ativos" :value="$stock['products']" icon="tags" />
                        </div>
                        <div class="col-6 col-md-4">
                            <a href="{{ route('stock.index', ['situacao' => 'baixo']) }}" class="text-decoration-none text-reset">
                                <x-stat-card label="Com estoque baixo" :value="$stock['low']" icon="exclamation-triangle" tone="blue" />
                            </a>
                        </div>
                        <div class="col-6 col-md-4">
                            <a href="{{ route('stock.index', ['situacao' => 'sem']) }}" class="text-decoration-none text-reset">
                                <x-stat-card label="Sem estoque" :value="$stock['out']" icon="x-octagon" />
                            </a>
                        </div>
                    </div>

                    @if ($lowStock->isNotEmpty())
                        <h3 class="h6 mt-4">Reposição prioritária</h3>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th scope="col">Item</th><th scope="col">SKU</th><th scope="col">Disponível</th><th scope="col">Mínimo</th></tr></thead>
                                <tbody>
                                    @foreach ($lowStock as $item)
                                        <tr wire:key="low-{{ $item->id }}">
                                            <td>{{ $item->fullName() }}</td>
                                            <td>{{ $item->sku }}</td>
                                            <td class="text-nowrap">{{ $item->formatQuantity($item->available()) }}</td>
                                            <td class="text-nowrap">{{ $item->formatQuantity($item->min_stock) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
