<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    public function current(): ?Tenant
    {
        return app('currentTenant');
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return \App\Models\PlatformSetting::getValue($key, $default);
    }
}
