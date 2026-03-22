<?php
// This file has been retired. Please delete it.
http_response_code(410);
die('Gone.');

$db = getDB();

// Map numeric duplicate IDs → canonical slug IDs to keep
$map = [
    '1' => 'food',
    '2' => 'transport',
    '3' => 'shopping',
    '4' => 'bills',
    '5' => 'health',
    '6' => 'entertainment',
    '7' => 'education',
    '8' => 'other',
];

foreach ($map as $numericId => $slugId) {
    // Reassign transactions referencing numeric ID to the slug ID
    $db->prepare('UPDATE transactions SET category_id = ? WHERE category_id = ?')
       ->execute([$slugId, $numericId]);

    // Reassign budgets referencing numeric ID to the slug ID
    $db->prepare('UPDATE budgets SET category_id = ? WHERE category_id = ?')
       ->execute([$slugId, $numericId]);

    // Delete the duplicate numeric-ID category
    $db->prepare('DELETE FROM categories WHERE id = ?')
       ->execute([$numericId]);

    echo "Removed duplicate category id=$numericId, reassigned refs to '$slugId'<br>";
}

echo "<br><strong>Done! Categories are now deduplicated.</strong><br>";
echo "<a href='/pocketwise-php/pocketwise-php/public/'>Go to app</a>";
