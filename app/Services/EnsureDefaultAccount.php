<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Str;

final class EnsureDefaultAccount
{
    public static function run(): Account
    {
        $existing = Account::query()->first();
        if ($existing) {
            return $existing;
        }

        $name = config('app.name', env('APP_NAME', 'Agent AI'));
        $slug = Str::slug($name ?: 'Agent AI');

        return Account::firstOrCreate([
            'name' => $name,
        ], [
            'slug' => $slug,
            'settings_json' => [],
        ]);
    }
}


