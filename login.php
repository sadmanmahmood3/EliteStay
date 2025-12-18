<?php
session_start();

// Redirect to appropriate dashboard if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
  if ($_SESSION['user_type'] === 'admin') {
    header('Location: admin_dashboard.php');
  } elseif ($_SESSION['user_type'] === 'member') {
    header('Location: customerProfile.php');
  }
  exit;
}

$error = '';
$email = '';
$user_type = 'member';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — EliteStay Hotel</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .login-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
      max-width: 420px;
      width: 100%;
      padding: 40px;
    }

    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .login-header h1 {
      font-size: 2rem;
      color: #2a0076;
      margin-bottom: 8px;
    }

    .login-header p {
      color: #666;
      font-size: 0.95rem;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.95rem;
    }

    input[type="email"],
    input[type="password"],
    select {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 0.95rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    input[type="email"]:focus,
    input[type="password"]:focus,
    select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .user-type-selector {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
    }

    .user-type-option {
      flex: 1;
      position: relative;
    }

    .user-type-option input[type="radio"] {
      display: none;
    }

    .user-type-label {
      display: block;
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      font-weight: 600;
      color: #666;
      transition: all 0.3s ease;
    }

    .user-type-option input[type="radio"]:checked+.user-type-label {
      background: #667eea;
      color: #fff;
      border-color: #667eea;
    }

    .error-message {
      background: #fff1f2;
      border: 1px solid #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .login-button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      margin-top: 10px;
    }

    .login-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .login-button:active {
      transform: translateY(0);
    }

    .signup-link {
      text-align: center;
      margin-top: 20px;
      font-size: 0.9rem;
      color: #666;
    }

    .signup-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .signup-link a:hover {
      color: #764ba2;
    }

    .helper-text {
      font-size: 0.85rem;
      color: #999;
      margin-top: 4px;
    }

    @media (max-width: 480px) {
      .login-container {
        padding: 30px 20px;
      }

      .login-header h1 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="login-header">
      <h1>🏨 EliteStay</h1>
      <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login_process.php">
      <!-- User Type Selection -->
      <div class="form-group">
        <label>Login As</label>
        <div class="user-type-selector">
          <div class="user-type-option">
            <input type="radio" id="member_type" name="user_type" value="member" checked>
            <label for="member_type" class="user-type-label">👤 Member</label>
          </div>
          <div class="user-type-option">
            <input type="radio" id="admin_type" name="user_type" value="admin">
            <label for="admin_type" class="user-type-label">🔐 Admin</label>
          </div>
        </div>
      </div>

      <!-- Email Input -->
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required placeholder="your@email.com">
        <div class="helper-text">Enter your registered email address</div>
      </div>

      <!-- Password Input -->
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="••••••••">
        <div class="helper-text">Your password is case-sensitive</div>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="login-button">Sign In</button>
    </form>

    <!-- Sign Up Link for Members -->
    <div class="signup-link">
      Don't have an account? <a href="signup.php">Create one now</a>
    </div>
  </div>
</body>

</html>