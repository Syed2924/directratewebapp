<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A hostel booking request and, once approved, the reservation and stay.
 * draft → pending → approved | returned | rejected → checked_in → checked_out; cancellable before check-in.
 * Only approved and checked-in bookings hold rooms. The guest's CNIC/passport is encrypted at rest.
 */
class HostelBooking extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'hostel';
    protected string $scopeColumn = 'hostel_id';

    protected $fillable = [
        'hostel_id', 'district_id', 'request_date', 'requested_by_email', 'guest_name', 'cnic_passport', 'mobile', 'organization',
        'referred_by', 'referrer_designation', 'purpose', 'purpose_details', 'check_in', 'check_out', 'guest_count', 'room_count', 'remarks',
    ];

    protected $hidden = ['cnic_passport'];

    protected $casts = [
        'cnic_passport' => 'encrypted',
        'request_date' => 'date',
        'check_in' => 'date',
        'check_out' => 'date',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'actual_check_in_at' => 'datetime',
        'actual_check_out_at' => 'datetime',
        'guest_count' => 'integer',
        'room_count' => 'integer',
        'hostel_id' => 'integer',
        'submitted_by' => 'integer',
        'created_by' => 'integer',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(HostelRoom::class, 'hostel_booking_rooms', 'hostel_booking_id', 'hostel_room_id')
            ->withPivot('assigned_by', 'assigned_at')->orderBy('room_number');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return config("hostel.booking_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function purposeLabel(): string
    {
        return config("hostel.purposes.{$this->purpose}", ucfirst($this->purpose));
    }

    public function referenceLabel(): string
    {
        return $this->booking_no;
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function holdsRooms(): bool
    {
        return in_array($this->status, config('hostel.holding_statuses'), true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned'], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function awaitingDecision(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'returned', 'approved'], true);
    }

    /** e.g. "XXXXX-XXXXXXX-3" — only the last character is shown. */
    public function maskedCnic(): string
    {
        $value = (string) $this->cnic_passport;

        return $value === '' ? '—' : preg_replace('/[A-Za-z0-9]/', 'X', substr($value, 0, -1)) . substr($value, -1);
    }
}
