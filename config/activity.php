<?php

/*
|--------------------------------------------------------------------------
| Activity Management module
|--------------------------------------------------------------------------
|
| Lookup lists and workflow labels for activity proposals and activities.
| Categories and the tracking-number prefix are a proposal and can be
| changed here without code changes.
|
*/

return [

    // Tracking numbers look like YA-2026-0001 (prefix-year-sequence).
    'tracking_prefix' => 'YA',

    'categories' => [
        'seminar' => 'Seminar',
        'workshop' => 'Workshop',
        'training' => 'Leadership / Skills Training',
        'sports' => 'Sports Event',
        'cultural' => 'Cultural Event',
        'health' => 'Health Awareness',
        'community' => 'Community Service',
        'career' => 'Career Guidance',
        'other' => 'Other',
    ],

    // Proposal workflow statuses => [label, badge css class].
    'proposal_statuses' => [
        'draft' => ['Draft', 'badge-draft'],
        'received' => ['Received', 'badge-received'],
        'incomplete' => ['Information Missing', 'badge-pending'],
        'under_review' => ['Under Review', 'badge-review'],
        'returned' => ['Returned for Correction', 'badge-pending'],
        'recommended' => ['Recommended', 'badge-assigned'],
        'approved' => ['Approved', 'badge-active'],
        'rejected' => ['Rejected', 'badge-inactive'],
    ],

    // Activity (implementation) statuses.
    'activity_statuses' => [
        'in_progress' => ['In Progress', 'badge-review'],
        'completion_submitted' => ['Completion Submitted', 'badge-assigned'],
        'returned' => ['Returned for Correction', 'badge-pending'],
        'completed' => ['Completed', 'badge-active'],
    ],

    'document_kinds' => [
        'proposal' => 'Scanned Proposal',
        'attachment' => 'Attachment',
        'picture' => 'Picture',
        'attendance' => 'Attendance Sheet',
        'receipt' => 'Receipt / Invoice',
        'report' => 'Completion Report',
    ],

    // Upload rules (kilobytes / extensions).
    'upload_max_kb' => 10240,
    'upload_mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
];
