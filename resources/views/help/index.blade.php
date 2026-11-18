@component('layouts.app', ['title' => 'Ajuda'])
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-3">
                Estes são os assuntos liberados para o seu perfil. Em qualquer tela, aperte <kbd>F1</kbd>
                ou o botão <i class="bi bi-question-circle" aria-hidden="true"></i> no topo para ver a ajuda daquela tela.
            </p>
            <label for="help-search" class="visually-hidden">Buscar na ajuda</label>
            <input id="help-search" type="search" class="form-control" placeholder="Buscar na ajuda (ex.: troca, sangria, etiqueta)" data-help-search autocomplete="off">
            <p class="small text-body-secondary mt-2 mb-0" data-help-empty hidden>Nenhum assunto encontrado.</p>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($sections as $section => $topics)
            <div class="col-12 col-md-6 col-xl-4" data-help-section>
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ $section }}</h2>
                        <ul class="list-unstyled mb-0 help-list">
                            @foreach ($topics as $topic)
                                <li data-help-item data-help-text="{{ $topic['title'].' '.$topic['intro'].' '.implode(' ', \App\Support\Help::steps($topic, auth()->user())).' '.implode(' ', $topic['tips']) }}">
                                    <a href="{{ route('help.show', $topic['slug']) }}"><i class="bi bi-{{ $topic['icon'] }}" aria-hidden="true"></i> {{ $topic['title'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endcomponent
