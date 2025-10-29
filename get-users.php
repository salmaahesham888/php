<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll();

if ($users) {
    echo "<h3>Registered Users:</h3>";
    echo "<p><small>SSE is enabled - updates happen automatically</small></p>";
    foreach ($users as $user) {
        echo "👤 " . htmlspecialchars($user['name']) . " — " . htmlspecialchars($user['email']) . "<br>";
    }
} else {
    echo "No users found.";
}
?>