<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class StaffUser extends Authenticatable
{
  use HasRoles, Notifiable;

  protected $table = 'staff_users';

  protected $guard_name = 'staff';

  protected $fillable = [
    'branch_id',
    'name',
    'email',
    'password',
    'is_active',
  ];

  protected $hidden = [
    'password',
    'remember_token',
  ];

  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
      'is_active' => 'boolean',
    ];
  }

  public function branch(): BelongsTo
  {
    return $this->belongsTo(Branch::class);
  }
}
