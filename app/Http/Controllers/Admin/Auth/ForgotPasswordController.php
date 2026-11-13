<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Business\BusinessContext;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\Auth\StaffPasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
  public function create(): View
  {
    $business = BusinessContext::get() ?? BusinessSetting::singleton();

    return view('admin.auth.forgot-password', ['business' => $business]);
  }

  public function store(Request $request, StaffPasswordReset $resets): RedirectResponse
  {
    $request->validate([
      'email' => ['required', 'email'],
    ]);

    $business = BusinessContext::get() ?? BusinessSetting::singleton();
    $resets->sendResetLink($business, $request->string('email')->toString());

    return back()->with('status', __('auth.reset_link_sent'));
  }
}
