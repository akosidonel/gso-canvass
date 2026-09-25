<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'employee_number' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
            'password' => ['required', 'string', 'max:1024'],
        ], [
            'employee_number.regex' => __('Employee number must be exactly 6 digits, with no letters or special characters.'),
        ]);
        $key = 'login:'.hash('sha256', $credentials['employee_number'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'employee_number' => __('Too many sign-in attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, 60);

        if (! Auth::attemptWhen($credentials, fn (User $user) => $user->canAccessSystem())) {
            Log::notice('auth.login_failed', ['ip' => $request->ip()]);
            throw ValidationException::withMessages([
                'employee_number' => __('The provided credentials could not be verified.'),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Log::info('auth.login', ['user_id' => Auth::id(), 'ip' => $request->ip()]);

        return redirect()->intended(route('price-monitoring.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Log::info('auth.logout', ['user_id' => Auth::id(), 'ip' => $request->ip()]);
        UserAccounts::offline($request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
