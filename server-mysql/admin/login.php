<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/config_admin.php';
require __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    if (hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — GPS Trip Monitor</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="brand"><span class="brand-dot"></span> GPS TRIP MONITOR — ADMIN</div>
        <form method="post">
            <input type="password" name="password" placeholder="Password admin" autofocus required>
            <button class="btn" style="width:100%" type="submit">Masuk</button>
            <?php if ($error): ?><p class="error-text"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        </form>
    </div>
</div>
</body>
</html>
