<?php
/**
 * PrimeCare — Patient Registration Endpoint
 * ---------------------------------------------------------
 * POST /api/auth/register.php
 * Body (JSON): { fullName, idType, idNumber, email, phone, dob, password }
 *
 * Server-side validation is authoritative. The client-side checks
 * in assets/js/auth.js only improve UX — they can be bypassed by
 * anyone calling this endpoint directly, so every rule is re-checked
 * here before anything touches the database.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

// ---- Only allow POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Max 5 registration attempts per IP per 15 minutes — prevents
// automated mass account creation.
check_rate_limit($pdo, 'register', 5, 15);
record_attempt($pdo, 'register');

// ---- Parse JSON body ----
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
    exit;
}

// ---- Collect + trim raw input ----
$fullName = trim($input['fullName'] ?? '');
$idType   = trim($input['idType'] ?? '');
$idNumber = trim($input['idNumber'] ?? '');
$email    = trim($input['email'] ?? '');
$phone    = trim($input['phone'] ?? '');
$dob      = trim($input['dob'] ?? '');
$password = (string)($input['password'] ?? '');

$errors = [];

// ---- Full name: require at least first + last name ----
$nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
if (count($nameParts) < 2) {
    $errors['fullName'] = 'Please enter your full name (first and last).';
} elseif (mb_strlen($fullName) > 100) {
    $errors['fullName'] = 'Full name is too long.';
}

// ---- NEW: ID Type + ID / Passport Number ----
// This is the unique identifier so two patients with the same
// name and surname don't get mixed up.
if (!in_array($idType, ['sa-id', 'passport'], true)) {
    $errors['idType'] = 'Please select your ID type.';
}

if ($idType === 'sa-id') {
    // SA ID must be exactly 13 digits
    if (!preg_match('/^\d{13}$/', $idNumber)) {
        $errors['idNumber'] = 'SA ID must be exactly 13 digits.';
    }
} elseif ($idType === 'passport') {
    // Passport: 6-15 letters/numbers, stored uppercase
    $idNumber = strtoupper($idNumber);
    if (!preg_match('/^[A-Z0-9]{6,15}$/', $idNumber)) {
        $errors['idNumber'] = 'Please enter a valid passport number (6-15 letters/numbers).';
    }
} elseif ($idNumber === '') {
    $errors['idNumber'] = 'Please enter your ID / Passport number.';
}

// ---- Email ----
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (mb_strlen($email) > 100) {
    $errors['email'] = 'Email address is too long.';
}

// ---- Phone: digits only after stripping spaces/dashes/parentheses, 7-15 digits ----
$phoneDigits = preg_replace('/[\s\-()]/', '', $phone);
if (!preg_match('/^\+?\d{7,15}$/', $phoneDigits)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

// ---- Date of birth: valid date, not in the future, realistic age ----
$dobDate = DateTime::createFromFormat('Y-m-d', $dob);
if (!$dobDate || $dobDate->format('Y-m-d') !== $dob) {
    $errors['dob'] = 'Please enter a valid date of birth.';
} else {
    $today = new DateTime();
    if ($dobDate > $today) {
        $errors['dob'] = 'Date of birth cannot be in the future.';
    } else {
        $age = $today->diff($dobDate)->y;
        if ($age > 120) {
            $errors['dob'] = 'Please enter a valid date of birth.';
        }
    }
}

// ---- Password: min 8 chars, at least one letter and one number ----
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors['password'] = 'Password must be at least 8 characters and include a letter and a number.';
}

// ---- Stop here if basic validation failed ----
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please correct the highlighted fields.',
        'errors'  => $errors
    ]);
    exit;
}

// ---- Split full name into first/last for the patients table ----
$lastName  = array_pop($nameParts);
$firstName = implode(' ', $nameParts);

try {
    // ---- Check for an existing account with this email ----
    $checkStmt = $pdo->prepare('SELECT patient_id FROM patients WHERE email = :email LIMIT 1');
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An account with this email already exists.',
            'errors'  => ['email' => 'This email is already registered.']
        ]);
        exit;
    }

    // ---- NEW: Check for an existing account with this ID / Passport ----
    // This is what stops two patients with the same name being confused.
    $checkIdStmt = $pdo->prepare('SELECT patient_id FROM patients WHERE id_number = :id_number LIMIT 1');
    $checkIdStmt->execute(['id_number' => $idNumber]);

    if ($checkIdStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An account with this ID / Passport number already exists.',
            'errors'  => ['idNumber' => 'This ID / Passport number is already registered. Please log in instead.']
        ]);
        exit;
    }

    // ---- Hash the password (never store plaintext) ----
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // ---- Insert the new patient (email doubles as username since no
    //      separate username was collected on the registration form) ----
    $insertStmt = $pdo->prepare(
        'INSERT INTO patients (first_name, last_name, id_type, id_number, email, phone, username, password_hash, date_of_birth)
         VALUES (:first_name, :last_name, :id_type, :id_number, :email, :phone, :username, :password_hash, :dob)'
    );

    $insertStmt->execute([
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'id_type'       => $idType,
        'id_number'     => $idNumber,
        'email'         => $email,
        'phone'         => $phoneDigits,
        'username'      => $email,
        'password_hash' => $passwordHash,
        'dob'           => $dob,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while creating your account. Please try again.'
    ]);
    // In development, you may want to log $e->getMessage() to a server-side
    // log file — never echo raw exception details back to the client.
}
