<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Business\BusinessContext;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\TenantLandingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        $business = BusinessSetting::query()->find(1) ?? BusinessSetting::query()->first();

        return view('admin.auth.login', ['business' => $business]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = StaffUser::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('pos.login_failed'),
            ]);
        }

        Auth::guard('staff')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $business = BusinessSetting::query()->find(1) ?? BusinessSetting::query()->firstOrFail();
        BusinessContext::set($business);

        return redirect()->intended(TenantLandingRoute::forUser($business, $user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
