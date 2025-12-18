<?php
session_start();
include 'customerAuthorization.php';
include "db.php";
require 'vendor/autoload.php'; // for dompdf

use Dompdf\Dompdf;

// Payment handler now supports two flows:
// 1) Hotel booking payment: POST includes booking_id and paid -> create payments row and update bookings
// 2) Legacy venue pending_booking in session -> original behavior

$paid = $_POST['paid'] ?? 0;
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$payment_method = $_POST['method'] ?? ($_POST['wallet_type'] ?? 'Unknown');

if ($booking_id > 0) {
  // Hotel booking payment flow
  // Fetch booking details
  $bst = $conn->prepare("SELECT b.id, b.total_price, b.member_id, b.room_id, b.check_in_date, b.check_out_date, rt.name AS room_type_name, rt.base_price, m.name AS member_name, m.email AS member_email
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    LEFT JOIN members m ON b.member_id = m.id
    WHERE b.id = ? LIMIT 1");
  $bst->bind_param('i', $booking_id);
  $bst->execute();
  $booking = $bst->get_result()->fetch_assoc();
  if (! $booking) {
    die('Booking not found.');
  }

  // Record payment
  $txn = 'DUMMY' . time();
  $receipt = 'R' . time() . rand(100, 999);
  $pstmt = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, payment_date, receipt_number) VALUES (?, ?, ?, ?, 'Success', NOW(), ?)");
  $pstmt->bind_param('idsss', $booking_id, $paid, $payment_method, $txn, $receipt);
  $pstmt->execute();

  // Update booking payment_status and booking_status
  $u = $conn->prepare("UPDATE bookings SET payment_status = 'Completed', booking_status = 'Confirmed' WHERE id = ?");
  $u->bind_param('i', $booking_id);
  $u->execute();

  // Build display values
  $food_total = 0; // hotel booking doesn't have food packages in this flow
  $addon_total = 0;
  $revenue = 0;
  $grand = $booking['total_price'];
  $bookingDate = $booking['check_in_date'];
  $timeSlot = $booking['check_out_date'];
  $guests = 1;
} else {
  // Legacy venue flow
  if (!isset($_SESSION['pending_booking'])) {
    header("Location: index.php");
    exit();
  }

  $data = $_SESSION['pending_booking'];
  $venue_id = $data['venue_id'];
  $customer_id = $_SESSION['user_id'];
  $bookingDate = $data['bookingDate'];
  $timeSlot = $data['timeSlot'];
  $guests = $data['guests'];
  $package_name = $data['package_name'];
  $status = "Active";
  $revenue = $guests * 30;

  // Calculate food + addon total
  $pstmt = $conn->prepare("SELECT promotional_price FROM food_packages WHERE venue_id = ? AND package_name = ?");
  $pstmt->bind_param("is", $venue_id, $package_name);
  $pstmt->execute();
  $res = $pstmt->get_result()->fetch_assoc();
  $price = $res['promotional_price'] ?? 0;
  $food_total = $guests * $price;
  $addon_total = 0;

  foreach ($data['add_ons'] ?? [] as $a) {
    $addon_total += $a['price'];
  }
  $grand = $food_total + $revenue + $addon_total;

  // Save booking (legacy)
  $stmt = $conn->prepare("INSERT INTO bookings (venue_id, customer_id, booking_date, timeslot, guests, package_name, status, revenue) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("iisssssi", $venue_id, $customer_id, $bookingDate, $timeSlot, $guests, $package_name, $status, $revenue);
  $stmt->execute();

  unset($_SESSION['pending_booking']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Payment Successful</title>
  <style>
    .success-box {
      background: #fff;
      border-radius: 12px;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      max-width: 600px;
      margin: auto;
    }

    h2 {
      color: #2a0076;
      margin-bottom: 20px;
    }

    .details {
      text-align: left;
      margin-top: 30px;
      font-size: 1rem;
    }

    .btn {
      background: #6a0dad;
      color: white;
      padding: 12px 25px;
      border: none;
      border-radius: 8px;
      margin-top: 30px;
      cursor: pointer;
    }

    .btn:hover {
      background: #4c0082;
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>
  <div class="success-box">
    <h2>🎉 Payment Successful!</h2>
    <p>Advance Paid: <strong>৳<?= number_format($paid) ?></strong></p>
    <div class="details">
      <p><strong>Date:</strong> <?= htmlspecialchars($bookingDate) ?></p>
      <p><strong>Time Slot:</strong> <?= htmlspecialchars($timeSlot) ?></p>
      <p><strong>Guests:</strong> <?= htmlspecialchars($guests) ?></p>
      <p><strong>Food Package:</strong> <?= htmlspecialchars($package_name) ?></p>
      <p><strong>Food Cost:</strong> ৳<?= number_format($food_total) ?></p>
      <p><strong>Service Charge:</strong> ৳<?= number_format($revenue) ?></p>
      <p><strong>Add-Ons:</strong> ৳<?= number_format($addon_total) ?></p>
      <p><strong>Total:</strong> ৳<?= number_format($grand) ?></p>
    </div>

    <form action="exportPdf.php" method="POST">
      <input type="hidden" name="date" value="<?= $bookingDate ?>">
      <input type="hidden" name="slot" value="<?= $timeSlot ?>">
      <input type="hidden" name="guests" value="<?= $guests ?>">
      <input type="hidden" name="package" value="<?= $package_name ?>">
      <input type="hidden" name="food_total" value="<?= $food_total ?>">
      <input type="hidden" name="service" value="<?= $revenue ?>">
      <input type="hidden" name="addons" value="<?= $addon_total ?>">
      <input type="hidden" name="grand" value="<?= $grand ?>">
      <input type="hidden" name="paid" value="<?= $paid ?>">
      <button type="submit" class="btn">📄 Download Invoice (PDF)</button>
    </form>
  </div>
</body>

</html>