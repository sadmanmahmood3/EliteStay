<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  $_SESSION['cancellation_message'] = 'Please log in to request cancellation.';
  header('Location: login.php');
  exit;
}

$member_id = (int) $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$cancellation_reason = isset($_POST['cancellation_explanation']) ? trim($_POST['cancellation_explanation']) : '';

if (!$booking_id) {
  $_SESSION['cancellation_message'] = 'Invalid booking ID.';
  header('Location: customerBooking.php');
  exit;
}

// Verify booking exists and belongs to the user
$stmt = $conn->prepare("
  SELECT id, member_id, booking_status, cancellation_status, check_in_date, check_out_date
  FROM bookings 
  WHERE id = ? AND member_id = ?
");
$stmt->bind_param('ii', $booking_id, $member_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
  $_SESSION['cancellation_message'] = 'Booking not found or you do not have permission to cancel this booking.';
  header('Location: customerBooking.php');
  exit;
}

// Validate that booking can be cancelled
$current_date = date('Y-m-d');
$check_in = $booking['check_in_date'];
$booking_status = $booking['booking_status'];
$cancellation_status = $booking['cancellation_status'];

// Cannot cancel if already cancelled or checked out
if (in_array($booking_status, ['Cancelled', 'Checked_Out'])) {
  $_SESSION['cancellation_message'] = 'This booking cannot be cancelled as it is already ' . strtolower(str_replace('_', ' ', $booking_status)) . '.';
  header('Location: customerBooking.php');
  exit;
}

// Cannot request cancellation if already requested
if ($cancellation_status === 'Requested') {
  $_SESSION['cancellation_message'] = 'A cancellation request for this booking is already pending.';
  header('Location: customerBooking.php');
  exit;
}

// Cannot request cancellation if already approved/rejected
if (in_array($cancellation_status, ['Approved', 'Rejected'])) {
  $_SESSION['cancellation_message'] = 'This booking has already been processed for cancellation.';
  header('Location: customerBooking.php');
  exit;
}

// Update booking with cancellation request
$stmt = $conn->prepare("
  UPDATE bookings 
  SET cancellation_status = 'Requested',
      cancellation_reason = ?,
      cancellation_requested_at = NOW()
  WHERE id = ?
");
$reason = !empty($cancellation_reason) ? $cancellation_reason : 'No reason provided.';
$stmt->bind_param('si', $reason, $booking_id);

if ($stmt->execute()) {
  $_SESSION['cancellation_message'] = 'Your cancellation request has been submitted successfully. We will review it and get back to you shortly.';
} else {
  $_SESSION['cancellation_message'] = 'Failed to submit cancellation request. Please try again.';
}

$stmt->close();
$conn->close();

header('Location: customerBooking.php');
exit;
?>

