<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by TwoFaController so your own application can react to it
 * (e.g. write to an audit log, send a notification) without TwoFaController
 * needing to know anything about your app-specific services.
 */
class TwoFactorEnabled
{
    use Dispatchable;

    public function __construct(public User $user)
    {
    }
}
