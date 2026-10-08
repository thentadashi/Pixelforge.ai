<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'pixelforge:admin {email?}';

    protected $description = 'Create the initial PixelForge administrator without hardcoded credentials';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Administrator email');
        $name = $this->ask('Full name', 'Thenmarck V. Dulos');
        $password = $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(compact('email', 'name', 'password'), ['email' => 'required|email|unique:users', 'name' => 'required|max:255', 'password' => 'required|min:12']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }
        $user = new User(['email' => $email, 'name' => $name, 'password' => Hash::make($password)]);
        $user->role = 'admin';
        $user->email_verified_at = now();
        $user->save();
        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
