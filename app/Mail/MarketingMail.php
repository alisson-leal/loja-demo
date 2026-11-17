<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * E-mail de campanha ou automação. Sempre leva o link de descadastro, também
 * no cabeçalho List-Unsubscribe (descadastro com um clique nos provedores).
 */
class MarketingMail extends Mailable
{
    public readonly string $unsubscribeUrl;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $bodyText,
        ?Customer $customer,
    ) {
        $this->unsubscribeUrl = $customer
            ? URL::signedRoute('marketing.unsubscribe', ['customer' => $customer->id])
            : url('/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing',
            text: 'emails.marketing-text',
            with: ['store' => config('loja.store')],
        );
    }
}
