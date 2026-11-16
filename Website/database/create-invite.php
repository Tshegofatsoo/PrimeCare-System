<?php
/**
 * PrimeCare — create a staff invitation from the command line
 * ---------------------------------------------------------
 *   php database/create-invite.php admin@clinic.co.za ADMIN
 *   php database/create-invite.php dr.smith@clinic.co.za DOCTOR
 *
 * Use this to create the FIRST admin of a fresh install (after that, admins
 * invite everyone else from the app: Settings > Invite a staff member).
 * Needs database/invites_migration.sql to have been imported.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/../includes/staff-auth.php';

$email = strtolower(trim($argv[1] ?? ''));
$role  = strtoupper(trim($argv[2] ?? 'ADMIN'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['ADMIN', 'DOCTOR'], true)) {
    fwrite(STDERR, "Usage: php database/create-invite.php <email> [ADMIN|DOCTOR]\n");
    exit(1);
}
if (staff_email_taken($pdo, $email)) {
    fwrite(STDERR, "A staff account with $email already exists.\n");
    exit(1);
}

$invite = create_staff_invite($pdo, $email, $role, null);
echo "Invitation for $email ($role)\n";
echo "  Code:    {$invite['code']}\n";
echo "  Expires: {$invite['expiresAt']} (works once, only for this email)\n";
echo "Open the app > Create a staff account, use this exact email and enter the code.\n";
