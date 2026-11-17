<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-5">
            <label for="origin" class="form-label">Origem</label>
            <select id="origin" class="form-select" wire:model.live="origin">
                <option value="">Todas as mensagens</option>
                <option value="aniversarios">Aniversários</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}">Campanha: {{ $campaign->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-7">
            <p class="small text-body-secondary mb-0">
                Clique em <strong>Abrir no WhatsApp</strong>: a conversa abre com a mensagem pronta. Envie pelo WhatsApp da loja e ela sai desta lista.
                Se o cliente responder pedindo para sair, use <strong>Cliente pediu para sair</strong>.
            </p>
        </div>
    </div>

    <p class="small text-body-secondary" id="queue-count">{{ $messages->total() }} {{ $messages->total() === 1 ? 'mensagem pendente' : 'mensagens pendentes' }}</p>

    <div class="row g-3">
        @forelse ($messages as $message)
            @php $link = \App\Support\WhatsApp::url($message->destination, $message->body); @endphp
            <div class="col-12 col-lg-6" wire:key="wa-{{ $message->id }}">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <div>
                                <a href="{{ route('customers.show', $message->customer_id) }}" class="fw-semibold">{{ $message->customer->name }}</a>
                                <div class="small text-body-secondary">{{ \App\Support\WhatsApp::format($message->destination) }}</div>
                            </div>
                            <span class="badge text-bg-secondary align-self-start text-wrap">{{ $message->originLabel() }}</span>
                        </div>
                        <div class="message-preview small mb-3 flex-grow-1">{{ $message->body }}</div>
                        @if ($message->expires_at)
                            <p class="small text-body-secondary mb-2">Enviar até {{ $message->expires_at->format('d/m') }}.</p>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            @if ($link)
                                <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-success" wire:click="markSent({{ $message->id }})">
                                    <i class="bi bi-whatsapp" aria-hidden="true"></i> Abrir no WhatsApp
                                </a>
                            @endif
                            <button type="button" class="btn btn-light" wire:click="skip({{ $message->id }})">Pular</button>
                            <button type="button" class="btn btn-light text-danger" wire:click="optOut({{ $message->id }})"
                                    wire:confirm="Registrar que {{ $message->customer->name }} não quer mais receber mensagens pelo WhatsApp?">Cliente pediu para sair</button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-center text-body-secondary py-4">Nenhuma mensagem para enviar agora.</div></div>
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $messages->links() }}</div>
</div>
