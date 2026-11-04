<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One door for audit entries: actor, action, related entity, and the
 * changed values — written once, never edited [S10].
 */
class AuditLogger
{
    public function record(?User $actor, string $action, Model $entity, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->id,
            'changes' => $changes === [] ? null : $changes,
            'created_at' => now(),
        ]);
    }
}
