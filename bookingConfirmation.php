<?php
session_start();
include "db.php";

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php?redirect=bookingConfirmation.php');
  exit;
}

// Initialize
$success = false;
$booking = null;

// If a booking_id is provided, show that booking (hotel schema)
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

// Helper: compute nights
function nights_between($start, $end)
{
  $s = strtotime($start);
  $e = strtotime($end);
  if (!$s || !$e) return 0;
  return max(0, (int)(($e - $s) / 86400));
}

// Handle confirmation POST for hotel-style pending_booking in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking']) && isset($_SESSION['pending_booking'])) {
  $data = $_SESSION['pending_booking'];
  // If pending booking comes from hotel flow (has room_type_id)
  if (!empty($data['room_type_id']) && !empty($data['check_in']) && !empty($data['check_out'])) {
    $member_id = $_SESSION['user_id'] ?? null;
    if (!$member_id) {
      die('Not authenticated');
    }
    $room_type_id = (int)$data['room_type_id'];
    $check_in = $data['check_in'];
    $check_out = $data['check_out'];
    $guests = (int)($data['guests'] ?? 1);

    // find available room
    $roomSql = "SELECT r.id FROM rooms r WHERE r.room_type_id = ? AND r.status = 'Available' AND r.id NOT IN (
      SELECT room_id FROM bookings WHERE booking_status IN ('Confirmed','Pending','Checked_In')
      AND NOT (check_out_date <= ? OR check_in_date >= ?)
    ) AND r.id NOT IN (
      SELECT room_id FROM room_availability WHERE available_date BETWEEN ? AND ? AND is_available = 'No'
    ) LIMIT 1";
    $rstmt = $conn->prepare($roomSql);
    $rstmt->bind_param('issss', $room_type_id, $check_in, $check_out, $check_in, $check_out);
    $rstmt->execute();
    $rrow = $rstmt->get_result()->fetch_assoc();
    if (!$rrow) {
      $error = 'No rooms available for the selected dates.';
    } else {
      $room_id = (int)$rrow['id'];
      // compute price from room_types
      $pstmt = $conn->prepare("SELECT base_price FROM room_types WHERE id = ?");
      $pstmt->bind_param('i', $room_type_id);
      $pstmt->execute();
      $ptype = $pstmt->get_result()->fetch_assoc();
      $base_price = (float)($ptype['base_price'] ?? 0);
      $nights = nights_between($check_in, $check_out);
      $total_price = $base_price * max(1, $nights);

      $bstmt = $conn->prepare("INSERT INTO bookings (member_id, room_id, check_in_date, check_out_date, num_guests, booking_status, total_price, payment_status) VALUES (?, ?, ?, ?, ?, 'Pending', ?, 'Pending')");
      $bstmt->bind_param('iissid', $member_id, $room_id, $check_in, $check_out, $guests, $total_price);
      if ($bstmt->execute()) {
        $booking_id = $bstmt->insert_id;
        $success = true;
        unset($_SESSION['pending_booking']);
      } else {
        $error = 'Failed to create booking: ' . $conn->error;
      }
    }
  } else {
    // preserve legacy behavior: store pending_booking and continue
    // already stored earlier in the old flow; do nothing here
  }
}

