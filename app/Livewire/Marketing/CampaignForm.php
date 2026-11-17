<?php

namespace App\Livewire\Marketing;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\MarketingCampaign;
use App\Services\CampaignService;
use App\Support\CustomerFilter;
use App\Support\MessageTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CampaignForm extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $campaignId = null;

    public string $name = '';

    public string $channel = 'whatsapp';

    /** Id do segmento salvo, ou vazio para todos os clientes. */
    public string $segment = '';

    public string $subject = '';

    public string $body = '';

    public function mount($campaign = null): void
    {
        Gate::authorize('marketing.campanhas');

        if ($campaign === null) {
            return;
        }

        $model = MarketingCampaign::findOrFail($campaign);
        abort_unless($model->isDraft(), 403, 'Só é possível editar campanhas em rascunho.');

        $this->campaignId = $model->id;
        $this->name = $model->name;
        $this->channel = $model->channel;
        $this->subject = (string) $model->subject;
        $this->body = $model->body;
        $this->segment = (string) CustomerSegment::where('name', $model->segment_name)->value('id');
    }

    public function save(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.campanhas');

        $this->name = trim($this->name);
        $this->subject = trim($this->subject);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'channel' => ['required', Rule::in(array_keys(MarketingCampaign::CHANNEL_LABELS))],
            'segment' => ['nullable', Rule::exists('customer_segments', 'id')],
            'subject' => ['nullable', 'required_if:channel,email', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:5', 'max:'.($this->channel === 'whatsapp' ? 1000 : 5000)],
        ], [
            'subject.required_if' => 'Informe o assunto do e-mail.',
        ], ['name' => 'nome', 'channel' => 'canal', 'segment' => 'público', 'subject' => 'assunto', 'body' => 'mensagem']);

        $segment = $this->segment !== '' ? CustomerSegment::find($this->segment) : null;

        $campaign = null;
        $ok = $this->attempt(function () use ($campaigns, $segment, &$campaign) {
            $campaign = $campaigns->save($this->campaignId ? MarketingCampaign::findOrFail($this->campaignId) : null, [
                'name' => $this->name,
                'channel' => $this->channel,
                'segment_name' => $segment?->name,
                'filters' => $segment?->filters ?? CustomerFilter::DEFAULTS,
                'subject' => $this->subject ?: null,
                'body' => $this->body,
            ], Auth::user());
        }, 'Campanha salva.');

        if ($ok) {
            session()->flash('success', 'Campanha salva como rascunho. Confira o público e inicie o envio quando quiser.');
            $this->redirectRoute('marketing.campaigns.show', ['campaign' => $campaign->id]);
        }
    }

    public function render(): View
    {
        $campaigns = app(CampaignService::class);
        $segment = $this->segment !== '' ? CustomerSegment::find($this->segment) : null;
        $filters = $segment?->filters ?? CustomerFilter::DEFAULTS;
        $channel = array_key_exists($this->channel, MarketingCampaign::CHANNEL_LABELS) ? $this->channel : 'whatsapp';
        $audience = $campaigns->audience($filters, $channel);
        $sample = (clone $audience)->orderBy('name')->first();

        return view('livewire.marketing.campaign-form', [
            'segments' => CustomerSegment::orderBy('name')->get(),
            'audienceCount' => $audience->count(),
            'segmentCount' => CustomerFilter::apply(Customer::query(), $filters)->count(),
            'preview' => $sample && trim($this->body) !== ''
                ? MessageTemplate::forChannel($channel, MessageTemplate::render($this->body, $sample))
                : null,
            'previewName' => $sample?->name,
            'previewSubject' => $sample && $channel === 'email' && trim($this->subject) !== ''
                ? MessageTemplate::render($this->subject, $sample)
                : null,
            'fields' => collect(MessageTemplate::FIELDS)->except(['{filho}', '{idade}', '{data_aniversario}', '{numero_venda}', '{valor_compra}']),
        ])->title($this->campaignId ? 'Editar campanha' : 'Nova campanha');
    }
}
