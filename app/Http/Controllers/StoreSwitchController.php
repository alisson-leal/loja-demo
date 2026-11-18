<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;

/** Troca a loja em que o usuário está trabalhando. */
class StoreSwitchController extends Controller
{
    public function __invoke(Store $store): RedirectResponse
    {
        abort_unless(CurrentStore::switch($store->id), 403);

        return back()->with('success', "Você está trabalhando na loja {$store->name}.");
    }
}
