<?php

namespace App\Http\Controllers;

use App\Models\PriceRecord;
use App\Services\PriceMonitoring;
use App\Services\PriceMonitoringExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PriceMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);
        $search = $filters['search'] ?? '';
        $category = $filters['category'] ?? '';

        return view('pages.price-monitoring.index', [
            'records' => PriceMonitoring::listing($search, $category), 'search' => $search,
            'category' => $category,
            'categories' => collect(PriceRecord::CATEGORIES)
                ->merge(PriceRecord::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'))
                ->filter()->unique(fn ($category) => mb_strtolower($category))->values(),
            'fields' => PriceRecord::FIELDS, 'title' => __('Price Monitoring'),
        ]);
    }

    public function create()
    {
        return view('pages.price-monitoring.paste', [
            'fields' => PriceRecord::FIELDS, 'title' => __('Paste Excel Data'),
        ]);
    }

    public function preparePreview(Request $request)
    {
        $rules = [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'rows.*' => ['array:'.implode(',', array_keys(PriceRecord::FIELDS))],
        ];
        foreach (PriceRecord::FIELDS as $field => $label) {
            $rules['rows.*.'.$field] = ['nullable', 'string', 'max:10000'];
        }
        // Preview accepts incomplete rows; full validation happens when saving.
        $rows = $request->validate($rules)['rows'];
        $request->session()->put('price_monitoring_preview', $rows);

        return response()->json(['redirect' => route('price-monitoring.preview')]);
    }

    public function preview(Request $request)
    {
        $rows = $request->session()->get('price_monitoring_preview', []);
        if (! $rows) {
            return redirect()->route('price-monitoring.create');
        }

        return $this->form(null, $rows);
    }

    public function export(PriceMonitoringExport $export)
    {
        return response()->download($export->create(), 'price-monitoring-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function edit(int $id)
    {
        return $this->form(PriceMonitoring::find($id));
    }

    public function store(Request $request)
    {
        PriceMonitoring::save($this->validated($request), $request->user());
        $request->session()->forget('price_monitoring_preview');

        $request->session()->flash('status', __('Canvass records saved.'));

        return response()->json(['redirect' => route('price-monitoring.index')]);
    }

    public function update(Request $request, int $id)
    {
        PriceMonitoring::save($this->validated($request, true), $request->user(), $id);

        $request->session()->flash('status', __('Canvass records saved.'));

        return response()->json(['redirect' => route('price-monitoring.index')]);
    }

    public function destroy(Request $request, int $id)
    {
        PriceMonitoring::delete($id, $request->user());

        return redirect()->route('price-monitoring.index')
            ->with('status', __('Record deleted.'))
            ->with('price_record_deleted', true);
    }

    private function form(?PriceRecord $record = null, array $rows = [])
    {
        return view('pages.price-monitoring.form', [
            'record' => $record, 'fields' => PriceRecord::FIELDS,
            'rows' => $rows,
            'categories' => collect(PriceRecord::CATEGORIES)
                ->merge(PriceRecord::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'))
                ->filter()->unique(fn ($category) => mb_strtolower($category))->values(),
            'title' => $record ? __('Edit canvass record') : __('Preview canvass records'),
        ]);
    }

    private function validated(Request $request, bool $editing = false): array
    {
        $rows = $request->input('rows');
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row as &$value) {
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                }
                unset($value);
            }
            unset($row);
        }

        return Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:'.($editing ? 1 : 500)],
            'rows.*' => ['required', 'array'],
            'rows.*.qty' => ['required', 'regex:/\A[0-9]+(?:\.[0-9]{1,3})?\z/', 'numeric', 'gt:0', 'max:99999.999'],
            'rows.*.unit' => ['required', 'string', 'max:50'],
            'rows.*.brand_model' => ['nullable', 'string', 'max:255'],
            'rows.*.particulars' => ['required', 'string', 'max:10000'],
            'rows.*.amount' => ['required', 'regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/', 'numeric', 'min:0', 'max:9999999.99'],
            'rows.*.department' => ['required', 'string', 'max:255'],
            'rows.*.control_number' => ['required', 'string', 'max:100'],
            'rows.*.store' => ['nullable', 'string', 'max:1000'],
            'rows.*.canvasser' => ['required', 'string', 'max:255'],
            'rows.*.category' => ['nullable', 'string', 'max:255'],
        ])->validate()['rows'];
    }
}
