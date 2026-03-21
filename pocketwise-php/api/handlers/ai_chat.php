<?php
function handleAiChat(): void {
    $user = requireAuth();
    $b    = body();
    $messages = $b['messages'] ?? [];
    $userId   = $user['id'];

    $db = getDB();

    // Fetch finance context (recent 500 transactions)
    $stmt = $db->prepare(
        'SELECT t.*, c.name AS cat_name FROM transactions t
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ?
         ORDER BY t.date DESC
         LIMIT 500'
    );
    $stmt->execute([$userId]);
    $allTx = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM budgets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $budgets = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch() ?: [];

    $now         = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth= (int)$now->format('m');

    $monthTx = array_filter($allTx, function ($t) use ($currentYear, $currentMonth) {
        $d = new DateTime($t['date']);
        return (int)$d->format('Y') === $currentYear && (int)$d->format('m') === $currentMonth;
    });

    $totalThisMonth = array_sum(array_column($monthTx, 'amount'));

    $catTotals = [];
    foreach ($monthTx as $t) {
        $key = $t['cat_name'] ?? $t['category_id'] ?? 'Other';
        $catTotals[$key] = ($catTotals[$key] ?? 0) + (float)$t['amount'];
    }
    arsort($catTotals);
    $topCategories = array_slice(
        array_map(fn($k, $v) => ['name' => $k, 'amount' => $v], array_keys($catTotals), $catTotals),
        0, 8
    );

    usort($monthTx, fn($a, $b) => strcmp($b['date'], $a['date']));
    $recentTx = array_slice(array_map(fn($t) => [
        'date'           => $t['date'],
        'amount'         => (float)$t['amount'],
        'type'           => $t['type'],
        'category'       => $t['cat_name'] ?? null,
        'merchant'       => $t['merchant'] ?? null,
        'note'           => $t['note'] ?? null,
        'payment_method' => $t['payment_method'] ?? null,
    ], array_values($monthTx)), 0, 25);

    $financeContext = [
        'currency'           => $profile['currency'] ?? '₹',
        'totalThisMonth'     => $totalThisMonth,
        'topCategories'      => $topCategories,
        'budgets'            => array_values(array_map('mapRow', $budgets)),
        'recentTransactions' => $recentTx,
    ];

    $reply = 'Anthropic API key is not configured.';

    if (ANTHROPIC_API_KEY) {
        $apiMessages = [
            [
                'role'    => 'user',
                'content' => 'Finance data:\n' . json_encode($financeContext, JSON_UNESCAPED_UNICODE),
            ],
        ];
        foreach ($messages as $m) {
            $apiMessages[] = [
                'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $m['content'],
            ];
        }

        $payload = json_encode([
            'model'      => 'claude-sonnet-4-5',
            'max_tokens' => 700,
            'system'     => 'You are a personal finance assistant for an expense tracker app. Use only the finance data provided to answer. Be practical, clear, and concise. If the answer is not supported by the data, say that clearly. When useful, suggest one actionable next step.',
            'messages'   => $apiMessages,
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            $parts = $data['content'] ?? [];
            $texts = array_filter(array_map(fn($p) => $p['type'] === 'text' ? $p['text'] : null, $parts));
            $reply = trim(implode("\n", $texts)) ?: 'Sorry, I could not generate a response.';
        } else {
            $reply = 'Failed to reach AI service. Please try again.';
        }
    }

    // Save to chat history
    $now2 = date('Y-m-d H:i:s');
    $hId  = generateId();
    $lastUserMsg = '';
    foreach (array_reverse($messages) as $m) {
        if ($m['role'] === 'user') { $lastUserMsg = $m['content']; break; }
    }
    $db->prepare(
        'INSERT INTO chat_history (id, user_id, user_message, assistant_reply, created_at)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$hId, $userId, $lastUserMsg, $reply, $now2]);

    json(['reply' => $reply]);
}

function handleGetChatHistory(): void {
    $user = requireAuth();
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM chat_history WHERE user_id = ? ORDER BY created_at ASC LIMIT 100');
    $stmt->execute([$user['id']]);
    json($stmt->fetchAll());
}
