<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait ChecksPanelModules
{
    /**
     * @param  array<int, string>  $modules
     */
    protected static function currentUserCanAccessModules(array $modules): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasAnyPanelModules($modules);
    }
}
