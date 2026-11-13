<?php

namespace App\Services\Auth;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Notifications\StaffResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffPasswordReset
{
  public function tokenStorageKey(string $email): string
  {
    return 'staff:'.$email;
  }

  public function sendResetLink(BusinessSetting $business, string $email): bool
  {
    $user = StaffUser::query()
      ->where('email', $email)
      ->first();

    if ($user === null || ! $user->is_active) {
      return true;
    }

    $token = Str::random(64);
    $key = $this->tokenStorageKey($email);

    DB::table($this->table())->updateOrInsert(
      ['email' => $key],
      [
        'token' => Hash::make($token),
        'created_at' => now(),
      ],
    );

    $user->notify(new StaffResetPasswordNotification($business, $token));

    return true;
  }

  public function resetPassword(BusinessSetting $business, string $email, string $token, string $password): bool
  {
    if (! $this->tokenIsValid($email, $token)) {
      return false;
    }

    $user = StaffUser::query()
      ->where('email', $email)
      ->first();

    if ($user === null || ! $user->is_active) {
      return false;
    }

    $user->forceFill(['password' => $password])->save();

    DB::table($this->table())->where('email', $this->tokenStorageKey($email))->delete();

    return true;
  }

  public function tokenIsValid(string $email, string $token): bool
  {
    $record = DB::table($this->table())
      ->where('email', $this->tokenStorageKey($email))
      ->first();

    if ($record === null) {
      return false;
    }

    if (! Hash::check($token, $record->token)) {
      return false;
    }

    $created = Carbon::parse($record->created_at);
    $expiry = (int) config('auth.passwords.tenant_users.expire', 60);

    return $created->addMinutes($expiry)->isFuture();
  }

  protected function table(): string
  {
    return (string) config('auth.passwords.tenant_users.table', 'password_reset_tokens');
  }
}
