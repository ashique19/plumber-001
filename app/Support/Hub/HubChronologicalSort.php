<?php

namespace App\Support\Hub;

use Illuminate\Database\Eloquent\Builder;

final class HubChronologicalSort
{
    /**
     * Latest-first ordering for hub document lists (invoices, quotations, payments picklists).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function applyLatestFirst(Builder $query, string $dateColumn, bool $descending = true): void
    {
        if ($descending) {
            $query->orderByDesc($dateColumn)->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy($dateColumn)->orderBy('created_at')->orderBy('id');
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function applyTieBreakers(Builder $query, bool $descending = true): void
    {
        if ($descending) {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy('created_at')->orderBy('id');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sortRowsByIssuedCreatedId(array $rows, bool $descending = true): array
    {
        usort($rows, static function (array $a, array $b) use ($descending): int {
            $issuedA = $a['issued_at'] ?? null;
            $issuedB = $b['issued_at'] ?? null;
            $createdA = $a['invoice']->created_at ?? null;
            $createdB = $b['invoice']->created_at ?? null;
            $idA = (int) ($a['invoice']->id ?? 0);
            $idB = (int) ($b['invoice']->id ?? 0);

            $cmp = self::compareNullableTimes($issuedA, $issuedB, $descending);
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = self::compareNullableTimes($createdA, $createdB, $descending);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $descending ? $idB <=> $idA : $idA <=> $idB;
        });

        return $rows;
    }

    private static function compareNullableTimes(mixed $a, mixed $b, bool $descending): int
    {
        $tsA = $a ? strtotime((string) $a) : null;
        $tsB = $b ? strtotime((string) $b) : null;

        if ($tsA === null && $tsB === null) {
            return 0;
        }
        if ($tsA === null) {
            return $descending ? 1 : -1;
        }
        if ($tsB === null) {
            return $descending ? -1 : 1;
        }

        $cmp = $tsA <=> $tsB;

        return $descending ? -$cmp : $cmp;
    }
}
