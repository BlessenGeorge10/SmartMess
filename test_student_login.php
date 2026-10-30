<?php

require_once __DIR__ . '/db_config.php';

$login = "blessen";
$password = "test123";

echo "<h2>SmartMess Student Login Test</h2>";

echo "<p><b>Database:</b> smartmess</p>";

$stmt = $conn->prepare(
    "SELECT id, name, email, username, password, role, status
     FROM users
     WHERE LOWER(email) = ?
        OR LOWER(username) = ?
     LIMIT 1"
);

$stmt->bind_param("ss", $login, $login);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo "<p style='color:red;'><b>❌ USER NOT FOUND</b></p>";

} else {

    $user = $result->fetch_assoc();

    echo "<p style='color:green;'><b>✅ USER FOUND</b></p>";

    echo "<hr>";

    echo "<p><b>ID:</b> " . htmlspecialchars($user['id']) . "</p>";

    echo "<p><b>Name:</b> " . htmlspecialchars($user['name']) . "</p>";

    echo "<p><b>Email:</b> " . htmlspecialchars($user['email']) . "</p>";

    echo "<p><b>Username:</b> " . htmlspecialchars($user['username']) . "</p>";

    echo "<p><b>Role:</b> " . htmlspecialchars($user['role']) . "</p>";

    echo "<p><b>Status:</b> " . htmlspecialchars($user['status']) . "</p>";

    echo "<p><b>Password hash starts with:</b> "
        . htmlspecialchars(substr($user['password'], 0, 7))
        . "</p>";

    echo "<hr>";

    if (password_verify($password, $user['password'])) {

        echo "<h3 style='color:green;'>✅ PASSWORD VERIFY = TRUE</h3>";

        echo "<p>PHP successfully verifies the password.</p>";

    } else {

        echo "<h3 style='color:red;'>❌ PASSWORD VERIFY = FALSE</h3>";

        echo "<p>PHP cannot verify test123 against the stored hash.</p>";
    }
}

$stmt->close();
$conn->close();

?>