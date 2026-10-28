<?php

/*
|--------------------------------------------------------------------------
| Jawan Markaz Management module
|--------------------------------------------------------------------------
|
| PROPOSED lists — expense categories, payment methods and the funding head
| must be confirmed against the Directorate's financial rules before use.
|
*/

return [

    // Central Budget & Planning head that funds center budgets.
    'budget_head' => 'jawan_markaz',

    'expense_categories' => [
        'office_supplies' => 'Office Supplies',
        'utilities' => 'Utilities',
        'maintenance' => 'Repair & Maintenance',
        'equipment' => 'Equipment',
        'training' => 'Training & Programs',
        'events' => 'Events & Activities',
        'travel' => 'Travel & Conveyance',
        'other' => 'Other',
    ],

    // How each center category is recorded in the central expense register.
    'central_categories' => [
        'office_supplies' => 'materials',
        'utilities' => 'services',
        'maintenance' => 'services',
        'equipment' => 'equipment',
        'training' => 'honorarium',
        'events' => 'venue',
        'travel' => 'transport',
        'other' => 'other',
    ],

    'payment_methods' => [
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'online' => 'Online / IBFT',
    ],

    // Expense workflow statuses => [label, badge class].
    'expense_statuses' => [
        'draft' => ['Draft', 'badge-draft'],
        'submitted' => ['Submitted', 'badge-received'],
        'under_review' => ['Under Review', 'badge-review'],
        'returned' => ['Returned for Correction', 'badge-pending'],
        'approved' => ['Approved', 'badge-active'],
        'rejected' => ['Rejected', 'badge-inactive'],
        'partially_paid' => ['Partially Paid', 'badge-assigned'],
        'paid' => ['Paid', 'badge-active'],
    ],
];
