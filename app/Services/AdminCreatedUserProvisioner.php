<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminCreatedAccountCredentialsNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminCreatedUserProvisioner
{
    /**
     * @param  array{name:string,email:string,profile_visibility?:string,password?:string|null,send_credentials?:bool,generate_password?:bool}  $data
     * @return array{user:User, plain_password:string, credentials_sent:bool}
     */
    public function create(array $data): array
    {
        $plainPassword = (bool) ($data['generate_password'] ?? false)
            ? $this->generatePassword()
            : (string) ($data['password'] ?? '');

        $user = User::create([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'password' => Hash::make($plainPassword),
            'profile_visibility' => $data['profile_visibility'] ?? 'public',
        ]);

        $credentialsSent = false;

        if ((bool) ($data['send_credentials'] ?? false)) {
            try {
                $user->notify(new AdminCreatedAccountCredentialsNotification($plainPassword));
                $credentialsSent = true;
            } catch (\Throwable $exception) {
                Log::warning('Admin-created account credentials notification could not be sent.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'user' => $user,
            'plain_password' => $plainPassword,
            'credentials_sent' => $credentialsSent,
        ];
    }

    private function generatePassword(): string
    {
        return Str::password(14, true, true, false, false);
    }
}
