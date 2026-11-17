<div>
    <x-flash />

    <p class="text-body-secondary">
        Só recebe quem autorizou o canal no cadastro. Os e-mails saem sozinhos; as mensagens de WhatsApp entram em
        <a href="{{ route('marketing.whatsapp') }}">WhatsApp a enviar</a> para a loja enviar com um clique.
    </p>

    @foreach ($automations as $key => $automation)
        <form wire:submit="save('{{ $key }}')" class="card mb-3" wire:key="automation-{{ $key }}" novalidate>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                    <h2 class="h6 mb-0">{{ $automation->label() }}</h2>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled-{{ $key }}" wire:model="forms.{{ $key }}.enabled">
                        <label class="form-check-label" for="enabled-{{ $key }}">Ativa</label>
                    </div>
                </div>
                <p class="small text-body-secondary mb-3">
                    @if ($automation->isBirthday())
                        Preparada todos os dias a partir das 8h, para quem faz aniversário na data escolhida.
                    @else
                        Preparada assim que uma venda é concluída com o cliente identificado. Se a venda for cancelada antes do envio, a mensagem é descartada.
                    @endif
                </p>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <span class="form-label d-block">Canais</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input @error("forms.$key.send_whatsapp") is-invalid @enderror" type="checkbox" id="wa-on-{{ $key }}" wire:model.live="forms.{{ $key }}.send_whatsapp">
                            <label class="form-check-label" for="wa-on-{{ $key }}">WhatsApp</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="email-on-{{ $key }}" wire:model.live="forms.{{ $key }}.send_email">
                            <label class="form-check-label" for="email-on-{{ $key }}">E-mail</label>
                        </div>
                        @error("forms.$key.send_whatsapp") <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    @if ($automation->isBirthday())
                        <div class="col-12 col-md-4">
                            <label for="days-{{ $key }}" class="form-label">Enviar quantos dias antes</label>
                            <input id="days-{{ $key }}" type="number" min="0" max="30" class="form-control @error("forms.$key.days_before") is-invalid @enderror" wire:model="forms.{{ $key }}.days_before">
                            @error("forms.$key.days_before") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">0 = no dia do aniversário.</div>
                        </div>
                    @endif
                </div>

                <div class="row g-3 mt-0">
                    @if ($forms[$key]['send_whatsapp'])
                        <div @class(['col-12', 'col-lg-6' => $forms[$key]['send_email']])>
                            <label for="wa-{{ $key }}" class="form-label">Mensagem do WhatsApp</label>
                            <textarea id="wa-{{ $key }}" rows="7" maxlength="1000" class="form-control @error("forms.$key.whatsapp_body") is-invalid @enderror" wire:model="forms.{{ $key }}.whatsapp_body"></textarea>
                            @error("forms.$key.whatsapp_body") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">"{{ \App\Support\MessageTemplate::WHATSAPP_OPT_OUT }}" é acrescentado ao final.</div>
                        </div>
                    @endif
                    @if ($forms[$key]['send_email'])
                        <div @class(['col-12', 'col-lg-6' => $forms[$key]['send_whatsapp']])>
                            <label for="subject-{{ $key }}" class="form-label">Assunto do e-mail</label>
                            <input id="subject-{{ $key }}" type="text" maxlength="150" class="form-control mb-2 @error("forms.$key.email_subject") is-invalid @enderror" wire:model="forms.{{ $key }}.email_subject">
                            @error("forms.$key.email_subject") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <label for="email-{{ $key }}" class="form-label">Mensagem do e-mail</label>
                            <textarea id="email-{{ $key }}" rows="5" maxlength="5000" class="form-control @error("forms.$key.email_body") is-invalid @enderror" wire:model="forms.{{ $key }}.email_body"></textarea>
                            @error("forms.$key.email_body") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>

                @php $stats = $recent->where('automation', $key); @endphp
                @if ($stats->isNotEmpty())
                    <p class="small text-body-secondary mt-3 mb-0">
                        Últimos 30 dias:
                        {{ $stats->where('status', \App\Models\MarketingMessage::SENT)->sum('total') }} enviada(s),
                        {{ $stats->where('status', \App\Models\MarketingMessage::PENDING)->sum('total') }} pendente(s),
                        {{ $stats->whereIn('status', [\App\Models\MarketingMessage::EXPIRED, \App\Models\MarketingMessage::SKIPPED, \App\Models\MarketingMessage::FAILED, \App\Models\MarketingMessage::CANCELLED])->sum('total') }} não enviada(s).
                    </p>
                @endif

                <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">Salvar</button>
            </div>
        </form>
    @endforeach

    <div class="card">
        <div class="card-body small">
            <h2 class="h6">Campos disponíveis</h2>
            <ul class="mb-0">
                @foreach ($fields as $field => $description)
                    <li><code>{{ $field }}</code>: {{ $description }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
