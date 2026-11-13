<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Business\BusinessContext;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\Auth\StaffPasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
  public function create(Request $request, string $token): View
  {
    $business = BusinessContext::get() ?? BusinessSetting::singleton();

    return view('admin.auth.reset-password', [
      'business' => $business,
      'token' => $token,
      'email' => $request->string('email')->toString(),
    ]);
  }

  public function store(Request $request, StaffPasswordReset $resets): RedirectResponse
  {
    $validated = $request->validate([
      'token' => ['required', 'string'],
      'email' => ['required', 'email'],
      'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $business = BusinessContext::get() ?? BusinessSetting::singleton();

    if (! $resets->resetPassword(
      $business,
      $validated['email'],
      $validated['token'],
      $validated['password'],
    )) {
      return back()
        ->withInput($request->only('email'))
        ->withErrors(['email' => __('auth.token_invalid')]);
    }

    return redirect()
      ->route('login')
      ->with('status', __('auth.password_reset'));
  }
}
