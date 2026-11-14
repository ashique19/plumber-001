<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves which branches a staff member may use and their preferred branch (session, assignment, sole branch).
 */
final class StaffBranchContext
{
    public const SESSION_KEY = 'staff.last_branch_id';

    /**
     * @return Builder<Branch>
     */
    public static function accessibleQuery(?StaffUser $user = null): Builder
    {
        $user ??= auth('staff')->user();

        $query = Branch::query()
            ->withoutGlobalScopes()
            ->where('is_active', true);

        if ($user?->branch_id !== null) {
            $assigned = Branch::query()->withoutGlobalScopes()->find($user->branch_id);
            if ($assigned !== null && $assigned->is_active) {
                return $query->whereKey($user->branch_id)->orderBy('name');
            }
        }

        return $query->orderBy('name');
    }

    /**
     * @return Collection<int, Branch>
     */
    public static function accessibleBranches(?StaffUser $user = null): Collection
    {
        return self::accessibleQuery($user)->get();
    }

    public static function selectorVisible(?StaffUser $user = null): bool
    {
        return self::accessibleBranches($user)->count() > 1;
    }

    public static function remember(Branch|int $branch): void
    {
        $id = $branch instanceof Branch ? (int) $branch->id : $branch;
        session([self::SESSION_KEY => $id]);
    }

    public static function resolve(?StaffUser $user = null, ?int $preferredId = null): ?Branch
    {
        $user ??= auth('staff')->user();
        $branches = self::accessibleBranches($user);
        if ($branches->isEmpty()) {
            return null;
        }

        if ($preferredId !== null && $branches->contains('id', $preferredId)) {
            return $branches->firstWhere('id', $preferredId);
        }

        $lastId = session(self::SESSION_KEY);
        if (is_numeric($lastId) && $branches->contains('id', (int) $lastId)) {
            return $branches->firstWhere('id', (int) $lastId);
        }

        if ($user?->branch_id !== null && $branches->contains('id', (int) $user->branch_id)) {
            return $branches->firstWhere('id', (int) $user->branch_id);
        }

        return $branches->first();
    }

    public static function posHomeUrl(?StaffUser $user = null): string
    {
        $user ??= auth('staff')->user();
        if (self::selectorVisible($user)) {
            return route('admin.pos.home');
        }

        $branch = self::resolve($user);

        return $branch !== null
            ? route('admin.pos.terminal', $branch)
            : route('admin.pos.home');
    }
}