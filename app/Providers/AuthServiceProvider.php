<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [/* ... */];

    public function boot(): void
    {
        Gate::define('is-registrar',   fn(User $u) => $u->role === User::ROLE_REGISTRAR);
        Gate::define('is-dean',        fn(User $u) => $u->role === User::ROLE_DEAN);
        Gate::define('is-chair',       fn(User $u) => $u->role === User::ROLE_HEAD);
        Gate::define('is-faculty',     fn(User $u) => $u->role === User::ROLE_FACULTY);

        // grouped permissions
        Gate::define('manage-scheduling', fn(User $u) => in_array($u->role, [
            User::ROLE_REGISTRAR, User::ROLE_DEAN, User::ROLE_HEAD
        ]));
    }
}
