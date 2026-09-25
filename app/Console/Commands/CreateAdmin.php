<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'gso:create-admin';

    protected $description = 'Create a system administrator using a hidden password prompt';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Full name')),
            'employee_number' => trim((string) $this->ask('Employee number (6 digits)')),
            'email' => Str::lower(trim((string) $this->ask('Email address'))),
            'password' => $this->secret('Password (at least 5 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', 'unique:users,employee_number'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(5), 'max:1024'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->employee_number = $data['employee_number'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = 'system_admin';
        $user->is_active = true;
        $user->save();

        Log::info('auth.admin_created', ['user_id' => $user->id, 'source' => 'console']);
        $this->info('System administrator created. Sign in at /signin.');

        return self::SUCCESS;
    }
}
