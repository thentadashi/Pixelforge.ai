<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user(), 'csrf' => csrf_token()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        if ($request->user()->invitation_token) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Please activate your account using your invitation.']);
        }
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        try {
            Password::sendResetLink($data);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'If an account exists, a reset link has been sent.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Password reset. You can sign in now.']);
    }

    public function invitation(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|size:64', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $user = DB::transaction(function () use ($data) {
            $user = User::where('invitation_token', hash('sha256', $data['token']))->lockForUpdate()->first();
            if (! $user || ! $user->invitation_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['token' => 'This invitation has expired or has already been used. Ask PixelForge for a new invitation.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'invitation_token' => null, 'invitation_expires_at' => null, 'email_verified_at' => now()])->save();

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return $this->me($request);
    }
}
