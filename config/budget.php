<?php

/*
|--------------------------------------------------------------------------
| Budget & Planning module
|--------------------------------------------------------------------------
|
| PROPOSED lookup lists. Budget heads, funding sources and categories are
| placeholders and must be replaced with the Directorate's official
| classifications before the module is used for official transactions.
|
*/

return [

    'funding_sources' => [
        'government_grant' => 'Government Grant',
        'adp' => 'Development (ADP)',
        'current' => 'Current / Non-Development',
        'donor' => 'Donor / Partner Funding',
        'other' => 'Other',
    ],

    'budget_heads' => [
        'youth_development' => 'Youth Development Programs',
        'events' => 'Events & Seminars',
        'sports' => 'Sports Activities',
        'training' => 'Training & Capacity Building',
        'administration' => 'Administration & Office',
        'jawan_markaz' => 'Jawan Markaz Operations',
        'hostel_operations' => 'Hostel Operations',
    ],

    // Expense categories for financial transactions (also used by activity expenses).
    'expense_categories' => [
        'venue' => 'Venue',
        'refreshments' => 'Refreshments',
        'transport' => 'Transport',
        'materials' => 'Printing & Materials',
        'honorarium' => 'Honorarium / Resource Persons',
        'prizes' => 'Prizes & Certificates',
        'equipment' => 'Equipment',
        'services' => 'Services & Utilities',
        'other' => 'Other',
    ],

    // Which budget head funds each activity category (used to suggest an allocation on approval).
    'category_heads' => [
        'sports' => 'sports',
        'training' => 'training',
        'workshop' => 'training',
        'career' => 'youth_development',
        'community' => 'youth_development',
        'health' => 'youth_development',
    ],
    'default_activity_head' => 'events',
    // Heads that may never fund an activity (office running costs).
    'non_activity_heads' => ['administration', 'jawan_markaz', 'hostel_operations'],

    'petty_cash_categories' => [
        'office_supplies' => 'Office Supplies',
        'stationery' => 'Stationery',
        'printing' => 'Printing & Photocopy',
        'conveyance' => 'Local Conveyance',
        'refreshments' => 'Refreshments',
        'postage' => 'Postage & Courier',
        'repairs' => 'Minor Repairs',
        'other' => 'Other',
    ],

    'quarters' => [
        1 => 'Q1 (Jul – Sep)',
        2 => 'Q2 (Oct – Dec)',
        3 => 'Q3 (Jan – Mar)',
        4 => 'Q4 (Apr – Jun)',
    ],

    // Every workflow status used in the module => [label, badge css class].
    'statuses' => [
        'draft' => ['Draft', 'badge-draft'],
        'submitted' => ['Submitted', 'badge-review'],
        'pending' => ['Pending', 'badge-review'],
        'approved' => ['Approved', 'badge-active'],
        'verified' => ['Verified', 'badge-active'],
        'returned' => ['Returned', 'badge-pending'],
        'rejected' => ['Rejected', 'badge-inactive'],
        'open' => ['Open', 'badge-assigned'],
        'closed' => ['Closed', 'badge-draft'],
        'cancelled' => ['Cancelled', 'badge-inactive'],
        'active' => ['Active', 'badge-active'],
        'balanced' => ['Balanced', 'badge-active'],
        'difference' => ['Difference Found', 'badge-pending'],
    ],

    'document_kinds' => [
        'budget' => 'Budget Document',
        'approval' => 'Approval / Notification',
        'receipt' => 'Receipt / Invoice',
        'supporting' => 'Supporting Document',
        'invoice' => 'Invoice',
        'payment_evidence' => 'Payment Evidence',
        'referral' => 'Referral Letter',
    ],

    // Chart colours: validated categorical slots 1–3 + neutral remainder (dataviz reference palette).
    'chart' => [
        'spent' => '#2a78d6',
        'committed' => '#eb6834',
        'available' => '#1baf7a',
        'unallocated' => '#d5dbe3',
        'planned' => '#2a78d6',
        'actual' => '#eb6834',
    ],
];
