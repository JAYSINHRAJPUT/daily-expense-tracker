<?php
function handleGetBudgets(): void {
    $user   = requireAuth();
    $userId = $_GET['userId'] ?? '';
    if ($userId !== $user['id']) error('Forbidden', 403);

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM budgets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    json(array_map('mapRow', $rows));
}

function handleUpsertBudget(): void {
    $user = requireAuth();
    $b    = body();

    if (($b['user_id'] ?? '') !== $user['id']) error('Forbidden', 403);

    $db     = getDB();
    $now    = date('Y-m-d H:i:s');
    $catId  = $b['category_id'] ?? null;
    $month  = $b['month'] ?? null;
    $amount = (float)($b['amount'] ?? 0);
    $type   = $b['type'] ?? '';

    // Check for existing budget with same key
    $stmt = $db->prepare(
        'SELECT * FROM budgets WHERE user_id = ? AND type = ?
         AND (category_id <=> ?) AND (month <=> ?)'
    );
    $stmt->execute([$user['id'], $type, $catId, $month]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare('UPDATE budgets SET amount = ?, updated_at = ? WHERE id = ?')
           ->execute([$amount, $now, $existing['id']]);
        $stmt = $db->prepare('SELECT * FROM budgets WHERE id = ?');
        $stmt->execute([$existing['id']]);
        json(mapRow($stmt->fetch()));
    } else {
        $id = generateId();
        $db->prepare(
            'INSERT INTO budgets (id, user_id, type, category_id, amount, month, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $user['id'], $type, $catId, $amount, $month, $now, $now]);
        $stmt = $db->prepare('SELECT * FROM budgets WHERE id = ?');
        $stmt->execute([$id]);
        json(mapRow($stmt->fetch()));
    }
}

function handleDeleteBudget(string $id): void {
    $user = requireAuth();
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM budgets WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) error('Budget not found', 404);
    if ($row['user_id'] !== $user['id']) error('Forbidden', 403);
    $db->prepare('DELETE FROM budgets WHERE id = ?')->execute([$id]);
    json(['success' => true]);
}
