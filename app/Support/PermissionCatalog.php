<?php

namespace App\Support;

/**
 * Fonte única dos nomes de módulos, operações e perfis padrão.
 */
final class PermissionCatalog
{
    public const ADMIN_ROLE = 'Administrador';

    /** @return array<string, array{label: string, operations: array<string, string>}> */
    public static function modules(): array
    {
        return config('loja.modules');
    }

    /** @return list<string> */
    public static function names(): array
    {
        $names = [];

        foreach (self::modules() as $module => $definition) {
            foreach (array_keys($definition['operations']) as $operation) {
                $names[] = "{$module}.{$operation}";
            }
        }

        return $names;
    }

    /** @return array<string, list<string>> */
    public static function defaultRoles(): array
    {
        return config('loja.default_roles');
    }

    public static function isDefaultRole(string $name): bool
    {
        return $name === self::ADMIN_ROLE || array_key_exists($name, self::defaultRoles());
    }
}
