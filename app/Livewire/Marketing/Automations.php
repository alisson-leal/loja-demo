<?php

namespace App\Livewire\Marketing;

use App\Models\MarketingAutomation;
use App\Models\MarketingMessage;
use App\Services\AuditLogger;
use App\Support\MessageTemplate;
use App\Support\StoreSegment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mensagens automáticas')]
class Automations extends Component
{
    private const FIELDS = ['enabled', 'days_before', 'send_whatsapp', 'send_email', 'email_subject', 'email_body', 'whatsapp_body'];

    /** @var array<string, array{enabled: bool, days_before: int|string, send_whatsapp: bool, send_email: bool, email_subject: string, email_body: string, whatsapp_body: string}> */
    public array $forms = [];

    public function mount(): void
    {
        Gate::authorize('marketing.automacoes');

        foreach ($this->automations() as $automation) {
            $this->forms[$automation->key] = $automation->only(self::FIELDS);
        }
    }

    /** Na ordem do catálogo: agradecimento primeiro, depois os aniversários. */
    private function automations()
    {
        $order = array_flip(array_keys(MarketingAutomation::LABELS));

        return MarketingAutomation::all()
            ->reject(fn (MarketingAutomation $a) => $a->isForChildren() && ! StoreSegment::childrenEnabled())
            ->sortBy(fn (MarketingAutomation $a) => $order[$a->key] ?? 99)
            ->keyBy('key');
    }

    public function save(string $key, AuditLogger $audit): void
    {
        Gate::authorize('marketing.automacoes');
        abort_unless(isset($this->forms[$key]), 404);

        $this->validate([
            "forms.{$key}.enabled" => ['boolean'],
            "forms.{$key}.send_whatsapp" => ['boolean'],
            "forms.{$key}.send_email" => ['boolean'],
            "forms.{$key}.days_before" => ['required', 'integer', 'min:0', 'max:30'],
            "forms.{$key}.email_subject" => ['required', 'string', 'max:150'],
            "forms.{$key}.email_body" => ['required', 'string', 'min:5', 'max:5000'],
            "forms.{$key}.whatsapp_body" => ['required', 'string', 'min:5', 'max:1000'],
        ], [], [
            "forms.{$key}.days_before" => 'antecedência',
            "forms.{$key}.email_subject" => 'assunto',
            "forms.{$key}.email_body" => 'mensagem do e-mail',
            "forms.{$key}.whatsapp_body" => 'mensagem do WhatsApp',
        ]);

        $form = $this->forms[$key];

        if ($form['enabled'] && ! $form['send_whatsapp'] && ! $form['send_email']) {
            $this->addError("forms.{$key}.send_whatsapp", 'Escolha ao menos um canal para a mensagem ativa.');

            return;
        }

        $automation = MarketingAutomation::where('key', $key)->firstOrFail();
        $automation->fill(collect($form)->only(self::FIELDS)->all() + ['updated_by' => Auth::id()]);

        if (! $automation->isBirthday()) {
            $automation->days_before = 0;
        }

        $automation->save();

        $audit->record('marketing.automation_saved', "Mensagem automática \"{$automation->label()}\" ".($automation->enabled ? 'ativada' : 'desativada').'.', $automation);
        session()->now('success', 'Mensagem automática salva.');
    }

    public function render(): View
    {
        return view('livewire.marketing.automations', [
            'automations' => $this->automations(),
            'fields' => StoreSegment::childrenEnabled()
                ? MessageTemplate::FIELDS
                : collect(MessageTemplate::FIELDS)->except(['{filho}', '{idade}', '{data_aniversario}'])->all(),
            'recent' => MarketingMessage::whereNotNull('automation')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('automation, channel, status, COUNT(*) as total')->groupBy('automation', 'channel', 'status')->get(),
        ]);
    }
}
