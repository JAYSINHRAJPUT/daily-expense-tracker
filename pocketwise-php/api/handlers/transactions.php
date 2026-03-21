<?php
function handleGetTransactions(): void {
    $user   = requireAuth();
    $userId = $_GET['userId'] ?? '';
    if ($userId !== $user['id']) error('Forbidden', 403);

    $db = getDB();
    $stmt = $db->prepare(
        'SELECT t.*, c.name AS cat_name, c.icon AS cat_icon, c.color AS cat_color
         FROM transactions t
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ?
         ORDER BY t.date DESC, t.created_at DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $result = array_map(function ($r) {
        $row = mapRow($r);
        if ($r['cat_name']) {
            $row['categories'] = [
                'name'  => $r['cat_name'],
                'icon'  => $r['cat_icon'],
                'color' => $r['cat_color'],
            ];
        } else {
            $row['categories'] = null;
        }
        unset($row['cat_name'], $row['cat_icon'], $row['cat_color']);
        return $row;
    }, $rows);

    json($result);
}

function handleCreateTransaction(): void {
    $user = requireAuth();
    $b    = body();

    $userId = $b['user_id'] ?? '';
    if (!$userId) error('user_id is required');
    if ($userId !== $user['id']) error('Forbidden', 403);

    $amount = (float)($b['amount'] ?? 0);
    if ($amount <= 0) error('Valid amount is required');
    if (empty($b['type'])) error('Transaction type is required');

    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    $id  = generateId();
    $date = $b['date'] ?? date('Y-m-d');

    $db->prepare(
        'INSERT INTO transactions
         (id, user_id, amount, type, category_id, merchant, note, payment_method, date, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id, $userId, $amount,
        $b['type'],
        $b['category_id'] ?? null,
        $b['merchant'] ?? null,
        $b['note'] ?? null,
        $b['payment_method'] ?? 'Cash',
        $date, $now, $now
    ]);

    $stmt = $db->prepare(
        'SELECT t.*, c.name AS cat_name, c.icon AS cat_icon, c.color AS cat_color
         FROM transactions t
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    $row = mapRow($r);
    $row['categories'] = $r['cat_name'] ? ['name' => $r['cat_name'], 'icon' => $r['cat_icon'], 'color' => $r['cat_color']] : null;
    unset($row['cat_name'], $row['cat_icon'], $row['cat_color']);
    json($row);
}

function handleUpdateTransaction(string $id): void {
    $user = requireAuth();
    $db   = getDB();

    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) error('Transaction not found', 404);
    if ($existing['user_id'] !== $user['id']) error('Forbidden', 403);

    $b   = body();
    $now = date('Y-m-d H:i:s');

    $fields = [];
    $vals   = [];
    $allowed = ['amount', 'type', 'category_id', 'merchant', 'note', 'payment_method', 'date'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $vals[]   = $f === 'amount' ? (float)$b[$f] : $b[$f];
        }
    }
    $fields[] = 'updated_at = ?';
    $vals[]   = $now;
    $vals[]   = $id;

    if (count($fields) > 1) {
        $db->prepare('UPDATE transactions SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
    }

    $stmt = $db->prepare(
        'SELECT t.*, c.name AS cat_name, c.icon AS cat_icon, c.color AS cat_color
         FROM transactions t LEFT JOIN categories c ON c.id = t.category_id WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $r   = $stmt->fetch();
    $row = mapRow($r);
    $row['categories'] = $r['cat_name'] ? ['name' => $r['cat_name'], 'icon' => $r['cat_icon'], 'color' => $r['cat_color']] : null;
    unset($row['cat_name'], $row['cat_icon'], $row['cat_color']);
    json($row);
}

function handleDeleteTransaction(string $id): void {
    $user = requireAuth();
    $db   = getDB();

    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) error('Transaction not found', 404);
    if ($row['user_id'] !== $user['id']) error('Forbidden', 403);

    $db->prepare('DELETE FROM transactions WHERE id = ?')->execute([$id]);
    json(['success' => true]);
}
