<?php

namespace App\Support\Reports;

use App\Models\District;
use App\Models\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Validated report filters. The reporting period is always explicit: it
 * defaults to the selected financial year and can be narrowed by dates.
 */
class ReportFilters
{
    public ?FinancialYear $fy;
    public Carbon $from;
    public Carbon $to;
    public ?int $district;
    public ?string $status;
    public ?string $category;
    public ?int $center;
    public ?int $hostel;
    public ?string $payment;
    public ?string $q;
    public Carbon $month;

    public static function fromRequest(Request $request): self
    {
        $request->validate([
            'fy' => ['nullable', 'integer', 'exists:financial_years,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'district' => ['nullable', 'integer'],
            'center' => ['nullable', 'integer'],
            'hostel' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:40'],
            'payment' => ['nullable', 'in:unpaid,partially_paid,paid'],
            'q' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $f = new self();
        $f->fy = FinancialYear::fromRequest($request);
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : ($f->fy?->starts_on->copy() ?? now()->startOfYear());
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : ($f->fy?->ends_on->copy() ?? now()->endOfYear());
        [$f->from, $f->to] = $from->lte($to) ? [$from->startOfDay(), $to->endOfDay()] : [$to->startOfDay(), $from->endOfDay()];
        $f->district = $request->integer('district') ?: null;
        $f->center = $request->integer('center') ?: null;
        $f->hostel = $request->integer('hostel') ?: null;
        $f->status = $request->input('status') ?: null;
        $f->category = $request->input('category') ?: null;
        $f->payment = $request->input('payment') ?: null;
        $f->q = trim((string) $request->input('q')) ?: null;
        $f->month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth() : now()->startOfMonth();

        return $f;
    }

    public function period(): string
    {
        return $this->from->format('d M Y') . ' – ' . $this->to->format('d M Y') . ($this->fy ? " (FY {$this->fy->label})" : '');
    }

    public function districtName(): ?string
    {
        return $this->district ? District::find($this->district)?->name : null;
    }

    public function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }
}
