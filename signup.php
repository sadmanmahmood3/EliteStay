<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'member') {
  header('Location: customerProfile.php');
  exit;
}

$error = '';
$success = '';
$name = '';
$email = '';
$phone = '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up — EliteStay Hotel</title>
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

    .signup-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
      max-width: 450px;
      width: 100%;
      padding: 40px;
    }

    .signup-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .signup-header h1 {
      font-size: 2rem;
      color: #2a0076;
      margin-bottom: 8px;
    }

    .signup-header p {
      color: #666;
      font-size: 0.95rem;
    }

    .form-group {
      margin-bottom: 18px;
    }

    label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.95rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="tel"] {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 0.95rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus,
    input[type="tel"]:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

    .success-message {
      background: #e4ffe4;
      border: 1px solid #2ecc71;
      color: #155724;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .signup-button {
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

    .signup-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .signup-button:active {
      transform: translateY(0);
    }

    .login-link {
      text-align: center;
      margin-top: 20px;
      font-size: 0.9rem;
      color: #666;
    }

    .login-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: #764ba2;
    }

    .helper-text {
      font-size: 0.85rem;
      color: #999;
      margin-top: 4px;
    }

    @media (max-width: 480px) {
      .signup-container {
        padding: 30px 20px;
      }

      .signup-header h1 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <div class="signup-header">
      <h1>🏨 EliteStay</h1>
      <p>Create your membership account</p>
    </div>

    <?php if ($error): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success-message">
        ✅ <?= htmlspecialchars($success) ?> <br>
        Redirecting to login...
        <script>
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 2000);
        </script>
      </div>
    <?php else: ?>

      <form method="POST" action="signup_process.php">
        <!-- Full Name -->
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required placeholder="John Doe">
          <div class="helper-text">Your full name as it appears on ID</div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required placeholder="your@email.com">
          <div class="helper-text">We'll use this to verify your account</div>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="+91 9876543210">
          <div class="helper-text">Optional: for booking notifications</div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required placeholder="••••••••">
          <div class="helper-text">Choose any password you like</div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
          <div class="helper-text">Must match your password above</div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="signup-button">Create Account</button>
      </form>

      <!-- Login Link -->
      <div class="login-link">
        Already have an account? <a href="login.php">Sign in here</a>
      </div>

    <?php endif; ?>
  </div>
  <!-- Zapier Chatbot Embed -->
  <script async type='module' src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'></zapier-interfaces-chatbot-embed>
</body>
</body>

</html>