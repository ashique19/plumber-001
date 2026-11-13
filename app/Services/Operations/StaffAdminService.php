<?php

namespace App\Services\Operations;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class StaffAdminService
{
  private function teamId(BusinessSetting $business): int
  {
    return (int) $business->id;
  }

  /**
   * @param  list<string>  $roles
   */
  public function createUser(
    BusinessSetting $business,
    string $name,
    string $email,
    string $password,
    ?int $branchId,
    array $roles,
  ): StaffUser {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->teamId($business));

    if (StaffUser::query()->where('email', $email)->exists()) {
      throw ValidationException::withMessages(['email' => [__('operations.email_taken')]]);
    }

    $user = StaffUser::query()->create([
      'branch_id' => $branchId,
      'name' => $name,
      'email' => $email,
      'password' => Hash::make($password),
      'is_active' => true,
    ]);

    $this->syncRoles($business, $user, $roles);

    return $user;
  }

  /**
   * @param  list<string>  $roles
   */
  public function syncRoles(BusinessSetting $business, StaffUser $user, array $roles): void
  {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->teamId($business));
    $allowed = ['owner', 'manager', 'cashier', 'accountant'];
    $roles = array_values(array_intersect($roles, $allowed));

    if ($roles === []) {
      throw ValidationException::withMessages(['roles' => [__('operations.role_required')]]);
    }

    if ($user->hasRole('owner') && ! in_array('owner', $roles, true)) {
      $ownerCount = StaffUser::query()
        ->where('is_active', true)
        ->whereHas('roles', fn ($q) => $q->where('name', 'owner'))
        ->count();
      if ($ownerCount <= 1) {
        throw ValidationException::withMessages(['roles' => [__('operations.last_owner')]]);
      }
    }

    $user->syncRoles($roles);
  }

  public function setActive(StaffUser $user, bool $active): void
  {
    if (! $active && $user->hasRole('owner')) {
      $ownerCount = StaffUser::query()
        ->where('is_active', true)
        ->whereHas('roles', fn ($q) => $q->where('name', 'owner'))
        ->count();
      if ($ownerCount <= 1) {
        throw ValidationException::withMessages(['is_active' => [__('operations.last_owner')]]);
      }
    }

    $user->update(['is_active' => $active]);
  }
}
