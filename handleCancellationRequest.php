<?php
// update_cancellation_status.php
session_start();
include 'db.php';

// Check if the user is logged in and is a venue manager
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'venue') {
  echo json_encode(['message' => 'Unauthorized access.']);
  exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$action = $_POST['action'] ?? '';

if (!$booking_id || !in_array($action, ['approve', 'reject'])) {
  echo json_encode(['message' => 'Invalid request.']);
  exit;
}

// Determine new status
$newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

// Update the booking's cancellation status
$stmt = $conn->prepare("UPDATE bookings SET cancellationStatus = ? WHERE id = ?");
$stmt->bind_param('si', $newStatus, $booking_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(['message' => "Cancellation request $newStatus successfully."]);
} else {
  echo json_encode(['message' => 'Failed to update cancellation request.']);
}

$stmt->close();
$conn->close();
