<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Ajuda do sistema (config/ajuda.php). Cada usuário só vê os tópicos e os
 * passos das telas que o seu perfil pode usar.
 */
final class Help
{
    /** @return Collection<string, array> */
    public static function topics(): Collection
    {
        return collect(config('ajuda.topics'))->map(fn (array $topic, string $slug) => $topic + ['slug' => $slug, 'when' => null]);
    }

    public static function canSee(User $user, array $topic): bool
    {
        if ($topic['when'] === 'multiple_stores' && ! CurrentStore::multiple()) {
            return false;
        }

        return $topic['can'] === [] || $user->canAny($topic['can']);
    }

    /** Tópicos liberados, agrupados pela seção do menu, na ordem do menu. */
    public static function visibleTo(User $user): Collection
    {
        $order = array_flip(config('ajuda.sections'));

        return self::topics()
            ->filter(fn (array $topic) => self::canSee($user, $topic))
            ->groupBy('section')
            ->sortBy(fn ($topics, string $section) => $order[$section] ?? 99);
    }

    public static function find(string $slug, User $user): ?array
    {
        $topic = self::topics()->get($slug);

        return $topic && self::canSee($user, $topic) ? $topic : null;
    }

    /** Tópico da tela atual (para a tecla F1). */
    public static function forRoute(?string $route, User $user): ?array
    {
        if (! $route) {
            return null;
        }

        return self::topics()->first(fn (array $topic) => self::canSee($user, $topic)
            && collect($topic['routes'])->contains(fn (string $pattern) => Str::is($pattern, $route)));
    }

    /** Passos que o usuário pode executar. @return list<string> */
    public static function steps(array $topic, User $user): array
    {
        return collect($topic['steps'])
            ->filter(fn ($step) => is_string($step) || $user->canAny($step['can']))
            ->map(fn ($step) => is_string($step) ? $step : $step['text'])
            ->values()
            ->all();
    }

    /** Texto seguro com **destaque** em negrito. */
    public static function format(string $text): HtmlString
    {
        return new HtmlString(preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text)));
    }
}
