<?php
function buildUserResponse(array $user): array {
    return [
        'id'        => $user['id'],
        'email'     => $user['email'],
        'full_name' => $user['full_name'] ?? '',
        'currency'  => $user['currency'] ?? '₹',
    ];
}

function handleSignup(): void {
    $b = body();
    $email    = trim($b['email'] ?? '');
    $password = $b['password'] ?? '';
    $fullName = trim($b['full_name'] ?? '');

    if (!$email || !$password) error('Email and password are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email address');
    if (strlen($password) < 6) error('Password must be at least 6 characters');

    $db = getDB();

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) error('User already exists');

    $id   = generateId();
    $now  = date('Y-m-d H:i:s');
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare(
        'INSERT INTO users (id, email, password, full_name, currency, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$id, $email, $hash, $fullName, '₹', $now, $now]);

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    $token = JWT::encode([
        'id'    => $user['id'],
        'email' => $user['email'],
        'exp'   => time() + 30 * 24 * 3600,
    ], JWT_SECRET);

    json(['token' => $token, 'user' => buildUserResponse($user)]);
}

function handleSignin(): void {
    $b = body();
    $email    = trim($b['email'] ?? '');
    $password = $b['password'] ?? '';

    if (!$email || !$password) error('Email and password are required');

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) error('Invalid credentials', 401);

    $stored = $user['password'];
    $valid  = false;

    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$')) {
        // Bcrypt hash
        $valid = password_verify($password, $stored);
    } else {
        // Legacy plain-text — verify then re-hash
        $valid = ($stored === $password);
        if ($valid) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([$newHash, $user['id']]);
        }
    }

    if (!$valid) error('Invalid credentials', 401);

    $token = JWT::encode([
        'id'    => $user['id'],
        'email' => $user['email'],
        'exp'   => time() + 30 * 24 * 3600,
    ], JWT_SECRET);

    json(['token' => $token, 'user' => buildUserResponse($user)]);
}
