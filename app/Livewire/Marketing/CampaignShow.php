<?php

namespace App\Livewire\Marketing;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Mail\MarketingMail;
use App\Models\Customer;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Services\CampaignService;
use App\Support\MessageTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class CampaignShow extends Component
{
    use HandlesBusinessRules;
    use WithPagination;

    #[Locked]
    public int $campaignId;

    #[Url(as: 'situacao')]
    public string $messageStatus = '';

    public function mount($campaign): void
    {
        Gate::authorize('marketing.ver');
        $this->campaignId = MarketingCampaign::findOrFail($campaign)->id;
    }

    public function updatedMessageStatus(): void
    {
        $this->resetPage();
    }

    public function launch(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.disparar');
        $total = 0;
        $this->attempt(function () use ($campaigns, &$total) {
            $total = $campaigns->launch($this->campaign(), Auth::user());
        }, 'Envio iniciado.');

        if ($total > 0) {
            session()->now('success', $this->campaign()->isEmail()
                ? "Envio iniciado para {$total} cliente(s). Os e-mails saem aos poucos, respeitando o limite por hora."
                : "{$total} mensagem(ns) na fila do WhatsApp. Abra a fila para enviar uma a uma.");
        }
    }

    public function pause(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.disparar');
        $this->attempt(fn () => $campaigns->pause($this->campaign()), 'Envio pausado.');
    }

    public function resume(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.disparar');
        $this->attempt(fn () => $campaigns->resume($this->campaign()), 'Envio retomado.');
    }

    public function cancel(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.disparar');
        $this->attempt(fn () => $campaigns->cancel($this->campaign()), 'Campanha cancelada. As mensagens pendentes não serão enviadas.');
    }

    public function delete(CampaignService $campaigns): void
    {
        Gate::authorize('marketing.campanhas');

        if ($this->attempt(fn () => $campaigns->delete($this->campaign()), 'Campanha excluída.')) {
            session()->flash('success', 'Campanha excluída.');
            $this->redirectRoute('marketing.campaigns.index');
        }
    }

    /** Envia a mensagem para o e-mail de quem está usando o sistema, para conferência. */
    public function sendTest(): void
    {
        Gate::authorize('marketing.campanhas');
        $campaign = $this->campaign();

        if (! $campaign->isEmail()) {
            return;
        }

        $sample = new Customer(['name' => Auth::user()->name]);

        try {
            Mail::to(Auth::user()->email)->send(new MarketingMail(
                '[Teste] '.MessageTemplate::render((string) $campaign->subject, $sample),
                MessageTemplate::render($campaign->body, $sample),
                null,
            ));
            session()->now('success', 'E-mail de teste enviado para '.Auth::user()->email.'.');
        } catch (Throwable $e) {
            report($e);
            session()->now('error', 'Não foi possível enviar o teste. Confira a configuração de e-mail do servidor.');
        }
    }

    private function campaign(): MarketingCampaign
    {
        return MarketingCampaign::findOrFail($this->campaignId);
    }

    public function render(): View
    {
        $campaigns = app(CampaignService::class);
        $campaign = $this->campaign();
        $counts = $campaign->messages()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.marketing.campaign-show', [
            'campaign' => $campaign->load('creator:id,name'),
            'counts' => $counts,
            'total' => $counts->sum(),
            'audienceCount' => $campaign->isDraft() ? $campaigns->audience($campaign->filters, $campaign->channel)->count() : null,
            'messages' => $campaign->messages()->with('customer:id,name', 'handler:id,name')
                ->when(array_key_exists($this->messageStatus, MarketingMessage::STATUS_LABELS), fn ($q) => $q->where('status', $this->messageStatus))
                ->orderBy('id')
                ->paginate(25),
            'statuses' => MarketingMessage::STATUS_LABELS,
        ])->title($campaign->name);
    }
}
