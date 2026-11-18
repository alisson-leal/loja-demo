@component('layouts.app', ['title' => 'Ajuda: '.$topic['title']])
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5"><i class="bi bi-{{ $topic['icon'] }}" aria-hidden="true"></i> {{ $topic['title'] }}</h2>
                    @include('help.partials.topic', ['topic' => $topic])
                    <a href="{{ route('help.index') }}" class="btn btn-light mt-2"><i class="bi bi-arrow-left" aria-hidden="true"></i> Todos os assuntos</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">Outros assuntos</h2>
                    @foreach ($sections as $section => $topics)
                        <p class="small text-body-secondary text-uppercase fw-semibold mt-3 mb-1">{{ $section }}</p>
                        <ul class="list-unstyled mb-0 help-list">
                            @foreach ($topics as $item)
                                <li>
                                    <a href="{{ route('help.show', $item['slug']) }}" @class(['fw-semibold' => $item['slug'] === $topic['slug']])
                                       @if ($item['slug'] === $topic['slug']) aria-current="page" @endif>{{ $item['title'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endcomponent
