<?php

namespace App\Http\Controllers;

use App\Models\PriceArchiveBatch;
use App\Models\PriceRecord;
use App\Services\PriceArchives;
use Illuminate\Http\Request;

class PriceArchiveController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->validate(['year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year - 1)]])['year'] ?? null;

        return view('pages.price-monitoring.archives', [
            'title' => __('Archive Management'), 'year' => $year,
            'count' => $year ? PriceArchives::eligible((int) $year)->count() : null,
            'years' => PriceRecord::whereNull('archived_at')->whereNotNull('created_at')
                ->where('created_at', '<', now()->year.'-01-01')
                ->selectRaw('DISTINCT SUBSTR(created_at, 1, 4) AS year')->orderByDesc('year')->pluck('year'),
            'batches' => PriceArchiveBatch::latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year - 1)],
            'expected_count' => ['required', 'integer', 'min:1'],
            'confirmed' => ['accepted'],
        ]);
        PriceArchives::archive((int) $data['year'], (int) $data['expected_count'], $request->user());

        return back()->with('status', __('Archive operation queued.'));
    }

    public function restore(Request $request, PriceArchiveBatch $batch)
    {
        $request->validate(['confirmed' => ['accepted']]);
        PriceArchives::restore($batch, $request->user());

        return back()->with('status', __('Restore operation queued.'));
    }

    public function retry(Request $request, PriceArchiveBatch $batch)
    {
        PriceArchives::retry($batch, $request->user());

        return back()->with('status', __('Archive operation queued.'));
    }
}
