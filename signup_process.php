<?php
session_start();
include 'db.php';

$error = '';
$success = '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
  $error = 'Name, email, and password are required.';
} elseif (strlen($password) < 8) {
  $error = 'Password must be at least 8 characters long.';
} elseif ($password !== $confirm_password) {
  $error = 'Passwords do not match.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $error = 'Please enter a valid email address.';
}

if (!empty($error)) {
  $_SESSION['signup_error'] = $error;
  $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'phone' => $phone];
  header('Location: signup.php');
  exit;
}

// Store password as plain text (no hashing)
$hashed_password = $password;

// Check if email already exists
$check_stmt = $conn->prepare("SELECT id FROM members WHERE email = ? LIMIT 1");
if (!$check_stmt) {
  die('Database error: ' . $conn->error);
}
$check_stmt->bind_param('s', $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
  $error = 'Email address is already registered. Please login or use a different email.';
  $_SESSION['signup_error'] = $error;
  $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'phone' => $phone];
  header('Location: signup.php');
  exit;
}

// Default membership tier is 'Cardholder' (id = 1)
$membership_tier_id = 1;

// Insert new member
$insert_stmt = $conn->prepare("INSERT INTO members (name, email, password, phone, membership_tier_id, status) VALUES (?, ?, ?, ?, ?, 'Active')");
if (!$insert_stmt) {
  die('Database error: ' . $conn->error);
}
$insert_stmt->bind_param('ssssi', $name, $email, $hashed_password, $phone, $membership_tier_id);

if ($insert_stmt->execute()) {
  $success = 'Account created successfully! Redirecting to login...';
  $_SESSION['signup_success'] = $success;
  $_SESSION['form_data'] = [];
  header('Refresh: 2; url=login.php');
  exit;
} else {
  $error = 'Failed to create account. Please try again. Error: ' . $conn->error;
  $_SESSION['signup_error'] = $error;
  $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'phone' => $phone];
  header('Location: signup.php');
  exit;
}