// If booking_id provided (or just created), fetch and display booking details
if (isset($booking_id) && $booking_id > 0) {
  $stmt = $conn->prepare("SELECT b.*, r.room_number, rt.name AS room_type_name, rt.base_price, m.name AS member_name, m.email AS member_email
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    LEFT JOIN members m ON b.member_id = m.id
    WHERE b.id = ? LIMIT 1");
  $stmt->bind_param('i', $booking_id);
  $stmt->execute();
  $booking = $stmt->get_result()->fetch_assoc();
}

// Fallback: if no booking_id but there is a legacy pending_booking in session, use old behavior
$legacy_data = $_SESSION['pending_booking'] ?? null;
if (!$booking && $legacy_data && empty($booking_id)) {
  $data = $legacy_data;
  // existing legacy logic: treat as venue booking preview
  $venue = null;
  if (!empty($data['venue_id'])) {
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->bind_param("i", $data['venue_id']);
    $stmt->execute();
    $venue = $stmt->get_result()->fetch_assoc();
  }
  // package price
  $price = 0;
  if (!empty($data['venue_id']) && !empty($data['package_name'])) {
    $pstmt = $conn->prepare("SELECT promotional_price FROM food_packages WHERE venue_id = ? AND package_name = ?");
    $pstmt->bind_param("is", $data['venue_id'], $data['package_name']);
    $pstmt->execute();
    $res = $pstmt->get_result()->fetch_assoc();
    $price = $res['promotional_price'] ?? 0;
  }
  $total = ($data['guests'] ?? 0) * $price;
  $revenue = ($data['guests'] ?? 0) * 30;
  $add_on_total = 0;
  foreach ($data['add_ons'] ?? [] as $a) $add_on_total += $a['price'] ?? 0;
  $grand = $total + $revenue + $add_on_total;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Booking Confirmation</title>
  <style>
    .confirmation-wrapper {
      max-width: 1200px;
      margin: auto;
    }

    .add-on-block li {
      margin-bottom: 10px;
      font-size: 1rem;
      position: relative;
      padding-left: 20px;
    }

    .add-on-block li::before {
      content: "✔";
      color: #6a0dad;
      position: absolute;
      left: 0;
    }

    .price-summary h3 {
      font-size: 1.5rem;
      margin-bottom: 25px;
      color: #2a0076;
    }

    .price-summary .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 12px;
      font-size: 1.05rem;
    }

    .grand-total {
      background: linear-gradient(90deg, #f9f9fb, #f1f1f8);
      padding: 18px 24px;
      border-left: 5px solid #6a0dad;
      border-radius: 10px;
      margin: 30px 0 20px;
      font-size: 1.2rem;
      font-weight: 600;
      color: #2a0076;
      box-shadow: 0 4px 12px rgba(106, 13, 173, 0.07);
    }

    .advance-box {
      background: #fff7f7;
      border: 1px solid #f5d0d0;
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
    }

    .advance-box h4 {
      margin-bottom: 10px;
      font-size: 1.1rem;
      color: #8b0000;
    }

    .advance-box .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 1.05rem;
    }

    .advance-box .note {
      font-size: 0.9rem;
      color: #555;
      margin-top: 8px;
      font-style: italic;
    }

    .btn {
      background: linear-gradient(to right, #6a0dad, #8a2be2);
      color: #fff;
      padding: 14px 28px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 500;
      margin-top: 30px;
      width: 100%;
      transition: background .3s ease;
    }

    .btn:hover {
      background: linear-gradient(to right, #53179f, #731dc4);
    }

    .success {
      background: #e4ffe4;
      border: 1px solid #2ecc71;
      color: #239d3a;
      padding: 18px;
      font-weight: 600;
      border-radius: 10px;
      text-align: center;
      font-size: 1.1rem;
      margin-bottom: 30px;
    }

    .booking-summary {
      background: #f5f0ff;
      padding: 25px;
      border-left: 4px solid #6a0dad;
      border-radius: 10px;
      margin-top: 30px;
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>
  <div class="confirmation-wrapper">
    <?php if ($success && !empty($booking)): ?>
      <?php $grand = $booking['total_price'];
      $advanceAmount = ceil($grand * 0.40); ?>
      <div class="success">✅ Booking created successfully. Booking ID: <?= htmlspecialchars($booking['id']) ?></div>
      <div style="max-width:700px;margin:20px auto;">
        <h3>Booking Summary</h3>
        <p><strong>Room Type:</strong> <?= htmlspecialchars($booking['room_type_name']) ?></p>
        <p><strong>Room Number:</strong> <?= htmlspecialchars($booking['room_number']) ?></p>
        <p><strong>Check-in:</strong> <?= htmlspecialchars($booking['check_in_date']) ?></p>
        <p><strong>Check-out:</strong> <?= htmlspecialchars($booking['check_out_date']) ?></p>
        <p><strong>Total:</strong> BDT <?= number_format($grand, 2) ?></p>
        <p><strong>Advance (40%):</strong> BDT <?= number_format($advanceAmount, 2) ?></p>

        <form method="POST" action="dummyPaymentGateway.php">
          <input type="hidden" name="total" value="<?= htmlspecialchars($grand) ?>">
          <input type="hidden" name="advance" value="<?= htmlspecialchars($advanceAmount) ?>">
          <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']) ?>">
          <button type="submit" class="btn">💳 Pay Advance Now</button>
        </form>
      </div>

    <?php elseif (!empty($booking)): ?>
      <?php $grand = $booking['total_price'];
      $advanceAmount = ceil($grand * 0.40); ?>
      <div class="confirmation-container">
        <div class="booking-details">
          <h2>🎉 Booking Details</h2>
          <div class="booking-summary">
            <p><span class="icon">📍</span><strong>Room Type:</strong> <?= htmlspecialchars($booking['room_type_name']) ?></p>
            <p><span class="icon">🛏️</span><strong>Room Number:</strong> <?= htmlspecialchars($booking['room_number']) ?></p>
            <p><span class="icon">📅</span><strong>Check-in:</strong> <?= htmlspecialchars($booking['check_in_date']) ?></p>
            <p><span class="icon">📅</span><strong>Check-out:</strong> <?= htmlspecialchars($booking['check_out_date']) ?></p>
            <p><span class="icon">👤</span><strong>Guest:</strong> <?= htmlspecialchars($booking['member_name'] ?? '') ?> (<?= htmlspecialchars($booking['member_email'] ?? '') ?>)</p>
          </div>
        </div>
        <div class="price-summary">
          <h3>💰 Payment</h3>
          <div class="row"><span>Total:</span><strong>৳<?= number_format($grand) ?></strong></div>
          <div class="row"><span>Advance (40%):</span><strong>৳<?= number_format($advanceAmount) ?></strong></div>
          <form method="POST" action="dummyPaymentGateway.php">
            <input type="hidden" name="total" value="<?= htmlspecialchars($grand) ?>">
            <input type="hidden" name="advance" value="<?= htmlspecialchars($advanceAmount) ?>">
            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']) ?>">
            <button type="submit" class="btn">💳 Pay Advance Now</button>
          </form>
        </div>
      </div>

    <?php else: ?>
      <div class="confirmation-container">
        <div class="booking-details">
          <h2>🎉 Booking Summary</h2>
          <div class="booking-summary">
            <p><span class="icon">📍</span><strong>Venue:</strong> <?= htmlspecialchars($venue['name'] ?? '') ?></p>
            <p><span class="icon">📅</span><strong>Date:</strong> <?= htmlspecialchars($data['bookingDate'] ?? '') ?></p>
            <p><span class="icon">🕒</span><strong>Time Slot:</strong> <?= htmlspecialchars($data['timeSlot'] ?? '') ?></p>
            <p><span class="icon">👥</span><strong>Guests:</strong> <?= htmlspecialchars($data['guests'] ?? '') ?></p>
            <p><span class="icon">🍽️</span><strong>Food Package:</strong> <?= htmlspecialchars($data['package_name'] ?? '') ?></p>
          </div>

          <?php if (!empty($data['add_ons'])): ?>
            <div class="add-on-block">
              <h4>Selected Add-Ons:</h4>
              <ul>
                <?php foreach ($data['add_ons'] as $addon): ?>
                  <li><strong><?= htmlspecialchars($addon['category']) ?>:</strong> <?= htmlspecialchars($addon['name']) ?> (৳<?= number_format($addon['price']) ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>

        <div class="price-summary">
          <h3>💰 Cost Summary</h3>
          <div class="row"><span>Total Food Cost:</span><strong>৳<?= number_format($total ?? 0) ?></strong></div>
          <div class="row"><span>Service Charge:</span><strong>৳<?= number_format($revenue ?? 0) ?></strong></div>
          <?php if (!empty($add_on_total)): ?><div class="row"><span>Add-On Cost:</span><strong>৳<?= number_format($add_on_total) ?></strong></div><?php endif; ?>
          <div class="grand-total">Grand Total: ৳<?= number_format($grand ?? 0) ?></div>

          <?php $advanceAmount = ceil(($grand ?? 0) * 0.40); ?>
          <div class="advance-box">
            <h4>Advance Payment</h4>
            <div class="row"><span>40% Payable Now:</span><strong>৳<?= number_format($advanceAmount) ?></strong></div>
            <p class="note">This must be paid to confirm booking. The rest can be paid at the venue.</p>
          </div>

          <form method="POST" action="dummyPaymentGateway.php">
            <input type="hidden" name="total" value="<?= htmlspecialchars($grand ?? 0) ?>">
            <input type="hidden" name="advance" value="<?= htmlspecialchars($advanceAmount) ?>">
            <button type="submit" class="btn">💳 Proceed to Payment</button>
          </form>

        </div>
      </div>
    <?php endif; ?>
  </div>
   <!-- Zapier Chatbot Embed -->
  <script async type='module' src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'></zapier-interfaces-chatbot-embed>
</body>

</html>