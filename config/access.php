<?php

/*
|--------------------------------------------------------------------------
| Access-control catalogue
|--------------------------------------------------------------------------
|
| Single source of truth for modules, permissions, proposed roles and the
| DRAFT permission matrix (spec §4, §5, §9). Role names and the default
| matrix are a proposal and must be confirmed by the Directorate before
| deployment. Run `php artisan db:seed --class=AccessControlSeeder` after
| changing this file; the seeder is idempotent and never removes
| permissions an administrator has granted through the UI unless
| `--reset-matrix` is used (see AccessControlSeeder).
|
*/

return [

    // Business modules shown as columns of the role-permission matrix.
    'modules' => [
        'activity' => 'Activity Management',
        'budget' => 'Budget & Planning',
        'jawan_markaz' => 'Jawan Markaz Management',
        'hostel' => 'Hostel Booking Management',
        'reports' => 'Report Management',
    ],

    // Workflow actions shown as rows of the matrix.
    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'submit' => 'Submit',
        'review' => 'Review',
        'approve' => 'Approve/Reject',
        'record_payment' => 'Record Payment',
        'export' => 'Export',
    ],

    // Which actions apply to which module (others render as "—").
    'module_actions' => [
        'activity' => ['view', 'create', 'edit', 'submit', 'review', 'approve', 'export'],
        'budget' => ['view', 'create', 'edit', 'submit', 'review', 'approve', 'record_payment', 'export'],
        'jawan_markaz' => ['view', 'create', 'edit', 'submit', 'review', 'approve', 'record_payment', 'export'],
        'hostel' => ['view', 'create', 'edit', 'submit', 'review', 'approve', 'record_payment', 'export'],
        'reports' => ['view', 'export'],
    ],

    // System administration permissions.
    'admin_permissions' => [
        'users.view' => 'View user accounts',
        'users.manage' => 'Create, edit, activate and deactivate users; assign roles and scope',
        'roles.view' => 'View roles and the permission matrix',
        'roles.manage' => 'Create and edit roles and change role permissions',
        'audit.view' => 'View the audit log',
        'audit.export' => 'Export the audit log',
        'security.manage' => 'Change security settings',
        'organization.manage' => 'Manage districts, offices, centers and hostels',
    ],

    // Permissions considered sensitive: changes are highlighted in the UI.
    'sensitive_actions' => ['approve', 'record_payment'],
    'sensitive_permissions' => ['users.manage', 'roles.manage', 'security.manage', 'audit.export'],

    // The Super Admin role can never lose these, so the system always stays administrable.
    'super_admin_role' => 'super_admin',
    'essential_permissions' => ['users.view', 'users.manage', 'roles.view', 'roles.manage', 'audit.view', 'security.manage'],

    // Proposed roles (spec §4) — CONFIRM WITH THE DIRECTORATE.
    // '*' = every permission. 'module.*' = every action of that module.
    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'System-wide technical administration, user/role configuration and audit review.',
            'system' => true,
            'permissions' => ['*'],
        ],
        'directorate_admin' => [
            'name' => 'Directorate Administrator',
            'description' => 'Manage authorized operational users and organizational scope.',
            'permissions' => [
                'users.view', 'users.manage', 'roles.view', 'audit.view', 'organization.manage',
                'activity.view', 'activity.review', 'activity.export',
                'budget.view', 'budget.review', 'budget.export',
                'jawan_markaz.view', 'jawan_markaz.review', 'jawan_markaz.export',
                'hostel.view', 'hostel.review', 'hostel.export',
                'reports.*',
            ],
        ],
        'officer_approver' => [
            'name' => 'Directorate Officer / Approver',
            'description' => 'Review and decide on assigned proposals, expenses or bookings where formally authorized.',
            'permissions' => [
                'activity.view', 'activity.review', 'activity.approve',
                'budget.view', 'budget.review', 'budget.approve',
                'jawan_markaz.view', 'jawan_markaz.review', 'jawan_markaz.approve',
                'hostel.view', 'hostel.review', 'hostel.approve',
                'reports.*',
            ],
        ],
        'activity_officer' => [
            'name' => 'Activity Officer',
            'description' => 'Register physical activity proposals, maintain records and completion evidence.',
            'permissions' => [
                'activity.view', 'activity.create', 'activity.edit', 'activity.submit', 'activity.export',
                'budget.view', 'reports.*',
            ],
        ],
        'finance_staff' => [
            'name' => 'Finance Staff',
            'description' => 'Manage budgets, allocations, transactions, payments and financial reports.',
            'permissions' => [
                'budget.view', 'budget.create', 'budget.edit', 'budget.submit', 'budget.record_payment', 'budget.export',
                'jawan_markaz.view', 'jawan_markaz.review', 'jawan_markaz.record_payment',
                'hostel.view', 'hostel.record_payment', 'activity.view', 'reports.*',
            ],
        ],
        'jawan_markaz_staff' => [
            'name' => 'Jawan Markaz Staff',
            'description' => 'Submit invoices/expenses and view records for the assigned center.',
            'permissions' => [
                'jawan_markaz.view', 'jawan_markaz.create', 'jawan_markaz.edit', 'jawan_markaz.submit',
                'reports.view',
            ],
        ],
        'hostel_staff' => [
            'name' => 'Hostel Staff',
            'description' => 'Manage bookings, check-in/check-out and records for assigned hostel(s).',
            'permissions' => [
                'hostel.view', 'hostel.create', 'hostel.edit', 'hostel.submit',
                'reports.view',
            ],
        ],
        'report_viewer' => [
            'name' => 'Report Viewer',
            'description' => 'View and export only approved reports.',
            'permissions' => ['reports.view', 'reports.export'],
        ],
    ],

    // Defaults for the Security Settings screen (stored in the `settings` table).
    'security_defaults' => [
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_number' => true,
        'password_require_special' => true,
        'password_expiry_days' => 90,          // 0 = never
        'max_failed_attempts' => 5,
        'lockout_minutes' => 30,
        'captcha_enabled' => false,
        'session_timeout_minutes' => 60,        // idle timeout
        'remember_me_days' => 7,                // 0 = disable "remember me"
        'password_reset_enabled' => false,      // Directorate must confirm the channel (spec §17)
    ],
];
