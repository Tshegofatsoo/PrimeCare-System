<?php
/**
 * POST /api/staff/auth/register.php
 * Body: { firstName, lastName, email, password, inviteCode }
 *
 * Registration needs a ONE-TIME INVITATION CODE issued by an admin for this
 * exact email address (see api/staff/invites/create.php). The role (ADMIN or
 * DOCTOR) comes from the invitation, never from the client, so nobody can
 * ask for more access than they were invited with.
 *
 * - The password is stored as a bcrypt hash (password_hash) — never plain text.
 * - A DOCTOR registration also creates the matching doctors row, so the new
 *   doctor immediately appears in Assign Doctor and on the website.
 * - Wrong, expired, used and unknown-email codes all give the SAME message, so
 *   the endpoint can't be used to discover which emails have invitations.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_once __DIR__ . '/../../../includes/rate-limit.php';

require_method('POST');
check_rate_limit($pdo, 'register', 10, 15);

$in    = read_json();
$first = trim((string) ($in['firstName'] ?? ''));
$last  = trim((string) ($in['lastName'] ?? ''));
$email = strtolower(trim((string) ($in['email'] ?? '')));
$pass  = (string) ($in['password'] ?? '');
$code  = normalize_invite_code((string) ($in['inviteCode'] ?? ''));

record_attempt($pdo, 'register');   // every attempt counts, successful or not

if (($err = name_error($first, 'first name')) !== null) { fail($err, 422); }
if (($err = name_error($last, 'last name')) !== null)   { fail($err, 422); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    fail('Please enter a valid email address.', 422);
}
if (($err = password_error($pass, $email)) !== null) { fail($err, 422); }
if (strlen($code) !== 10) {
    fail('Please enter the 10-character invitation code from your administrator.', 422);
}

const INVITE_INVALID = 'That invitation code is invalid, expired or already used. Ask your administrator for a new one.';

try {
    $pdo->beginTransaction();

    // Lock the newest unused invitation for this email while we check it.
    $stmt = $pdo->prepare('SELECT invite_id, role, code_hash, expires_at, failed_attempts
                           FROM staff_invites
                           WHERE email = :e AND used_at IS NULL
                           ORDER BY invite_id DESC LIMIT 1 FOR UPDATE');
    $stmt->execute(['e' => $email]);
    $invite = $stmt->fetch();

    if (!$invite || $invite['expires_at'] <= now_dt() || (int) $invite['failed_attempts'] >= INVITE_MAX_FAILED) {
        $pdo->rollBack();
        fail(INVITE_INVALID, 403);
    }

    if (!hash_equals($invite['code_hash'], invite_hash($code, $email))) {
        $pdo->prepare('UPDATE staff_invites SET failed_attempts = failed_attempts + 1 WHERE invite_id = :i')
            ->execute(['i' => $invite['invite_id']]);
        $pdo->commit();             // keep the failed-attempt count
        fail(INVITE_INVALID, 403);
    }

    if (staff_email_taken($pdo, $email)) {
        $pdo->rollBack();
        fail('An account with that email already exists.', 409);
    }

    $role     = $invite['role'];
    $doctorId = null;
    if ($role === 'DOCTOR') {
        $dep = $pdo->query("SELECT department_id FROM departments WHERE department_name = 'General Practice' LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT INTO doctors (first_name, last_name, specialty, department_id, email, status)
                       VALUES (:f, :l, 'General Practice', :d, :e, 'Active')")
            ->execute(['f' => $first, 'l' => $last, 'd' => $dep ?: null, 'e' => $email]);
        $doctorId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO staff (email, password_hash, role, first_name, last_name, doctor_id)
                   VALUES (:e, :h, :r, :f, :l, :d)')
        ->execute(['e' => $email, 'h' => password_hash($pass, PASSWORD_DEFAULT), 'r' => $role,
                   'f' => $first, 'l' => $last, 'd' => $doctorId]);

    // Single use: burn the invitation in the same transaction as the account.
    $pdo->prepare('UPDATE staff_invites SET used_at = :now WHERE invite_id = :i')
        ->execute(['now' => now_dt(), 'i' => $invite['invite_id']]);

    $pdo->commit();
    json_out(['success' => true, 'message' => 'Account created. You can now log in.'], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not create the account. Please try again.', 500);
}
