<div>
    <x-flash />

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a href="{{ route('marketing.campaigns.index') }}" class="align-self-center"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar às campanhas</a>
        <div class="d-flex flex-wrap gap-2">
            @if ($campaign->isDraft())
                @can('marketing.campanhas')
                    <a href="{{ route('marketing.campaigns.edit', $campaign->id) }}" class="btn btn-outline-primary">Editar</a>
                    @if ($campaign->isEmail())
                        <button type="button" class="btn btn-light" wire:click="sendTest" wire:loading.attr="disabled">Enviar teste para mim</button>
                    @endif
                    <button type="button" class="btn btn-light text-danger" wire:click="delete" wire:confirm="Excluir este rascunho?">Excluir</button>
                @endcan
                @can('marketing.disparar')
                    <button type="button" class="btn btn-primary" wire:click="launch" wire:loading.attr="disabled"
                            wire:confirm="Iniciar o envio para {{ $audienceCount }} cliente(s)? Depois de iniciada, a campanha não pode ser editada.">Iniciar envio</button>
                @endcan
            @endif
            @can('marketing.disparar')
                @if ($campaign->status === \App\Models\MarketingCampaign::SENDING && $campaign->isEmail())
                    <button type="button" class="btn btn-light" wire:click="pause">Pausar</button>
                @endif
                @if ($campaign->status === \App\Models\MarketingCampaign::PAUSED)
                    <button type="button" class="btn btn-primary" wire:click="resume">Retomar</button>
                @endif
                @if (in_array($campaign->status, [\App\Models\MarketingCampaign::SENDING, \App\Models\MarketingCampaign::PAUSED], true))
                    <button type="button" class="btn btn-light text-danger" wire:click="cancel" wire:confirm="Cancelar a campanha? As mensagens ainda não enviadas serão descartadas.">Cancelar campanha</button>
                @endif
            @endcan
            @if ($campaign->status === \App\Models\MarketingCampaign::SENDING && ! $campaign->isEmail())
                @can('marketing.whatsapp')
                    <a href="{{ route('marketing.whatsapp', ['campanha' => $campaign->id]) }}" class="btn btn-primary"><i class="bi bi-whatsapp" aria-hidden="true"></i> Abrir fila do WhatsApp</a>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                        <h2 class="h6 mb-0">{{ $campaign->name }}</h2>
                        <span class="badge text-bg-{{ $campaign->statusTone() }}">{{ $campaign->statusLabel() }}</span>
                    </div>
                    <dl class="row small mb-3">
                        <dt class="col-4">Canal</dt><dd class="col-8">{{ $campaign->channelLabel() }}</dd>
                        <dt class="col-4">Público</dt><dd class="col-8">{{ $campaign->segment_name ?? 'Todos os clientes' }}</dd>
                        <dt class="col-4">Criada por</dt><dd class="col-8">{{ $campaign->creator->name }} em {{ $campaign->created_at->format('d/m/Y H:i') }}</dd>
                        @if ($campaign->started_at)
                            <dt class="col-4">Início</dt><dd class="col-8">{{ $campaign->started_at->format('d/m/Y H:i') }}</dd>
                        @endif
                        @if ($campaign->finished_at)
                            <dt class="col-4">Término</dt><dd class="col-8">{{ $campaign->finished_at->format('d/m/Y H:i') }}</dd>
                        @endif
                        @if ($campaign->subject)
                            <dt class="col-4">Assunto</dt><dd class="col-8">{{ $campaign->subject }}</dd>
                        @endif
                    </dl>
                    <div class="message-preview">{{ $campaign->body }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">Resultado</h2>
                    @if ($campaign->isDraft())
                        <p class="mb-1"><strong>{{ $audienceCount }}</strong> {{ $audienceCount === 1 ? 'cliente receberá' : 'clientes receberão' }} esta campanha hoje.</p>
                        <p class="small text-body-secondary mb-0">A lista é fechada ao iniciar o envio: só entra quem autorizou o canal e tem o contato preenchido.</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach ($statuses as $status => $label)
                                @if (($counts[$status] ?? 0) > 0)
                                    <li class="d-flex justify-content-between py-1 border-bottom"><span>{{ $label }}</span><strong>{{ $counts[$status] }}</strong></li>
                                @endif
                            @endforeach
                            <li class="d-flex justify-content-between py-1"><span>Total</span><strong>{{ $total }}</strong></li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @unless ($campaign->isDraft())
        <div class="row g-2 mb-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="messageStatus" class="form-label">Mensagens</label>
                <select id="messageStatus" class="form-select" wire:model.live="messageStatus">
                    <option value="">Todas</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <caption class="visually-hidden">Mensagens da campanha</caption>
                    <thead>
                        <tr><th scope="col">Cliente</th><th scope="col">Destino</th><th scope="col">Situação</th><th scope="col">Enviada em</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr wire:key="message-{{ $message->id }}">
                                <td>{{ $message->customer->name }}</td>
                                <td @class(['text-nowrap' => $message->channel === 'whatsapp', 'text-break' => $message->channel !== 'whatsapp'])>{{ $message->channel === 'whatsapp' ? \App\Support\WhatsApp::format($message->destination) : $message->destination }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $message->statusTone() }}">{{ $message->statusLabel() }}</span>
                                    @if ($message->error)<div class="small text-body-secondary">{{ $message->error }}</div>@endif
                                </td>
                                <td class="text-nowrap">{{ $message->sent_at?->format('d/m/Y H:i') ?? '-' }}@if ($message->handler)<div class="small text-body-secondary">{{ $message->handler->name }}</div>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma mensagem nesta situação.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $messages->links() }}</div>
    @endunless
</div>
