<?php
require __DIR__ . '/panel_lib.php';
panelStartSession();
if (panelIsAuthorized()) {
    header('Location: /panel/');
    exit;
}
$error = '';
if (!panelIsConfigured()) {
    $error = 'Set PANEL_ADMIN_PASSWORD in .env first.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (panelAttemptLogin($username, $password)) {
        header('Location: /panel/');
        exit;
    }
    $error = 'Invalid login or password';
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>vpnbot_panel login</title><style>body{margin:0;font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;display:grid;place-items:center;min-height:100vh}.box{background:#1f2937;border:1px solid #374151;border-radius:16px;padding:24px;min-width:320px}input,button{width:100%;padding:12px;margin-top:12px;border-radius:10px;border:1px solid #4b5563;background:#111827;color:#e5e7eb}button{background:#2563eb;border-color:#2563eb;cursor:pointer}.err{color:#fca5a5;margin-top:10px}</style></head><body><form class="box" method="post"><h2>vpnbot_panel</h2><div>Admin login</div><input name="username" value="admin" autocomplete="username"><input type="password" name="password" placeholder="Password" autocomplete="current-password"><button type="submit">Login</button><?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?></form></body></html>