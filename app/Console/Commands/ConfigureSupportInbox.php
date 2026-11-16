<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class ConfigureSupportInbox extends Command
{
    protected $signature = 'airmius:configure-support {--safety-user= : Existing account email} {--dry-run : Check without changing roles}';

    protected $description = 'Assign the explicit safety support role to one existing account after deployment';

    public function handle(): int
    {
        $email = trim((string) $this->option('safety-user'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide an existing account email using --safety-user.');

            return self::FAILURE;
        }
        $users = User::where('email', $email)->get();
        if ($users->count() !== 1) {
            $this->error('Expected exactly one existing account; no permissions changed.');

            return self::FAILURE;
        }
        $role = Role::where('name', 'safety_support')->where('guard_name', 'web')->first();
        if (! $role || $role->permissions()->whereIn('name', ['support.tickets', 'support.safety'])->count() !== 2) {
            $this->error('Safety support role is missing or incomplete. Run the support migration first.');

            return self::FAILURE;
        }
        if (! $this->option('dry-run')) {
            $users->first()->assignRole($role);
        }
        $this->info($this->option('dry-run') ? 'Account and safety role validated; no changes made.' : 'Safety role assigned; existing roles preserved.');

        return self::SUCCESS;
    }
}
