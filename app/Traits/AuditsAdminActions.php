<?php

namespace App\Traits;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

trait AuditsAdminActions
{
    protected function auditLog(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $changes = null,
        ?array $metadata = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_user_id' => request()->user()?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'changes' => $changes,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    protected function auditModelChange(string $action, Model $model, array $oldValues = []): AdminAuditLog
    {
        $changes = null;

        if (!empty($oldValues)) {
            $diff = [];
            foreach ($oldValues as $key => $oldVal) {
                $newVal = $model->getAttribute($key);
                if ($oldVal != $newVal) {
                    $diff[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            if (!empty($diff)) {
                $changes = $diff;
            }
        }

        $targetType = class_basename($model);

        return $this->auditLog($action, $targetType, $model->getKey(), $changes);
    }
}
