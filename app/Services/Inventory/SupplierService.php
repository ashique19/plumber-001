<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupplierService
{
    /**
     * @return list<string>
     */
    public static function attributeKeys(): array
    {
        return [
            'name',
            'name_ar',
            'contact_person',
            'phone',
            'email',
            'vat_number',
            'cr_number',
            'address',
            'city',
            'notes',
            'is_active',
        ];
    }

    public function queryForBranch(int $branchId): Builder
    {
        return Supplier::query()
            ->where('branch_id', $branchId)
            ->orderBy('name');
    }

    /**
     * @return Collection<int, Supplier>
     */
    public function search(int $branchId, string $term, int $limit = 12): Collection
    {
        $term = trim($term);
        $query = $this->queryForBranch($branchId)->where('is_active', true);

        if ($term === '') {
            return $query->limit($limit)->get();
        }

        $like = '%'.$term.'%';

        return $query
            ->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('vat_number', 'like', $like);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Branch $branch, array $data): Supplier
    {
        return Supplier::query()->create($this->normalize($branch->id, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->fill($this->normalize($supplier->branch_id, $data, existing: $supplier));
        $supplier->save();

        return $supplier->fresh();
    }

    public function deleteIfUnused(Supplier $supplier): bool
    {
        if ($supplier->purchases()->exists()) {
            return false;
        }

        $supplier->delete();

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(?int $branchId, array $data, ?Supplier $existing = null): array
    {
        $active = $data['is_active'] ?? ($existing?->is_active ?? true);

        return [
            'branch_id' => $branchId,
            'name' => trim((string) ($data['name'] ?? $existing?->name ?? '')),
            'name_ar' => self::nullableString($data['name_ar'] ?? $existing?->name_ar),
            'contact_person' => self::nullableString($data['contact_person'] ?? $existing?->contact_person),
            'phone' => self::nullableString($data['phone'] ?? $existing?->phone),
            'email' => self::nullableString($data['email'] ?? $existing?->email),
            'vat_number' => self::nullableString($data['vat_number'] ?? $existing?->vat_number),
            'cr_number' => self::nullableString($data['cr_number'] ?? $existing?->cr_number),
            'address' => self::nullableString($data['address'] ?? $existing?->address),
            'city' => self::nullableString($data['city'] ?? $existing?->city),
            'notes' => self::nullableString($data['notes'] ?? $existing?->notes),
            'is_active' => filter_var($active, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}