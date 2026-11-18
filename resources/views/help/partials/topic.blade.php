@php($steps = \App\Support\Help::steps($topic, auth()->user()))
<p class="help-intro">{{ \App\Support\Help::format($topic['intro']) }}</p>

@if ($steps)
    <h3 class="h6 mt-3">Passo a passo</h3>
    <ol class="help-steps">
        @foreach ($steps as $step)
            <li>{{ \App\Support\Help::format($step) }}</li>
        @endforeach
    </ol>
@endif

@if ($topic['tips'])
    <h3 class="h6 mt-3">Dicas</h3>
    <ul class="help-tips">
        @foreach ($topic['tips'] as $tip)
            <li>{{ \App\Support\Help::format($tip) }}</li>
        @endforeach
    </ul>
@endif
