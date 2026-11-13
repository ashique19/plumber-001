<?php

namespace App\Http\Controllers\Admin;

use App\Business\BusinessContext;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Services\Pos\PosAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
  public function __invoke(Request $request, PosAnalyticsService $analytics): StreamedResponse
  {
    $validated = $request->validate([
      'from' => ['required', 'date'],
      'to' => ['required', 'date', 'after_or_equal:from'],
      'granularity' => ['nullable', 'in:day,week,month'],
      'branch' => ['nullable', 'integer'],
    ]);

    $business = BusinessContext::get() ?? BusinessSetting::singleton();

    $tz = config('app.timezone');
    $from = Carbon::parse($validated['from'], $tz)->startOfDay();
    $to = Carbon::parse($validated['to'], $tz)->endOfDay();
    $granularity = $validated['granularity'] ?? 'day';

    $branch = null;
    if (! empty($validated['branch'])) {
      $branch = Branch::query()
        ->whereKey($validated['branch'])
        ->firstOrFail();
    }

    $report = $analytics->report($business, $branch, $from, $to, $granularity);
    $filename = 'sales-analytics-'.$from->toDateString().'_'.$to->toDateString().'.csv';

    return response()->streamDownload(
      fn () => print ($analytics->toCsv($report)),
      $filename,
      ['Content-Type' => 'text/csv; charset=UTF-8'],
    );
  }
}
