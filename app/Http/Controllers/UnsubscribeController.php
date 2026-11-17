<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ConsentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Descadastro de e-mails pelo link assinado. A abertura do link só mostra a
 * confirmação (robôs de antivírus visitam links); a revogação exige POST, que
 * também atende o descadastro com um clique dos provedores de e-mail.
 */
class UnsubscribeController extends Controller
{
    public function show(Customer $customer): View
    {
        return view('marketing.unsubscribe', [
            'customer' => $customer,
            'done' => ! $customer->email_opt_in,
            'store' => config('loja.store'),
        ]);
    }

    public function store(Request $request, Customer $customer, ConsentService $consents): View
    {
        $consents->set($customer, 'email', false, 'descadastro');

        return view('marketing.unsubscribe', [
            'customer' => $customer,
            'done' => true,
            'store' => config('loja.store'),
        ]);
    }
}
