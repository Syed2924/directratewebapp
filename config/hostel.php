<?php

/*
|--------------------------------------------------------------------------
| Hostel Booking Management module
|--------------------------------------------------------------------------
|
| PROPOSED lists — purposes, room types and expense categories must be
| confirmed with the Directorate's booking policy and financial rules.
|
*/

return [

    // Central Budget & Planning head that funds hostel budgets.
    'budget_head' => 'hostel_operations',

    'room_types' => [
        'single' => ['Single Room', 1],
        'double' => ['Double Room', 2],
        'triple' => ['Triple Room', 3],
        'dormitory' => ['Dormitory', 8],
    ],

    'room_statuses' => [
        'available' => 'Available',
        'maintenance' => 'Under Maintenance',
        'inactive' => 'Inactive',
    ],

    'purposes' => [
        'official' => 'Official Visit',
        'training' => 'Training Program',
        'sports' => 'Sports Event',
        'education' => 'Educational Tour',
        'cultural' => 'Cultural Event',
        'seminar' => 'Seminar / Workshop',
        'other' => 'Other',
    ],

    'booking_statuses' => [
        'draft' => ['Draft', 'badge-draft'],
        'pending' => ['Pending', 'badge-review'],
        'returned' => ['Returned for Correction', 'badge-pending'],
        'approved' => ['Approved', 'badge-active'],
        'rejected' => ['Rejected', 'badge-inactive'],
        'checked_in' => ['Checked In', 'badge-assigned'],
        'checked_out' => ['Checked Out', 'badge-received'],
        'cancelled' => ['Cancelled', 'badge-inactive'],
    ],

    // Bookings that hold rooms: confirmed reservations and actual stays.
    'holding_statuses' => ['approved', 'checked_in'],

    'expense_categories' => [
        'utilities' => 'Utilities',
        'maintenance' => 'Repair & Maintenance',
        'cleaning' => 'Cleaning & Sanitation',
        'supplies' => 'Supplies & Linen',
        'furniture' => 'Furniture & Fixtures',
        'security' => 'Security Services',
        'other' => 'Other',
    ],

    'central_categories' => [
        'utilities' => 'services',
        'maintenance' => 'services',
        'cleaning' => 'services',
        'supplies' => 'materials',
        'furniture' => 'equipment',
        'security' => 'services',
        'other' => 'other',
    ],

    'expense_statuses' => [
        'pending' => ['Pending Approval', 'badge-review'],
        'approved' => ['Approved', 'badge-active'],
        'rejected' => ['Rejected', 'badge-inactive'],
        'partially_paid' => ['Partially Paid', 'badge-assigned'],
        'paid' => ['Paid', 'badge-active'],
    ],
];
