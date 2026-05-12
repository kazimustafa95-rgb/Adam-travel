<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        Admin $admin,
        string $action,
        string $description,
        Model|string|null $target = null,
        array $metadata = [],
    ): ActivityLog {
        $targetType = null;
        $targetId = null;
        $targetLabel = null;

        if ($target instanceof Model) {
            $targetType = class_basename($target);
            $targetId = $target->getKey();
            $targetLabel = $target->title
                ?? $target->name
                ?? $target->email
                ?? $target->subject
                ?? $target->key
                ?? $target->slug
                ?? $targetType.' #'.$targetId;
        } elseif (is_string($target) && $target !== '') {
            $targetLabel = $target;
        }

        return ActivityLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label' => $targetLabel ? mb_substr($targetLabel, 0, 255) : null,
            'ip_address' => request()->ip(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
