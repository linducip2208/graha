<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IAM / Security Policy
    |--------------------------------------------------------------------------
    | Konfigurasi kebijakan keamanan — TIDAK ada role name yang di-hardcode
    | sebagai security gate. MFA required ditentukan via PERMISSION sensitif.
    */

    // Invitation token berlaku (hari).
    'invitation_ttl_days' => env('IAM_INVITATION_TTL_DAYS', 7),

    // Permission yang dianggap SENSITIF → warning UI + MFA policy.
    'sensitive_permissions' => [
        'organization.manage',
        'finance.manage',
        'accounting.post',
        'approval.decide',
        'signature.sign',
        'audit.view',
        'security.manage',
        'user.manage',
    ],

    // Jika user memegang salah satu permission ini → MFA wajib.
    // Kosongkan [] untuk menonaktifkan enforcement.
    'mfa_required_permissions' => [
        'organization.manage',
        'finance.manage',
        'accounting.post',
        'approval.decide',
        'signature.sign',
    ],

    // Grace period setelah deploy policy sebelum login diblokir penuh (hari, 0 = langsung).
    'mfa_grace_days' => env('IAM_MFA_GRACE_DAYS', 7),

    // Separation of duties — kombinasi permission berisiko (warning saja,
    // tidak memblokir; bisa diperketat per perusahaan via company setting).
    'separation_of_duties' => [
        ['procurement.manage', 'accounting.post'],
        ['finance.manage', 'audit.view'],
        ['inventory.manage', 'inventory.approve'] ?? ['inventory.manage'],
        ['user.manage', 'audit.view'],
    ],

    // Retention login history (hari).
    'login_history_retention_days' => env('IAM_LOGIN_HISTORY_DAYS', 365),

    // Session: logout device lain setelah ganti password (default enterprise).
    'logout_other_sessions_on_password_change' => true,

    // Password policy minimal.
    'password_min_length' => 10,

    // Notification kategori yang WAJIB in-app (tidak boleh dimatikan user).
    'mandatory_notification_categories' => ['security', 'approval'],

    // Self-approval sudah diblokir ApprovalEngine; config ini dokumentatif
    // dan dipakai UI untuk menampilkan status kebijakan.
    'self_approval_allowed' => false,

    // Authority default bila user/role tidak punya record approval_authorities:
    // null = mengikuti workflow step (tanpa batas tambahan), angka = hard cap.
    'default_authority_cap' => null,
];
