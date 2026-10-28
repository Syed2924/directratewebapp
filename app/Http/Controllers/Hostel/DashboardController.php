<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\FinancialYear;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelBudget;
use App\Support\HostelAvailability;
use Illuminate\Http\Request;

/**
 * Booking summary (requests ≠ reservations ≠ occupancy) and financial summary,
 * for a selected hostel and financial year.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('hostel.view');
        $user = $request->user();
        $fy = FinancialYear::fromRequest($request);
        $hostels = Hostel::withinCoverageOf($user)->orderBy('name')->get();
        $selected = $request->filled('hostel') ? $hostels->where('id', (int) $request->input('hostel')) : $hostels;

        $bookings = HostelBooking::visibleTo($user)->whereIn('hostel_id', $selected->pluck('id'))
            ->when($fy, fn ($q) => $q->whereBetween('check_in', [$fy->starts_on, $fy->ends_on]));
        $counts = (clone $bookings)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        $months = [];
        if ($fy) {
            for ($m = $fy->starts_on->copy()->startOfMonth(); $m->lte($fy->ends_on); $m->addMonth()) {
                $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'year' => $m->format('Y'), 'requested' => 0, 'confirmed' => 0];
            }
            (clone $bookings)->where('status', '!=', 'draft')->get(['check_in', 'status'])->each(function ($b) use (&$months) {
                $k = $b->check_in->format('Y-m');
                if (isset($months[$k])) {
                    $months[$k]['requested']++;
                    if (in_array($b->status, ['approved', 'checked_in', 'checked_out'], true)) {
                        $months[$k]['confirmed']++;
                    }
                }
            });
        }

        $finance = ['original' => 0, 'revisions' => 0, 'allocated' => 0, 'paid' => 0, 'unpaid' => 0, 'remaining' => 0, 'available' => 0, 'pending' => 0];
        if ($fy) {
            HostelBudget::visibleTo($user)->where('financial_year_id', $fy->id)->where('status', 'approved')->whereIn('hostel_id', $selected->pluck('id'))->get()
                ->each(function ($b) use (&$finance) {
                    foreach ($b->figures() as $k => $v) {
                        if (isset($finance[$k])) {
                            $finance[$k] += $v;
                        }
                    }
                });
        }

        return view('hostel.dashboard', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'hostels' => $hostels,
            'counts' => $counts,
            'today' => HostelAvailability::today($selected),
            'months' => $months,
            'finance' => $finance,
            'recent' => (clone $bookings)->with('hostel')->where('status', '!=', 'draft')->latest('id')->limit(7)->get(),
        ]);
    }
}
