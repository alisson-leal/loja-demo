<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SaleReceipt extends Mailable
{
    public function __construct(public readonly Sale $sale) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Comprovante da venda #{$this->sale->number()} - ".config('loja.store.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sale-receipt');
    }
}
