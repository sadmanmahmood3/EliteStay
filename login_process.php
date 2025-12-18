<?php
session_start();
include 'db.php';

$error = '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$user_type = $_POST['user_type'] ?? 'member';

// Validate inputs
if (empty($email) || empty($password)) {
  $error = 'Email and password are required.';
  $_SESSION['login_error'] = $error;
  header('Location: login.php');
  exit;
}

// Sanitize user type
if (!in_array($user_type, ['member', 'admin'])) {
  $user_type = 'member';
}

// Determine table and fetch logic based on user type
if ($user_type === 'admin') {
  $table = 'administrators';
  $name_col = 'name';
  $email_col = 'email';
  $pass_col = 'password';
  $id_col = 'id';
  $status_col = 'status';
  $redirect_url = 'index.php';

  // Fetch admin by email
  $stmt = $conn->prepare("SELECT $id_col, $name_col, $pass_col, role, $status_col FROM $table WHERE $email_col = ? LIMIT 1");
  if (!$stmt) {
    die('Database error: ' . $conn->error);
  }
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if (!$user) {
    $error = 'Invalid email or password.';
  } elseif ($user['status'] !== 'Active') {
    $error = 'Your admin account is inactive. Contact support.';
  } else {
    // Verify password (plain text comparison)
    if ($password === $user['password']) {
      // Set session variables
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user_name'] = $user['name'];
      $_SESSION['user_email'] = $email;
      $_SESSION['user_type'] = 'admin';
      $_SESSION['admin_role'] = $user['role'];
      header('Location: ' . $redirect_url);
      exit;
    } else {
      $error = 'Invalid email or password.';
    }
  }
} else {
  // Member login
  $table = 'members';
  $name_col = 'name';
  $email_col = 'email';
  $pass_col = 'password';
  $id_col = 'id';
  $status_col = 'status';
  $redirect_url = 'index.php';

  // Fetch member by email with membership tier info
  $stmt = $conn->prepare("
    SELECT 
      m.$id_col, 
      m.$name_col, 
      m.$pass_col, 
      m.membership_tier_id, 
      m.avatar, 
      m.$status_col, 
      mt.name as tier_name,
      mt.tier_level 
    FROM $table m 
    LEFT JOIN membership_tiers mt ON m.membership_tier_id = mt.id 
    WHERE m.$email_col = ? LIMIT 1
  ");
  if (!$stmt) {
    die('Database error: ' . $conn->error);
  }
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if (!$user) {
    $error = 'Invalid email or password.';
  } elseif ($user['status'] !== 'Active') {
    $error = 'Your account is inactive or suspended. Contact support.';
  } else {
    // Verify password (plain text comparison)
    if ($password === $user['password']) {
      // Determine user_type based on membership tier name
      // Resolve the precise user type (cardholder vs stakeholder) from tier details
      $member_user_type = 'cardholder';
      if (!empty($user['tier_level'])) {
        $member_user_type = ((int) $user['tier_level'] >= 2) ? 'stakeholder' : 'cardholder';
      } elseif (!empty($user['tier_name'])) {
        $tier_name = strtolower($user['tier_name']);
        if (strpos($tier_name, 'stakeholder') !== false) {
          $member_user_type = 'stakeholder';
        }
      }

      // Fetch active perks for the member's tier so the UI can display their benefits
      $membership_perks = [];
      if (!empty($user['membership_tier_id'])) {
        $perks_stmt = $conn->prepare("
          SELECT perk_name, description 
          FROM membership_perks 
          WHERE membership_tier_id = ? AND is_active = 'Yes'
          ORDER BY id ASC
        ");
        if ($perks_stmt) {
          $perks_stmt->bind_param('i', $user['membership_tier_id']);
          $perks_stmt->execute();
          $perks_result = $perks_stmt->get_result();
          while ($perk = $perks_result->fetch_assoc()) {
            $membership_perks[] = $perk;
          }
          $perks_stmt->close();
        }
      }

      // Set session variables
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user_name'] = $user['name'];
      $_SESSION['user_email'] = $email;
      $_SESSION['user_type'] = $member_user_type;
      $_SESSION['membership_tier_id'] = $user['membership_tier_id'];
      $_SESSION['membership_tier_name'] = $user['tier_name'];
      $_SESSION['user_avatar'] = $user['avatar'];
      $_SESSION['membership_perks'] = $membership_perks;
      header('Location: ' . $redirect_url);
      exit;
    } else {
      $error = 'Invalid email or password.';
    }
  }
}

// If we reach here, login failed
$_SESSION['login_error'] = $error;
header('Location: login.php');
exit;