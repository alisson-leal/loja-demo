<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Grava um evento de auditoria. Nunca envie senhas ou tokens em $properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'description' => Str::limit($description, 250),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}
