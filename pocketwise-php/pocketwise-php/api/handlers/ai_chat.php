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

    // ── Keyword-based fallback responses ──────────────────────────────────────
    $lastUserInput = '';
    foreach (array_reverse($messages) as $m) {
        if ($m['role'] === 'user') { $lastUserInput = strtolower(trim($m['content'])); break; }
    }

    $keywordReply = null;

    if (preg_match('/\b(hi|hello|hey|howdy|greetings)\b/', $lastUserInput)) {
        $keywordReply = "Hello! 👋 I'm your PocketWise finance assistant. You can ask me things like:\n"
            . "• *How much did I spend this month?*\n"
            . "• *What are my top spending categories?*\n"
            . "• *Show my recent transactions*\n"
            . "• *How are my budgets doing?*\n"
            . "• *Give me some saving tips*";

    } elseif (preg_match('/\b(help|what can you do|commands|features)\b/', $lastUserInput)) {
        $keywordReply = "Here's what you can ask me:\n"
            . "• **Spending** – total spending this month\n"
            . "• **Categories** – top spending categories\n"
            . "• **Transactions** – recent transactions\n"
            . "• **Budget** – budget status\n"
            . "• **Income** – income this month\n"
            . "• **Saving tips** – practical saving advice\n"
            . "• **Balance** – net balance (income minus expenses)";

    } elseif (preg_match('/\b(spend|spending|spent|expense|expenses|how much)\b/', $lastUserInput)
              && !preg_match('/categor|budget|income/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $total    = number_format($financeContext['totalThisMonth'], 2);
        $keywordReply = "This month you have spent **{$currency}{$total}** in total.\n"
            . "Ask about *categories* to see where the money went, or *budget* to check your limits.";

    } elseif (preg_match('/\b(categor|top spend|most spend|breakdown|where.*money)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $cats     = $financeContext['topCategories'];
        if (empty($cats)) {
            $keywordReply = "No category data found for this month yet.";
        } else {
            $lines = ["Here are your **top spending categories** this month:"];
            foreach ($cats as $i => $cat) {
                $lines[] = (($i + 1) . ". {$cat['name']} – {$currency}" . number_format($cat['amount'], 2));
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(recent|latest|last|transaction|transactions)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $txList   = $financeContext['recentTransactions'];
        if (empty($txList)) {
            $keywordReply = "No transactions recorded this month.";
        } else {
            $shown = array_slice($txList, 0, 7);
            $lines = ["Here are your **recent transactions**:"];
            foreach ($shown as $tx) {
                $label = $tx['merchant'] ?: ($tx['category'] ?: 'Misc');
                $lines[] = "• {$tx['date']} – {$label}: {$currency}" . number_format($tx['amount'], 2);
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(budget|budgets|limit|limits)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $budgets  = $financeContext['budgets'];
        if (empty($budgets)) {
            $keywordReply = "You have no budgets set up yet. Go to the Budgets section to create one.";
        } else {
            $lines = ["Your **budget overview**:"];
            foreach ($budgets as $bud) {
                $limit = number_format($bud['amount'] ?? $bud['limit_amount'] ?? 0, 2);
                $name  = $bud['category'] ?? $bud['name'] ?? 'Budget';
                $lines[] = "• {$name}: {$currency}{$limit} limit";
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(income|earn|earnings|salary|revenue)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $income   = 0;
        foreach ($financeContext['recentTransactions'] as $tx) {
            if (($tx['type'] ?? '') === 'income') {
                $income += $tx['amount'];
            }
        }
        $keywordReply = $income > 0
            ? "Your recorded income this month is **{$currency}" . number_format($income, 2) . "**."
            : "No income transactions recorded this month. Make sure to log income entries in the Transactions section.";

    } elseif (preg_match('/\b(balance|net|left|remaining|profit)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $income   = 0;
        foreach ($financeContext['recentTransactions'] as $tx) {
            if (($tx['type'] ?? '') === 'income') {
                $income += $tx['amount'];
            }
        }
        $expenses = $financeContext['totalThisMonth'] - $income;
        $net      = $income - $expenses;
        $keywordReply = "This month:\n"
            . "• Income: **{$currency}" . number_format($income, 2) . "**\n"
            . "• Expenses: **{$currency}" . number_format(max(0, $expenses), 2) . "**\n"
            . "• Net balance: **{$currency}" . number_format($net, 2) . "**";

    } elseif (preg_match('/\b(tip|tips|advice|save|saving|savings|how to save)\b/', $lastUserInput)) {
        $keywordReply = "Here are some practical saving tips:\n"
            . "1. **Track every expense** – awareness is the first step.\n"
            . "2. **Follow the 50/30/20 rule** – 50% needs, 30% wants, 20% savings.\n"
            . "3. **Set category budgets** – use the Budgets section to cap spending.\n"
            . "4. **Review subscriptions** – cancel ones you rarely use.\n"
            . "5. **Cook at home more** – food is usually a top spending category.\n"
            . "6. **Pay yourself first** – set aside savings the day you receive income.";

    } elseif (preg_match('/\b(thank|thanks|thank you|ty|thx)\b/', $lastUserInput)) {
        $keywordReply = "You're welcome! 😊 Feel free to ask anything else about your finances.";

    } elseif (preg_match('/\b(highest|biggest|largest|most expensive|top transaction)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $txList   = $financeContext['recentTransactions'];
        if (empty($txList)) {
            $keywordReply = "No transactions found this month to compare.";
        } else {
            usort($txList, fn($a, $b) => $b['amount'] <=> $a['amount']);
            $top = array_slice($txList, 0, 5);
            $lines = ["Your **biggest transactions** this month:"];
            foreach ($top as $i => $tx) {
                $label = $tx['merchant'] ?: ($tx['category'] ?: 'Misc');
                $lines[] = (($i + 1) . ". {$label} on {$tx['date']}: {$currency}" . number_format($tx['amount'], 2));
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(average|avg|mean|daily|per day)\b/', $lastUserInput)) {
        $currency  = $financeContext['currency'];
        $total     = $financeContext['totalThisMonth'];
        $dayOfMonth = (int)(new DateTime())->format('j');
        $avg = $dayOfMonth > 0 ? $total / $dayOfMonth : 0;
        $keywordReply = "Based on your spending so far this month:\n"
            . "• Total spent: **{$currency}" . number_format($total, 2) . "**\n"
            . "• Days elapsed: **{$dayOfMonth}**\n"
            . "• Average per day: **{$currency}" . number_format($avg, 2) . "**";

    } elseif (preg_match('/\b(food|dining|restaurant|eat|eating|groceries|grocery)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $foodTotal = 0;
        foreach ($financeContext['topCategories'] as $cat) {
            if (preg_match('/food|dining|restaurant|eat|groceries/i', $cat['name'])) {
                $foodTotal += $cat['amount'];
            }
        }
        $keywordReply = $foodTotal > 0
            ? "You've spent **{$currency}" . number_format($foodTotal, 2) . "** on food & dining this month.\nTip: Cooking at home can greatly reduce this."
            : "No food or dining transactions found this month. Make sure transactions are categorised correctly.";

    } elseif (preg_match('/\b(transport|transportation|travel|fuel|gas|car|uber|taxi|commute)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $travelTotal = 0;
        foreach ($financeContext['topCategories'] as $cat) {
            if (preg_match('/transport|travel|fuel|car|uber|taxi|commute/i', $cat['name'])) {
                $travelTotal += $cat['amount'];
            }
        }
        $keywordReply = $travelTotal > 0
            ? "You've spent **{$currency}" . number_format($travelTotal, 2) . "** on transport & travel this month."
            : "No transport or travel transactions found this month. Make sure transactions are categorised correctly.";

    } elseif (preg_match('/\b(subscription|subscriptions|netflix|spotify|streaming|recurring)\b/', $lastUserInput)) {
        $keywordReply = "To manage subscriptions:\n"
            . "1. Review your recent transactions for recurring charges.\n"
            . "2. List all active subscriptions and their monthly cost.\n"
            . "3. Cancel any you haven't used in the last 30 days.\n"
            . "4. Consider sharing plans with family/friends where possible.\n"
            . "Even cutting **2–3 unused subscriptions** can save thousands per year.";

    } elseif (preg_match('/\b(goal|goals|target|financial goal|wealth)\b/', $lastUserInput)) {
        $keywordReply = "Here's how to set a financial goal in PocketWise:\n"
            . "1. Decide on a target amount and deadline.\n"
            . "2. Create a **Budget** for your savings category.\n"
            . "3. Track income vs expenses under the **Dashboard**.\n"
            . "4. Review your **Balance** weekly to stay on track.\n"
            . "Common goals: emergency fund (3–6 months of expenses), vacation, down payment.";

    } elseif (preg_match('/\b(overspend|over budget|exceeded|exceed|went over)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $budgets  = $financeContext['budgets'];
        if (empty($budgets)) {
            $keywordReply = "You have no budgets set up. Create budgets in the Budgets section to track limits.";
        } else {
            $keywordReply = "Check the **Budgets** section to see which categories have exceeded their limits.\n"
                . "You currently have **" . count($budgets) . " budget(s)** configured.\n"
                . "Tip: Tighten the highest-spend categories first.";
        }

    } elseif (preg_match('/\b(payment method|cash|card|credit|debit|upi|online)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $methods  = [];
        foreach ($financeContext['recentTransactions'] as $tx) {
            if (!empty($tx['payment_method'])) {
                $m = strtolower($tx['payment_method']);
                $methods[$m] = ($methods[$m] ?? 0) + $tx['amount'];
            }
        }
        if (empty($methods)) {
            $keywordReply = "No payment method data found. Make sure to fill in the payment method when logging transactions.";
        } else {
            arsort($methods);
            $lines = ["Your spending by **payment method** this month:"];
            foreach ($methods as $method => $amt) {
                $lines[] = "• " . ucfirst($method) . ": {$currency}" . number_format($amt, 2);
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(merchant|shop|store|vendor|where.*buy|where.*bought)\b/', $lastUserInput)) {
        $currency  = $financeContext['currency'];
        $merchants = [];
        foreach ($financeContext['recentTransactions'] as $tx) {
            if (!empty($tx['merchant'])) {
                $key = $tx['merchant'];
                $merchants[$key] = ($merchants[$key] ?? 0) + $tx['amount'];
            }
        }
        if (empty($merchants)) {
            $keywordReply = "No merchant data found this month. Fill in the merchant field when logging transactions.";
        } else {
            arsort($merchants);
            $top   = array_slice($merchants, 0, 6, true);
            $lines = ["Your **top merchants** this month:"];
            foreach ($top as $name => $amt) {
                $lines[] = "• {$name}: {$currency}" . number_format($amt, 2);
            }
            $keywordReply = implode("\n", $lines);
        }

    } elseif (preg_match('/\b(debt|loan|borrow|owe|credit card bill|emi)\b/', $lastUserInput)) {
        $keywordReply = "Managing debt effectively:\n"
            . "1. **List all debts** – amount owed, interest rate, due date.\n"
            . "2. **Avalanche method** – pay off highest interest rate first (saves more).\n"
            . "3. **Snowball method** – pay off smallest balance first (better motivation).\n"
            . "4. **Never miss a minimum payment** – late fees add up quickly.\n"
            . "5. Log your EMI/loan payments as transactions in PocketWise to track them.";

    } elseif (preg_match('/\b(invest|investing|investment|mutual fund|stocks|sip|fd|fixed deposit)\b/', $lastUserInput)) {
        $keywordReply = "Basic investment pointers:\n"
            . "1. Build a **3–6 month emergency fund** before investing.\n"
            . "2. Start with **index funds or mutual funds** for diversification.\n"
            . "3. **SIP (Systematic Investment Plan)** lets you invest small fixed amounts monthly.\n"
            . "4. The earlier you start, the more **compounding** works in your favour.\n"
            . "5. Track your investment contributions as income in PocketWise.";

    } elseif (preg_match('/\b(tax|taxes|tax saving|80c|deduction)\b/', $lastUserInput)) {
        $keywordReply = "Quick tax-saving reminders:\n"
            . "1. **Section 80C** – invest up to ₹1.5L in PPF, ELSS, LIC, etc.\n"
            . "2. **Section 80D** – health insurance premiums are deductible.\n"
            . "3. **HRA** – claim house rent allowance if applicable.\n"
            . "4. Keep receipts for all deductible expenses.\n"
            . "5. Log tax-related payments as transactions to keep a clear record.";

    } elseif (preg_match('/\b(emergency fund|rainy day|safety net)\b/', $lastUserInput)) {
        $currency = $financeContext['currency'];
        $total    = $financeContext['totalThisMonth'];
        $target   = $total * 6;
        $keywordReply = "An **emergency fund** should cover 3–6 months of expenses.\n"
            . "Based on this month's spending of **{$currency}" . number_format($total, 2) . "**, a 6-month target would be:\n"
            . "**{$currency}" . number_format($target, 2) . "**\n"
            . "Start small – even saving 10% of income monthly builds it over time.";

    } elseif (preg_match('/\b(bye|goodbye|see you|see ya|ciao|later)\b/', $lastUserInput)) {
        $keywordReply = "Goodbye! 👋 Come back anytime to review your finances. Stay on budget!";
    }

    $reply = $keywordReply ?? "I'm not sure how to answer that. Try asking about:\n"
        . "• *spending / expenses* – total this month\n"
        . "• *categories / breakdown* – top spending categories\n"
        . "• *transactions / recent* – latest transactions\n"
        . "• *budget* – your budget limits\n"
        . "• *income / balance* – income and net balance\n"
        . "• *average / daily* – average daily spend\n"
        . "• *biggest / highest* – largest transactions\n"
        . "• *merchants / payment method* – where & how you spend\n"
        . "• *food / transport / subscriptions* – category deep-dives\n"
        . "• *debt / invest / tax / emergency fund / goals* – financial topics\n"
        . "• *saving tips* – practical advice";

    if (!$keywordReply && ANTHROPIC_API_KEY) {
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

function handleClearChatHistory(): void {
    $user = requireAuth();
    $db   = getDB();
    $db->prepare('DELETE FROM chat_history WHERE user_id = ?')->execute([$user['id']]);
    json(['message' => 'Chat history cleared.']);
}
