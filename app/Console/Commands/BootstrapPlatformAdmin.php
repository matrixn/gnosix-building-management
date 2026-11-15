<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapPlatformAdmin extends Command
{
    protected $signature = 'gnosix:admin {--reset-password : Apply ADMIN_PASSWORD to an existing administrator} {--revoke-passkeys : Revoke registered passkeys for this administrator}';
    protected $description = 'Initialize the platform administrator from ADMIN_EMAIL and ADMIN_PASSWORD.';

    public function handle(): int
    {
        // Read at command time, including after config:cache. Never cache the bootstrap password.
        $values = Dotenv::createArrayBacked(base_path())->safeLoad();
        $email = strtolower(trim((string) (getenv('ADMIN_EMAIL') ?: ($values['ADMIN_EMAIL'] ?? ''))));
        $password = (string) (getenv('ADMIN_PASSWORD') ?: ($values['ADMIN_PASSWORD'] ?? ''));
        $existing = PlatformAdmin::where('email', $email)->first();
        if ($existing && ! $this->option('reset-password') && ! $this->option('revoke-passkeys')) {
            $this->info('Administrator already exists; password and passkeys unchanged.');
            return self::SUCCESS;
        }
        $rules = ['email' => ['required', 'email:rfc', 'max:255']];
        if (! $existing || $this->option('reset-password')) {
            $rules['password'] = ['required', 'string', 'min:12', 'max:255'];
        }
        if (Validator::make(compact('email', 'password'), $rules)->fails()) {
            $this->error('Set a valid ADMIN_EMAIL and an ADMIN_PASSWORD of 12–255 characters. Values were not printed.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($existing, $email, $password) {
            $admin = $existing ?? new PlatformAdmin(['name' => 'Administrator platformă', 'email' => $email]);
            if (! $existing || $this->option('reset-password')) {
                $admin->password = $password;
            }
            if ($existing) {
                $admin->auth_version++;
            }
            $admin->save();
            if ($this->option('revoke-passkeys')) {
                $admin->passkeys()->delete();
            }
        });
        $this->info('Platform administrator initialized. Existing sessions invalidated when credentials were changed.');
        return self::SUCCESS;
    }
}
