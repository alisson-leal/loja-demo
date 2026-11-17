<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12"><x-text-field name="name" label="Nome da campanha (uso interno)" required maxlength="120" /></div>

                            <div class="col-12 col-md-6">
                                <span class="form-label d-block">Canal</span>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" id="channel-whatsapp" value="whatsapp" wire:model.live="channel">
                                    <label class="form-check-label" for="channel-whatsapp">WhatsApp</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" id="channel-email" value="email" wire:model.live="channel">
                                    <label class="form-check-label" for="channel-email">E-mail</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="segment" class="form-label">Público</label>
                                <select id="segment" class="form-select @error('segment') is-invalid @enderror" wire:model.live="segment">
                                    <option value="">Todos os clientes</option>
                                    @foreach ($segments as $item)
                                        <option value="{{ $item->id }}">Segmento: {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error('segment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Os segmentos são criados na tela Clientes, salvando os filtros.</div>
                            </div>

                            @if ($channel === 'email')
                                <div class="col-12">
                                    <label for="subject" class="form-label">Assunto do e-mail</label>
                                    <input id="subject" type="text" maxlength="150" wire:model.live.debounce.500ms="subject" class="form-control @error('subject') is-invalid @enderror">
                                    @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <label for="body" class="form-label">Mensagem</label>
                                <textarea id="body" rows="8" maxlength="{{ $channel === 'whatsapp' ? 1000 : 5000 }}" wire:model.live.debounce.500ms="body" class="form-control @error('body') is-invalid @enderror"></textarea>
                                @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Campos que viram dados de cada cliente:
                                    @foreach ($fields as $field => $description)
                                        <code title="{{ $description }}">{{ $field }}</code>@if (! $loop->last), @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar rascunho</button>
                    <a href="{{ $campaignId ? route('marketing.campaigns.show', $campaignId) : route('marketing.campaigns.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6">Quem vai receber</h2>
                        <p class="mb-1"><strong id="audience-count">{{ $audienceCount }}</strong> {{ $audienceCount === 1 ? 'cliente autorizou' : 'clientes autorizaram' }} receber por {{ $channel === 'email' ? 'e-mail' : 'WhatsApp' }}.</p>
                        <p class="small text-body-secondary mb-0">O público tem {{ $segmentCount }} {{ $segmentCount === 1 ? 'cliente' : 'clientes' }}; só recebe quem deu consentimento para o canal e tem o contato preenchido.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6">Pré-visualização</h2>
                        @if ($preview)
                            <p class="small text-body-secondary">Como {{ $previewName }} vai receber:</p>
                            @if ($previewSubject)
                                <p class="small mb-1"><strong>Assunto:</strong> {{ $previewSubject }}</p>
                            @endif
                            <div class="message-preview">{{ $preview }}</div>
                        @else
                            <p class="small text-body-secondary mb-0">Escreva a mensagem para ver como ela fica para um cliente deste público.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
