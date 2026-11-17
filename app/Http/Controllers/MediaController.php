<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega as imagens de produtos sem depender de link simbólico,
 * que nem toda hospedagem compartilhada permite.
 */
class MediaController extends Controller
{
    public function show(string $path): Response
    {
        $disk = Storage::disk('public');

        abort_unless(Str::startsWith($path, 'product-images/') && ! Str::contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=2592000']);
    }
}
