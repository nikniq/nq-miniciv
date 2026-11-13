<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Administrators. Used by deploy.sh and cx.sh (options 60-64); safe to run
| on the server without tinker.
*/
Artisan::command('admin:list', function () {
    $admins = User::where('is_admin', true)->orderBy('email')->get(['id', 'name', 'email', 'created_at']);
    if ($admins->isEmpty()) {
        $this->warn('No administrators. Grant one with: php artisan admin:grant you@example.com');

        return 0;
    }
    $this->table(['ID', 'Nickname', 'Email', 'Since'], $admins->map(fn ($u) => [$u->id, $u->name, $u->email, $u->created_at?->toDateString()]));

    return 0;
})->purpose('List administrators');

Artisan::command('admin:grant {email}', function (string $email) {
    $user = User::where('email', Str::lower($email))->first();
    if (! $user || $user->isGuest()) {
        $this->error("No registered account for {$email}. Sign in on the site once, then run this again.");

        return 1;
    }
    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$user->email} is now an administrator.");

    return 0;
})->purpose('Make an existing account an administrator');

Artisan::command('admin:revoke {email}', function (string $email) {
    $user = User::where('email', Str::lower($email))->first();
    if (! $user) {
        $this->error("No account for {$email}.");

        return 1;
    }
    $user->forceFill(['is_admin' => false])->save();
    $this->info("{$user->email} is no longer an administrator.");

    return 0;
})->purpose('Remove administrator rights');

Artisan::command('admin:sync', function () {
    $emails = config('auth.admin_emails');
    if (! $emails) {
        $this->line('ADMIN_EMAILS is empty; nothing to sync.');

        return 0;
    }
    foreach ($emails as $email) {
        $user = User::where('email', $email)->where('is_guest', false)->first();
        if ($user) {
            if (! $user->is_admin) {
                $user->forceFill(['is_admin' => true])->save();
            }
            $this->info("admin: {$email}");
        } else {
            $this->warn("not yet registered: {$email} (sign in once, then redeploy or run admin:sync)");
        }
    }

    return 0;
})->purpose('Grant admin to every address in ADMIN_EMAILS (never revokes)');
