<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchActiveForPos
{
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch');

        if ($branch instanceof Branch && ! $branch->is_active) {
            abort(403, __('operations.branch_deactivated_pos'));
        }

        return $next($request);
    }
}
