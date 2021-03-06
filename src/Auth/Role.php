<?php

namespace Statamic\Auth;

use Statamic\Facades;
use Statamic\Contracts\Auth\Role as RoleContract;

abstract class Role implements RoleContract
{
    public function editUrl()
    {
        return cp_route('roles.edit', $this->handle());
    }

    public function deleteUrl()
    {
        return cp_route('roles.destroy', $this->handle());
    }

    public static function __callStatic($method, $parameters)
    {
        return Facades\Role::{$method}(...$parameters);
    }
}
