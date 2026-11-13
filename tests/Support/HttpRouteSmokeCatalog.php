<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosOrder;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

/**
 * GET routes from the application router with parameter bindings for smoke tests.
 */
final class HttpRouteSmokeCatalog
{
    /**
     * @return list<array{0: string, 1: ?string, 2: array<string, mixed>}>
     */
    public static function getRoutes(Branch $branch, ?Invoice $invoice, ?PosOrder $order): array
    {
        $routes = [];
        $skipNames = [
            'livewire.preview-file',
            'storage.local',
        ];

        /** @var IlluminateRoute $route */
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();
            if ($name !== null && in_array($name, $skipNames, true)) {
                continue;
            }

            $parameters = self::parametersFor($route, $branch, $invoice, $order);
            if ($parameters === null) {
                continue;
            }

            $uri = $route->uri();
            if ($name !== null) {
                try {
                    $url = route($name, $parameters, false);
                } catch (\Throwable) {
                    $url = '/'.ltrim($uri, '/');
                }
            } else {
                $url = '/'.ltrim(self::fillUri($uri, $parameters), '/');
            }

            $routes[] = [$url, $name, $parameters];
        }

        usort($routes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $routes;
    }

    /**
     * @return array<string, mixed>|null null when route cannot be bound safely
     */
    private static function parametersFor(
        IlluminateRoute $route,
        Branch $branch,
        ?Invoice $invoice,
        ?PosOrder $order,
    ): ?array {
        $name = $route->getName();
        $uri = $route->uri();

        if ($name === 'password.reset') {
            return ['token' => 'install-sql-smoke-token'];
        }

        if ($name === 'admin.reports.export') {
            $today = now()->toDateString();

            return [
                'from' => now()->subDays(7)->toDateString(),
                'to' => $today,
            ];
        }

        if (str_contains($uri, '{branch}')) {
            if ($branch->getKey() === null) {
                return null;
            }

            $params = ['branch' => $branch];
            if (str_contains($uri, '{order}') && $order !== null) {
                $params['order'] = $order;
            }

            return $params;
        }

        if (str_contains($uri, '{invoice}')) {
            return $invoice !== null ? ['invoice' => $invoice] : null;
        }

        if (str_contains($uri, '{order}')) {
            return $order !== null ? ['order' => $order] : null;
        }

        if (str_contains($uri, '{mode}')) {
            return ['mode' => 'simplified'];
        }

        if (str_contains($uri, '{filename}')) {
            return null;
        }

        if (str_contains($uri, '{path}')) {
            return null;
        }

        if (str_contains($uri, '{token}')) {
            return ['token' => 'install-sql-smoke-token'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function fillUri(string $uri, array $parameters): string
    {
        $filled = $uri;
        foreach ($parameters as $key => $value) {
            if (is_object($value) && method_exists($value, 'getRouteKey')) {
                $value = $value->getRouteKey();
            }
            $filled = str_replace('{'.$key.'}', (string) $value, $filled);
        }

        return $filled;
    }
}
