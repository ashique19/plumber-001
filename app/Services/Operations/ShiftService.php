<?php

namespace App\Services\Operations;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\StaffUser;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function openShift(Branch $branch, StaffUser $user, float $openingCashFloat): Shift
    {
        if ($this->openShiftForBranch($branch) !== null) {
            throw ValidationException::withMessages([
                'openingCashFloat' => [__('operations.shift_already_open')],
            ]);
        }

        return Shift::query()->create([
            'branch_id' => $branch->id,
            'staff_user_id' => $user->id,
            'started_at' => Carbon::now(),
            'opening_cash_float' => max(0, round($openingCashFloat, 2)),
        ]);
    }

    public function closeShift(Shift $shift, float $closingCashCounted, ?string $notes = null): Shift
    {
        if ($shift->ended_at !== null) {
            throw ValidationException::withMessages([
                'closingCashCounted' => [__('operations.shift_already_closed')],
            ]);
        }

        $shift->forceFill([
            'ended_at' => Carbon::now(),
            'closing_cash_counted' => max(0, round($closingCashCounted, 2)),
            'close_notes' => $notes,
        ])->save();

        return $shift->fresh();
    }

    public function openShiftForBranch(Branch $branch): ?Shift
    {
        return Shift::query()
            ->where('branch_id', $branch->id)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();
    }
}
