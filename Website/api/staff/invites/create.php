<?php
/**
 * POST /api/staff/invites/create.php            (ADMIN only)
 * Body: { email, role (ADMIN|DOCTOR) }
 *
 * Issues a one-time invitation code for ONE email address and role.
 * The code is shown to the admin once (it is never stored in plain text),
 * is valid for INVITE_TTL_HOURS, and works a single time.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';

require_method('POST');
$staff = require_staff($pdo, ['ADMIN']);

$in    = read_json();
$email = strtolower(trim((string) ($in['email'] ?? '')));
$role  = strtoupper(trim((string) ($in['role'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    fail('Please enter a valid email address.', 422);
}
if (!in_array($role, ['ADMIN', 'DOCTOR'], true)) {
    fail('Please choose a role.', 422);
}

try {
    if (staff_email_taken($pdo, $email)) {
        fail('A staff account with that email already exists.', 409);
    }
    $invite = create_staff_invite($pdo, $email, $role, $staff['staff_id']);

    json_out([
        'success'   => true,
        'message'   => 'Invitation created. Give this code to the staff member — it will not be shown again.',
        'code'      => $invite['code'],
        'email'     => $email,
        'role'      => $role,
        'expiresAt' => $invite['expiresAt'],
    ], 201);
} catch (PDOException $e) {
    fail('Could not create the invitation. Has database/invites_migration.sql been imported?', 500);
}
