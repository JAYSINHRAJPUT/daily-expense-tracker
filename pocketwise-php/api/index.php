<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';

// ── CORS ────────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = array_filter([
    CORS_ORIGIN,
    'http://localhost',
    'http://localhost:8080',
    'http://localhost:3000',
]);

if ($origin !== '' && (in_array($origin, $allowed, true) || APP_ENV === 'development')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // fallback for same-origin/no Origin header requests
    header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function body(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function json(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error(string $msg, int $code = 400): void {
    json(['error' => $msg], $code);
}

function requireAuth(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) error('Unauthorized', 401);
    $token = substr($auth, 7);
    $payload = JWT::decode($token, JWT_SECRET);
    if (!$payload) error('Invalid token', 401);
    return $payload;
}

function mapRow(array $row): array {
    // Ensure numeric fields are cast properly
    if (isset($row['amount'])) $row['amount'] = (float)$row['amount'];
    return $row;
}

// ── Router ───────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip leading /api prefix if present (works both with and without it)
$path = preg_replace('#^.*?/api#', '', $uri);
$path = rtrim($path, '/') ?: '/';

// Extract dynamic segments
// /transactions/123  ->  ['transactions', '123']
$segments = explode('/', ltrim($path, '/'));

try {
    // ── Health ──────────────────────────────────────────────────────────────
    if ($path === '/health' && $method === 'GET') {
        json(['ok' => true]);
    }

    // ── Auth ─────────────────────────────────────────────────────────────────
    if ($path === '/auth/signup' && $method === 'POST') {
        require __DIR__ . '/handlers/auth.php';
        handleSignup();
    }
    if ($path === '/auth/signin' && $method === 'POST') {
        require __DIR__ . '/handlers/auth.php';
        handleSignin();
    }

    // ── Categories ────────────────────────────────────────────────────────────
    if ($path === '/categories' && $method === 'GET') {
        require __DIR__ . '/handlers/categories.php';
        handleGetCategories();
    }

    // ── Budgets ──────────────────────────────────────────────────────────────
    if ($path === '/budgets' && $method === 'GET') {
        require __DIR__ . '/handlers/budgets.php';
        handleGetBudgets();
    }
    if ($path === '/budgets' && $method === 'POST') {
        require __DIR__ . '/handlers/budgets.php';
        handleUpsertBudget();
    }
    if (preg_match('#^/budgets/([^/]+)$#', $path, $m) && $method === 'DELETE') {
        require __DIR__ . '/handlers/budgets.php';
        handleDeleteBudget($m[1]);
    }

    // ── Transactions ──────────────────────────────────────────────────────────
    if ($path === '/transactions' && $method === 'GET') {
        require __DIR__ . '/handlers/transactions.php';
        handleGetTransactions();
    }
    if ($path === '/transactions' && $method === 'POST') {
        require __DIR__ . '/handlers/transactions.php';
        handleCreateTransaction();
    }
    if (preg_match('#^/transactions/([^/]+)$#', $path, $m) && $method === 'PUT') {
        require __DIR__ . '/handlers/transactions.php';
        handleUpdateTransaction($m[1]);
    }
    if (preg_match('#^/transactions/([^/]+)$#', $path, $m) && $method === 'DELETE') {
        require __DIR__ . '/handlers/transactions.php';
        handleDeleteTransaction($m[1]);
    }

    // ── Profiles ──────────────────────────────────────────────────────────────
    if ($path === '/profiles' && $method === 'GET') {
        require __DIR__ . '/handlers/profiles.php';
        handleGetProfile();
    }
    if (preg_match('#^/profiles/([^/]+)$#', $path, $m) && $method === 'PUT') {
        require __DIR__ . '/handlers/profiles.php';
        handleUpdateProfile($m[1]);
    }

    // ── AI Chat ───────────────────────────────────────────────────────────────
    if ($path === '/ai-chat' && $method === 'POST') {
        require __DIR__ . '/handlers/ai_chat.php';
        handleAiChat();
    }
    if ($path === '/chat-history' && $method === 'GET') {
        require __DIR__ . '/handlers/ai_chat.php';
        handleGetChatHistory();
    }

    // Root
    if ($path === '/' && $method === 'GET') {
        json(['message' => 'PocketWise PHP API is running']);
    }

    error("Route not found: $method $path", 404);

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error($e->getMessage(), 500);
}
