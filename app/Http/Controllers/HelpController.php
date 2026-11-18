<?php

namespace App\Http\Controllers;

use App\Support\Help;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Central de ajuda: só mostra os assuntos liberados para o perfil do usuário. */
class HelpController extends Controller
{
    public function index(Request $request): View
    {
        return view('help.index', ['sections' => Help::visibleTo($request->user())]);
    }

    public function show(Request $request, string $topic): View
    {
        $found = Help::find($topic, $request->user());
        abort_unless($found, 404);

        return view('help.show', [
            'topic' => $found,
            'sections' => Help::visibleTo($request->user()),
        ]);
    }
}
