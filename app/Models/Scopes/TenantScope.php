<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app('currentTenant');

        if ($tenant) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenant->id);
        }
    }
}
