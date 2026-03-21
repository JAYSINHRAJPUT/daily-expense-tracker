<?php
function handleGetProfile(): void {
    $user   = requireAuth();
    $userId = $_GET['userId'] ?? '';
    if ($userId !== $user['id']) error('Forbidden', 403);

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    json($profile ?: new stdClass());
}

function handleUpdateProfile(string $userId): void {
    $user = requireAuth();
    if ($userId !== $user['id']) error('Forbidden', 403);

    $db  = getDB();
    $b   = body();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare('SELECT id FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    $fullName = substr(trim($b['full_name'] ?? ''), 0, 255);
    $currency = $b['currency'] ?? '₹';

    if ($existing) {
        $db->prepare('UPDATE profiles SET full_name = ?, currency = ?, updated_at = ? WHERE user_id = ?')
           ->execute([$fullName, $currency, $now, $userId]);
    } else {
        $id = generateId();
        $db->prepare(
            'INSERT INTO profiles (id, user_id, full_name, currency, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$id, $userId, $fullName, $currency, $now, $now]);
    }

    // Keep users table in sync
    $db->prepare('UPDATE users SET full_name = ?, currency = ?, updated_at = ? WHERE id = ?')
       ->execute([$fullName, $currency, $now, $userId]);

    $stmt = $db->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    json($stmt->fetch());
}
