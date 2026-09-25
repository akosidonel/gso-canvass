<?php

namespace App\Http\Controllers;

use App\Models\PriceRecord;
use App\Services\PriceMonitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PriceMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:200']])['search'] ?? '';

        return view('pages.price-monitoring.index', [
            'records' => PriceMonitoring::listing($search), 'search' => $search,
            'fields' => PriceRecord::FIELDS, 'title' => __('Price Monitoring Canvass'),
        ]);
    }

    public function create()
    {
        return $this->form();
    }

    public function edit(int $id)
    {
        return $this->form(PriceMonitoring::find($id));
    }

    public function store(Request $request)
    {
        PriceMonitoring::save($this->validated($request), $request->user());

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

        return redirect()->route('price-monitoring.index')->with('status', __('Record deleted.'));
    }

    private function form(?PriceRecord $record = null)
    {
        return view('pages.price-monitoring.form', [
            'record' => $record, 'fields' => PriceRecord::FIELDS,
            'title' => $record ? __('Edit canvass record') : __('Paste Excel Data'),
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
        ])->validate()['rows'];
    }
}
