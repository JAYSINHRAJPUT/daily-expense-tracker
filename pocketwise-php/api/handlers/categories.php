<?php
function handleGetCategories(): void {
    requireAuth();
    $db   = getDB();
    $stmt = $db->query('SELECT * FROM categories ORDER BY name ASC');
    json($stmt->fetchAll());
}
