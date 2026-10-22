<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logModelOperation($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $dirty = $model->getDirty();
            $original = array_intersect_key($model->getOriginal(), $dirty);

            // Strip sensitive fields
            unset(
                $dirty['password'],
                $original['password'],
                $dirty['remember_token'],
                $original['remember_token']
            );

            if (!empty($dirty)) {
                static::logModelOperation($model, 'updated', $original, $dirty);
            }
        });

        static::deleted(function (Model $model) {
            static::logModelOperation($model, 'deleted', $model->getAttributes(), null);
        });
    }

    protected static function logModelOperation(Model $model, string $action, ?array $old, ?array $new): void
    {
        try {
            $user = Auth::user();
            $restaurantId = $model->restaurant_id
                ?? $model->restaurant?->id
                ?? $user?->restaurant_id
                ?? null;

            ActivityLog::create([
                'restaurant_id' => $restaurantId,
                'user_id'       => $user?->id,
                'user_name'     => $user?->name ?? 'System/Guest',
                'user_role'     => $user?->role?->name ?? ($user?->is_super_admin ? 'super_admin' : 'guest'),
                'action'        => $action,
                'model_type'    => get_class($model),
                'model_id'      => $model->getKey(),
                'description'   => ucfirst($action) . ' ' . class_basename($model) . ' #' . $model->getKey(),
                'old_values'    => $old,
                'new_values'    => $new,
                'ip_address'    => Request::ip(),
                'user_agent'    => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Never break business transaction if audit logging encounters an issue
            \Illuminate\Support\Facades\Log::warning('Activity logging failed: ' . $e->getMessage());
        }
    }
}
