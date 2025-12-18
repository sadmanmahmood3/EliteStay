<?php
// logout.php
session_start();

// 1) Clear session data
$_SESSION = [];

// 2) Remove session cookie if used
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

// 3) Destroy the session
session_destroy();

// 4) Determine where to go next
$raw = $_GET['redirect'] ?? '';
if ($raw && preg_match('#^/[^/].*#', $raw)) {
  // safe internal path
  $dest = $raw;
} else {
  // fallback
  $dest = '/index.php';
}

// 5) Redirect
header("Location: $dest");
exit;
