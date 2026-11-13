<?php

namespace App\Services\Operations;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Shift;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;

class BranchAdminService
{
    /**
     * @return list<string>
     */
    public function deletionBlockers(Branch $branch): array
    {
        $blockers = [];

        if (PosOrder::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            $blockers[] = 'pos_orders';
        }
        if (Invoice::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            $blockers[] = 'invoices';
        }
        if (Shift::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            $blockers[] = 'shifts';
        }
        if (StaffUser::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            $blockers[] = 'staff';
        }

        return $blockers;
    }

    public function canDelete(Branch $branch): bool
    {
        return $this->deletionBlockers($branch) === [];
    }

    public function delete(Branch $branch): void
    {
        if (! $this->canDelete($branch)) {
            throw new \InvalidArgumentException($this->userFacingDeletionMessage($branch));
        }

        DB::transaction(function () use ($branch): void {
            $branch->delete();
        });
    }

    public function userFacingDeletionMessage(Branch $branch): string
    {
        $keys = $this->deletionBlockers($branch);
        if ($keys === []) {
            return '';
        }

        $labels = array_map(
            fn (string $key) => __('operations.delete_blocker.'.$key),
            $keys,
        );

        return __('operations.delete_blocked', [
            'reasons' => implode(', ', $labels),
        ]);
    }

    public function setActive(Branch $branch, bool $active): Branch
    {
        $branch->forceFill(['is_active' => $active])->save();

        return $branch->fresh();
    }
}
