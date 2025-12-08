<?php


include "db.php";

if (session_status() === PHP_SESSION_NONE) session_start();
$member_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

// Redirect to login if not logged in
if (!$member_id) {
  header('Location: login.php?redirect=booking.php');
  exit;
}

// Determine experience based on tier
$is_stakeholder = ($user_type === 'stakeholder');

$is_cardholder = in_array($user_type, ['cardholder', 'member'], true);
$cardholder_discount_rate = 0.40;
$requires_addons_selection = $is_cardholder;

// Inputs
$room_type_id = isset($_GET['room_type_id']) ? (int)$_GET['room_type_id'] : (isset($_POST['room_type_id']) ? (int)$_POST['room_type_id'] : 0);
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : (isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0);
$check_in = $_POST['check_in'] ?? null;
$check_out = $_POST['check_out'] ?? null;
$guests = isset($_POST['guests']) ? (int)$_POST['guests'] : null;
$action = $_POST['action'] ?? null; // 'check' or 'confirm'

if (!$room_type_id && !$room_id) {
  die('No room type or room selected.');
}

// If a specific room id was provided but room_type_id missing, fetch it
$room = null;
if ($room_id && !$room_type_id) {
  $rs = $conn->prepare("SELECT r.id, r.room_number, r.floor, r.room_type_id, rt.name AS type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ? LIMIT 1");
  $rs->bind_param('i', $room_id);
  $rs->execute();
  $room = $rs->get_result()->fetch_assoc();
  if ($room) {
    $room_type_id = (int)$room['room_type_id'];
  } else {
    die('Requested room not found.');
  }
}

// Fetch room type
$stmt = $conn->prepare("SELECT id, name, description, base_price, max_occupancy, image, features FROM room_types WHERE id = ? AND status = 'Active'");
$stmt->bind_param('i', $room_type_id);
$stmt->execute();
$roomType = $stmt->get_result()->fetch_assoc();
if (! $roomType) {
  die('Room type not found or inactive.');
}

// Fetch add-on categories and options if they exist
$add_on_categories = [];
$add_on_options = [];
$addon_price_lookup = [];
$fallback_addons = [
  ['id' => -1, 'option_name' => 'Breakfast (per stay)', 'price' => 600],
  ['id' => -2, 'option_name' => 'Airport Pickup', 'price' => 1800],
  ['id' => -3, 'option_name' => 'Extra Bed (per night)', 'price' => 800],
];

$addon_tables = $conn->query("SHOW TABLES LIKE 'add_on_categories'");
if ($addon_tables && $addon_tables->num_rows) {
  $add_on_categories = $conn->query("SELECT id, name FROM add_on_categories")->fetch_all(MYSQLI_ASSOC);
  foreach ($add_on_categories as $cat) {
    $cid = (int) $cat['id'];
    $s = $conn->prepare("SELECT id, option_name, price, features FROM add_on_options WHERE category_id = ?");
    $s->bind_param('i', $cid);
    $s->execute();
    $options = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $add_on_options[$cid] = $options;
    foreach ($options as $opt) {
      $addon_price_lookup[(int) $opt['id']] = (float) $opt['price'];
    }
  }
} else {
  foreach ($fallback_addons as $addon) {
    $addon_price_lookup[(int) $addon['id']] = (float) $addon['price'];
  }
}

if (empty($addon_price_lookup)) {
  $requires_addons_selection = false;
}

// Helper: count available rooms
function count_available_rooms($conn, $room_type_id, $check_in = null, $check_out = null)
{
  $sql = "SELECT COUNT(r.id) AS cnt FROM rooms r WHERE r.room_type_id = ? AND r.status = 'Available'";
  $params = [$room_type_id];
  if ($check_in && $check_out) {
    $sql .= " AND r.id NOT IN (
      SELECT room_id FROM bookings WHERE booking_status IN ('Confirmed','Pending','Checked_In')
      AND NOT (check_out_date <= ? OR check_in_date >= ?)
    )";
    $params[] = $check_in;
    $params[] = $check_out;
    $sql .= " AND r.id NOT IN (
      SELECT room_id FROM room_availability WHERE available_date BETWEEN ? AND ? AND is_available = 'No'
    )";
    $params[] = $check_in;
    $params[] = $check_out;
  }
  $stmt = $conn->prepare($sql);
  if (!$stmt) return 0;
  $types = str_repeat('s', count($params));
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return (int)($row['cnt'] ?? 0);
}

function calculate_addons_total(array $selected_ids, array $price_lookup)
{
  $total = 0;
  foreach ($selected_ids as $id) {
    $id = (int) $id;
    if (isset($price_lookup[$id])) {
      $total += (float) $price_lookup[$id];
    }
  }
  return $total;
}

$available_count = null;
$booking_success = false;
$booking_error = null;
$booking_id = null;

if ($action === 'check' && $check_in && $check_out) {
  if ($room_id) {
    // check specific room availability
    $available_count = 0;
    $rstmt = $conn->prepare("SELECT id, status FROM rooms WHERE id = ? AND room_type_id = ? LIMIT 1");
    $rstmt->bind_param('ii', $room_id, $room_type_id);
    $rstmt->execute();
    $rrow = $rstmt->get_result()->fetch_assoc();
    if ($rrow && $rrow['status'] === 'Available') {
      // ensure no conflicting bookings
      $conf = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE room_id = ? AND booking_status IN ('Confirmed','Pending','Checked_In') AND NOT (check_out_date <= ? OR check_in_date >= ?)");
      $conf->bind_param('iss', $room_id, $check_in, $check_out);
      $conf->execute();
      $cres = $conf->get_result()->fetch_assoc();
      $blocked = (int)($cres['c'] ?? 0);
      // also check room_availability
      $bad = $conn->prepare("SELECT COUNT(*) AS c FROM room_availability WHERE room_id = ? AND available_date BETWEEN ? AND ? AND is_available = 'No'");
      $bad->bind_param('iss', $room_id, $check_in, $check_out);
      $bad->execute();
      $bres = $bad->get_result()->fetch_assoc();
      $baddays = (int)($bres['c'] ?? 0);
      if ($blocked === 0 && $baddays === 0) $available_count = 1;
    }
  } else {
    $available_count = count_available_rooms($conn, $room_type_id, $check_in, $check_out);
  }
}

if ($action === 'confirm' && $check_in && $check_out && $member_id) {
  $nights = (int)((strtotime($check_out) - strtotime($check_in)) / 86400);
  if ($nights <= 0) {
    $booking_error = 'Check-out must be after check-in.';
  } else {
    $selected_addons = isset($_POST['addon_ids']) ? array_map('intval', (array) $_POST['addon_ids']) : [];
    $selected_addons = array_values(array_filter($selected_addons, function ($id) use ($addon_price_lookup) {
      return isset($addon_price_lookup[$id]);
    }));
    if ($requires_addons_selection && empty($selected_addons)) {
      $booking_error = 'Please select at least one add-on before confirming.';
    }

    $addons_total = $is_cardholder ? calculate_addons_total($selected_addons, $addon_price_lookup) : 0;

    if (!$booking_error) {
      if ($room_id) {
        // validate specific room availability again
        $rstmt = $conn->prepare("SELECT id, status FROM rooms WHERE id = ? AND room_type_id = ? LIMIT 1");
        $rstmt->bind_param('ii', $room_id, $room_type_id);
        $rstmt->execute();
        $rrow = $rstmt->get_result()->fetch_assoc();
        if (! $rrow || $rrow['status'] !== 'Available') {
          $booking_error = 'Selected room is not available.';
        } else {
          $conf = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE room_id = ? AND booking_status IN ('Confirmed','Pending','Checked_In') AND NOT (check_out_date <= ? OR check_in_date >= ?)");
          $conf->bind_param('iss', $room_id, $check_in, $check_out);
          $conf->execute();
          $cres = $conf->get_result()->fetch_assoc();
          $blocked = (int)($cres['c'] ?? 0);
          $bad = $conn->prepare("SELECT COUNT(*) AS c FROM room_availability WHERE room_id = ? AND available_date BETWEEN ? AND ? AND is_available = 'No'");
          $bad->bind_param('iss', $room_id, $check_in, $check_out);
          $bad->execute();
          $bres = $bad->get_result()->fetch_assoc();
          $baddays = (int)($bres['c'] ?? 0);
          if ($blocked > 0 || $baddays > 0) {
            $booking_error = 'Selected room is not available for the chosen dates.';
          } else {
            $base_price = (float)$roomType['base_price'];
            $gross_total = ($base_price * $nights) + $addons_total;
            if ($is_stakeholder) {
              $total_price = 0;
            } else {
              $discount_amount = $is_cardholder ? ($gross_total * $cardholder_discount_rate) : 0;
              $total_price = $gross_total - $discount_amount;
            }

            // Both stakeholders and cardholders get auto-confirmed bookings
            $booking_status = 'Confirmed';
            $payment_status = $is_stakeholder ? 'Completed' : 'Pending';

            $bstmt = $conn->prepare("INSERT INTO bookings (member_id, room_id, check_in_date, check_out_date, num_guests, booking_status, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $bstmt->bind_param('iissidss', $member_id, $room_id, $check_in, $check_out, $guests, $total_price, $booking_status, $payment_status);
            if ($bstmt->execute()) {
              $booking_id = $bstmt->insert_id;
              $booking_success = true;
            } else {
              $booking_error = 'Failed to create booking: ' . $conn->error;
            }
          }
        }
      } else {
        // fallback: pick any available room as before
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
        if (! $rrow) {
          $booking_error = 'No rooms available for the selected dates.';
        } else {
          $room_id = (int)$rrow['id'];
          $base_price = (float)$roomType['base_price'];
          $gross_total = ($base_price * $nights) + $addons_total;
          if ($is_stakeholder) {
            $total_price = 0;
          } else {
            $discount_amount = $is_cardholder ? ($gross_total * $cardholder_discount_rate) : 0;
            $total_price = $gross_total - $discount_amount;
          }

          $booking_status = 'Confirmed';
          $payment_status = $is_stakeholder ? 'Completed' : 'Pending';

          $bstmt = $conn->prepare("INSERT INTO bookings (member_id, room_id, check_in_date, check_out_date, num_guests, booking_status, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          $bstmt->bind_param('iissidss', $member_id, $room_id, $check_in, $check_out, $guests, $total_price, $booking_status, $payment_status);
          if ($bstmt->execute()) {
            $booking_id = $bstmt->insert_id;
            $booking_success = true;
          } else {
            $booking_error = 'Failed to create booking: ' . $conn->error;
          }
        }
      }
    }
  }
}

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Book — <?php echo htmlspecialchars($roomType['name']); ?></title>

  <!-- ====== Fonts (system-safe fallback) ====== -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <style>
    /* ---------------------------
       Premium theme (all in-file for copy/paste)
       --------------------------- */

    :root {
      --bg: #f8f6fc;
      --card: #ffffff;
      --muted: #6b7280;
      --primary: #7c3aed;
      --primary-dark: #6d28d9;
      --accent: #ec4899;
      --accent1: linear-gradient(135deg, var(--primary), var(--primary-dark));
      --glass-bg: rgba(255, 255, 255, 0.55);
      --glass-border: rgba(255, 255, 255, 0.6);
      --glass-shadow: rgba(20, 20, 20, 0.06);
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: radial-gradient(1200px 400px at 10% 10%, rgba(139, 92, 246, 0.06), transparent 8%),
        radial-gradient(900px 300px at 90% 90%, rgba(0, 0, 0, 0.02), transparent 10%),
        var(--bg);
      color: #111827;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      min-height: 100vh;
    }

    /* Header */
    .hero {
      padding: 56px 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      margin-bottom: 24px;
    }

    .hero .glass {
      width: 100%;
      max-width: 1180px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 28px 30px;
      border-radius: 14px;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.45), rgba(255, 255, 255, 0.35));
      backdrop-filter: blur(8px) saturate(120%);
      border: 1px solid rgba(255, 255, 255, 0.55);
      box-shadow: 0 8px 30px rgba(12, 17, 23, 0.06);
    }

    .hero .left {
      display: flex;
      gap: 18px;
      align-items: center;
    }

    .hotel-title {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.2px;
    }

    .hotel-sub {
      color: var(--muted);
      font-size: 14px;
    }

    /* Layout */
    .container {
      max-width: 1180px;
      margin: 0 auto;
      padding: 18px 20px 80px;
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 28px;
    }

    @media (max-width:1100px) {
      .container {
        grid-template-columns: 1fr;
      }

      .summary-sidebar {
        order: -1
      }
    }

    /* Main card */
    .card {
      background: var(--card);
      border-radius: 14px;
      padding: 22px;
      box-shadow: 0 10px 30px rgba(20, 20, 30, 0.04);
    }

    .room-header {
      display: flex;
      gap: 18px;
      align-items: center;
      margin-bottom: 8px;
    }

    .room-image {
      height: 90px;
      width: 150px;
      border-radius: 10px;
      object-fit: cover;
      flex-shrink: 0;
      box-shadow: 0 6px 18px rgba(10, 10, 30, 0.06);
    }

    .room-meta h2 {
      margin: 0;
      font-size: 20px
    }

    .room-meta p {
      margin: 4px 0;
      color: var(--muted);
      font-size: 14px
    }

    /* Wizard */
    .wizard {
      margin-top: 16px;
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .step {
      flex: 1;
      min-width: 160px;
      padding: 12px;
      border-radius: 10px;
      background: transparent;
      border: 1px solid rgba(15, 20, 30, 0.04);
      display: flex;
      gap: 12px;
      align-items: center;
      cursor: pointer;
      transition: all .28s ease;
    }

    .step .num {
      width: 36px;
      height: 36px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #fff
    }

    .step .title {
      font-size: 15px;
      font-weight: 600
    }

    .step.inactive {
      opacity: 0.9
    }

    .step.active {
      background: linear-gradient(135deg, #6b21a8, #8b5cf6);
      color: #fff;
      transform: translateY(-6px);
      box-shadow: 0 10px 30px rgba(107, 33, 168, 0.16);
      border: none;
    }

    .step .num {
      background: rgba(0, 0, 0, 0.06);
    }

    .step.active .num {
      background: transparent;
      font-weight: 800
    }

    /* Form area */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 16px
    }

    @media (max-width:700px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    label {
      font-weight: 600;
      font-size: 13px;
      color: #111827
    }

    input[type="text"],
    input[type="number"],
    input[type="date"] {
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px solid rgba(15, 20, 30, 0.08);
      font-size: 14px;
    }

    .input-inline {
      display: flex;
      gap: 8px
    }

    .btn {
      padding: 12px 16px;
      border-radius: 10px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      background: var(--accent1);
      color: #fff;
      transition: transform .18s ease, box-shadow .18s ease;
    }

    .btn.secondary {
      background: transparent;
      border: 1px solid rgba(15, 20, 30, 0.06);
      color: var(--muted);
      font-weight: 600
    }

    .btn:active {
      transform: translateY(1px)
    }

    /* Floating date picker */
    .date-input {
      position: relative;
    }

    .floating-calendar {
      position: absolute;
      top: 54px;
      left: 0;
      width: 320px;
      background: rgba(255, 255, 255, 0.98);
      border-radius: 12px;
      box-shadow: 0 12px 30px rgba(10, 10, 30, 0.08);
      padding: 14px;
      z-index: 60;
      display: none;
    }

    .floating-calendar.visible {
      display: block
    }

    .floating-calendar .row {
      display: flex;
      gap: 8px
    }

    .cal-presets {
      display: flex;
      gap: 8px;
      margin-top: 10px;
      flex-wrap: wrap
    }

    .preset {
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid rgba(15, 20, 30, 0.06);
      cursor: pointer;
      font-size: 13px
    }

    /* Add-ons */
    .addons {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 12px
    }

    .addon {
      padding: 10px;
      border-radius: 10px;
      border: 1px solid rgba(15, 20, 30, 0.04);
      display: flex;
      align-items: center;
      gap: 10px
    }

    .addon input {
      transform: scale(1.1)
    }

    /* Summary sidebar (glass) */
    .summary-sidebar {
      height: fit-content;
      border-radius: 14px;
      padding: 18px;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.35));
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.6);
      box-shadow: 0 10px 30px rgba(10, 10, 30, 0.06);
      position: sticky;
      top: 28px;
    }

    .summary-title {
      font-weight: 800;
      margin-bottom: 8px
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px dashed rgba(15, 20, 30, 0.03)
    }

    .summary-total {
      font-size: 20px;
      font-weight: 800;
      margin-top: 12px
    }

    /* Alerts */
    .alert {
      padding: 12px;
      border-radius: 10px;
      margin-bottom: 12px
    }

    .alert.success {
      background: #eaf6f1;
      color: #056a47;
      border: 1px solid #c8f0e3
    }

    .alert.error {
      background: #fff0f0;
      color: #8a1b1b;
      border: 1px solid #ffd2d2
    }

    .empty-state {
      padding: 14px;
      border-radius: 10px;
      background: rgba(15, 20, 30, 0.03);
      color: var(--muted);
    }

    /* small helpers */
    .muted {
      color: var(--muted);
      font-size: 13px
    }

    .tiny {
      font-size: 12px;
      color: var(--muted)
    }

    .center {
      display: flex;
      justify-content: center;
      align-items: center
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <header class="hero">
    <div class="glass">
      <div class="left">
        <div>
          <?php
          // image for header small icon
          $thumbImage = '';
          if (!empty($roomType['image']) && file_exists(__DIR__ . '/images/rooms/' . $roomType['image'])) {
            $thumbImage = 'images/rooms/' . $roomType['image'];
          } else {
            $thumbImage = 'images/rooms/default-room.jpg';
          }
          ?>
          <img src="<?php echo htmlspecialchars($thumbImage) ?>" alt="room"
            style="height:66px;width:110px;border-radius:10px;object-fit:cover;">
        </div>
        <div>
          <div class="hotel-title"><?php echo htmlspecialchars($roomType['name']); ?></div>
          <div class="hotel-sub"><?php echo htmlspecialchars($roomType['description']); ?></div>
        </div>
      </div>

      <div class="right tiny muted">
        Price/Night: <strong style="margin-left:8px;font-size:16px;color:#3b0256">BDT
          <?php echo number_format($roomType['base_price'], 2) ?></strong>
      </div>
    </div>
  </header>

  <main class="container">
    <!-- Left / Main -->
    <section class="card">

      <?php if ($booking_success): ?>
        <div class="alert success">
          ✅ Booking confirmed successfully! Booking ID: <?php echo $booking_id; ?><br>
          <?php if ($is_stakeholder): ?>
            Your complimentary stakeholder stay is confirmed. <a href="customerBooking.php">View your bookings</a>
          <?php else: ?>
            A 40% cardholder discount has been applied. Please complete payment from <a href="customerBooking.php">your
              bookings page</a>.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php if ($booking_error): ?><div class="alert error"><?php echo htmlspecialchars($booking_error); ?></div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="room-header">
        <img src="<?php echo htmlspecialchars($thumbImage) ?>" class="room-image" />
        <div class="room-meta">
          <h2><?php echo htmlspecialchars($roomType['name']); ?> <?php if (!empty($room)): ?> — Room
              <?= htmlspecialchars($room['room_number']) ?><?php endif; ?></h2>
          <p class="muted">Max occupancy: <?php echo (int)$roomType['max_occupancy']; ?> • BDT
            <?php echo number_format($roomType['base_price'], 2); ?> / night</p>
        </div>
      </div>

      <!-- Wizard steps -->
      <div class="wizard" id="wizardSteps">
        <div class="step active" data-step="1">
          <div class="num">1</div>
          <div>
            <div class="title">Select Dates & Guests</div>
            <div class="tiny">Choose check-in/out & guests</div>
          </div>
        </div>
        <div class="step inactive" data-step="2">
          <div class="num">2</div>
          <div>
            <div class="title">Review & Add-ons</div>
            <div class="tiny">Optional extras & summary</div>
          </div>
        </div>
        <div class="step inactive" data-step="3">
          <div class="num">3</div>
          <div>
            <div class="title">Confirm Booking</div>
            <div class="tiny">Create reservation (Pay later)</div>
          </div>
        </div>
      </div>

      <!-- Step content -->
      <div id="stepContents" style="margin-top:18px">
        <!-- STEP 1 -->
        <div class="step-body" data-step="1">
          <form id="checkForm" method="POST" action="booking.php">
            <input type="hidden" name="room_type_id" value="<?php echo (int)$room_type_id; ?>">
            <?php if (!empty($room_id)): ?><input type="hidden" name="room_id"
                value="<?= (int)$room_id ?>"><?php endif; ?>

            <div class="form-grid">
              <div class="field date-input">
                <label>Check-in</label>
                <input id="checkInInput" name="check_in" type="text" placeholder="Select check-in" autocomplete="off"
                  readonly value="<?php echo htmlspecialchars($check_in); ?>" />
              </div>

              <div class="field date-input">
                <label>Check-out</label>
                <input id="checkOutInput" name="check_out" type="text" placeholder="Select check-out" autocomplete="off"
                  readonly value="<?php echo htmlspecialchars($check_out); ?>" />
              </div>

              <div class="field">
                <label>Guests</label>
                <input type="number" id="guestsInput" name="guests" min="1"
                  max="<?php echo (int)$roomType['max_occupancy']; ?>"
                  value="<?php echo htmlspecialchars($guests ?? 1); ?>">
                <div class="tiny">Max <?php echo (int)$roomType['max_occupancy']; ?> guests</div>
              </div>

              <div class="field center" style="align-items:flex-end">
                <!-- Check availability triggers full page post to get availability_count rendered -->
                <button type="submit" name="action" value="check" class="btn" style="width:100%">Check
                  availability</button>
              </div>
            </div>
          </form>

          <!-- Floating calendar popup -->
          <div class="floating-calendar" id="floatingCalendar" aria-hidden="true">
            <div style="font-weight:700">Pick dates</div>
            <div class="row" style="margin-top:10px">
              <div style="flex:1">
                <label class="tiny">Check-in</label>
                <input id="calCheckIn" type="date"
                  style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(15,20,30,0.06)" />
              </div>
              <div style="flex:1">
                <label class="tiny">Check-out</label>
                <input id="calCheckOut" type="date"
                  style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(15,20,30,0.06)" />
              </div>
            </div>

            <div class="cal-presets">
              <div class="preset" data-days="1">1 night</div>
              <div class="preset" data-days="2">2 nights</div>
              <div class="preset" data-days="3">3 nights</div>
              <div class="preset" data-days="7">7 nights</div>
              <div class="preset" id="clearDates">Clear</div>
            </div>

            <div style="display:flex; gap:10px; margin-top:12px">
              <button class="btn" id="applyDates">Apply</button>
              <button class="btn secondary" id="closeCal">Close</button>
            </div>
          </div>

          <?php if ($available_count !== null): ?>
            <div style="margin-top:14px">
              <div class="<?php echo $available_count > 0 ? 'alert success' : 'alert error' ?>">
                <?php if ($available_count > 0): ?>
                  ✅ Rooms available for those dates: <strong><?php echo (int)$available_count; ?></strong>
                <?php else: ?>
                  ✖ No rooms available for the selected dates. Try different dates or room types.
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>

        <!-- STEP 2 -->
        <div class="step-body" data-step="2" style="display:none">
          <div>
            <h3 style="margin:0 0 8px 0">Add-ons & Preferences</h3>
            <?php if ($is_cardholder): ?>
              <div class="tiny muted">Select add-ons to include in your booking (required for cardholders).</div>

              <div class="addons" id="addonsList">
                <?php
                if (empty($add_on_categories)) {
                  foreach ($fallback_addons as $addon) {
                    echo '<label class="addon"><input type="checkbox" class="addon-checkbox" data-price="' . htmlspecialchars($addon['price']) . '" data-id="' . htmlspecialchars($addon['id']) . '"> <div><strong>' . htmlspecialchars($addon['option_name']) . '</strong><div class="tiny muted">BDT ' . number_format($addon['price'], 2) . '</div></div></label>';
                  }
                } else {
                  foreach ($add_on_categories as $cat) {
                    $cid = $cat['id'];
                    if (!empty($add_on_options[$cid])) {
                      foreach ($add_on_options[$cid] as $opt) {
                        $p = (float)$opt['price'];
                        echo '<label class="addon"><input type="checkbox" class="addon-checkbox" data-price="' . htmlspecialchars($p) . '" data-id="' . htmlspecialchars($opt['id']) . '"> <div><strong>' . htmlspecialchars($opt['option_name']) . '</strong><div class="tiny muted">BDT ' . number_format($p, 2) . '</div></div></label>';
                      }
                    }
                  }
                }
                ?>
              </div>
            <?php else: ?>
              <p class="empty-state">As a stakeholder, your stays are complimentary—no add-ons or payments required.</p>
            <?php endif; ?>

            <div style="margin-top:18px; display:flex; gap:12px;">
              <button class="btn" id="toConfirmBtn">Proceed to Confirm</button>
              <button class="btn secondary" id="backToStep1">Back</button>
            </div>
          </div>
        </div>

        <!-- STEP 3 -->
        <div class="step-body" data-step="3" style="display:none">
          <h3 style="margin:0 0 8px 0">Confirm your booking</h3>
          <div class="tiny muted">Review details below and then confirm booking. Payment can be completed later from
            your dashboard.</div>

          <div style="margin-top:12px">
            <div style="display:flex; gap:12px; align-items:center;">
              <div style="flex:1">
                <div class="tiny muted">Room</div>
                <div><strong><?php echo htmlspecialchars($roomType['name']); ?></strong></div>
              </div>
              <div style="text-align:right">
                <div class="tiny muted">Price/night</div>
                <div><strong>BDT <?php echo number_format($roomType['base_price'], 2) ?></strong></div>
              </div>
            </div>
          </div>

          <div style="margin-top:18px">
            <!-- Confirm form posts to server with action=confirm -->
            <form id="confirmForm" method="POST" action="booking.php">
              <input type="hidden" name="room_type_id" value="<?php echo (int)$room_type_id; ?>">
              <?php if (!empty($room_id)): ?><input type="hidden" name="room_id"
                  value="<?= (int)$room_id ?>"><?php endif; ?>
              <input type="hidden" name="check_in" id="final_check_in"
                value="<?php echo htmlspecialchars($check_in); ?>">
              <input type="hidden" name="check_out" id="final_check_out"
                value="<?php echo htmlspecialchars($check_out); ?>">
              <input type="hidden" name="guests" id="final_guests"
                value="<?php echo htmlspecialchars($guests ?? 1); ?>">
              <!-- selected add-ons (populate via JS) -->
              <div id="selectedAddonsInputs"></div>

              <div style="display:flex; gap:12px; margin-top:12px">
                <button type="submit" name="action" value="confirm" class="btn">
                  <?php echo $is_stakeholder ? 'Confirm Booking' : 'Confirm & Pay Later'; ?>
                </button>
                <button type="button" class="btn secondary" id="backToStep2">Back</button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </section>

    <!-- Right / Summary Sidebar -->
    <aside class="summary-sidebar card">
      <div class="summary-title">Booking Summary</div>

      <div style="display:flex; gap:12px; align-items:center; margin:8px 0">
        <img src="<?php echo htmlspecialchars($thumbImage) ?>"
          style="height:64px;width:92px;border-radius:8px;object-fit:cover">
        <div>
          <div style="font-weight:700"><?php echo htmlspecialchars($roomType['name']); ?></div>
          <div class="tiny muted"><?php if (!empty($room)): ?>Room <?= htmlspecialchars($room['room_number']) ?> • Floor
            <?= htmlspecialchars($room['floor']) ?><?php endif; ?></div>
        </div>
      </div>

      <div style="margin-top:10px">
        <div class="summary-row">
          <div class="tiny">Check-in</div>
          <div id="summaryCheckIn" class="tiny muted"><?php echo htmlspecialchars($check_in) ?: '—' ?></div>
        </div>
        <div class="summary-row">
          <div class="tiny">Check-out</div>
          <div id="summaryCheckOut" class="tiny muted"><?php echo htmlspecialchars($check_out) ?: '—' ?></div>
        </div>
        <div class="summary-row">
          <div class="tiny">Nights</div>
          <div id="summaryNights" class="tiny muted">0</div>
        </div>
        <div class="summary-row">
          <div class="tiny">Guests</div>
          <div id="summaryGuests" class="tiny muted"><?php echo htmlspecialchars($guests ?? 1) ?></div>
        </div>
      </div>

      <div style="margin-top:12px">
        <div class="tiny muted">Room subtotal</div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px">
          <div>BDT <span id="roomNightPrice"><?php echo number_format($roomType['base_price'], 2) ?></span> <span
              class="tiny muted">/ night</span></div>
          <div class="tiny muted">x <span id="roomNightsCount">0</span></div>
        </div>

        <div style="margin-top:12px">
          <div class="tiny muted">Add-ons</div>
          <div id="summaryAddons" style="margin-top:8px">
            <div class="tiny muted">— none selected —</div>
          </div>
        </div>

        <div style="margin-top:12px">
          <div class="summary-row">
            <div class="tiny">Subtotal</div>
            <div id="summarySubtotal">BDT 0.00</div>
          </div>
          <?php if ($is_cardholder): ?>
            <div class="summary-row">
              <div class="tiny">Cardholder discount (40%)</div>
              <div id="summaryDiscount">- BDT 0.00</div>
            </div>
          <?php endif; ?>
          <div class="summary-row">
            <div class="tiny">Tax (0%)</div>
            <div id="summaryTax">BDT 0.00</div>
          </div>
          <div class="summary-total">Total <div style="font-weight:900" id="summaryTotal">BDT 0.00</div>
          </div>
        </div>
      </div>

      <div style="margin-top:14px" class="tiny muted">
        <?php if ($is_stakeholder): ?>
          After confirming, your reservation will be auto-confirmed and marked as fully paid—enjoy your complimentary
          stay.
        <?php else: ?>
          After confirming, your reservation will be <strong>Confirmed</strong> with a 40% discount applied. Complete the
          remaining payment from your bookings page.
        <?php endif; ?>
      </div>
    </aside>

  </main>

  <!-- JS: wizard, floating calendar, summary calculations -->
  <script>
    (function() {
      // ---------------------------
      // Helpers
      // ---------------------------
      const $ = (sel, el = document) => el.querySelector(sel);
      const $$ = (sel, el = document) => Array.from(el.querySelectorAll(sel));

      // Elements
      const steps = $$('.step');
      const stepBodies = $$('.step-body');
      let activeStep = 1;
      const isStakeholder = <?php echo $is_stakeholder ? 'true' : 'false'; ?>;
      const isCardholder = <?php echo $is_cardholder ? 'true' : 'false'; ?>;
      const discountRate = isCardholder ? <?php echo $cardholder_discount_rate; ?> : 0;
      const requiresAddons = <?php echo $requires_addons_selection ? 'true' : 'false'; ?>;

      const setActiveStep = (n) => {
        activeStep = n;
        steps.forEach(s => s.classList.toggle('active', parseInt(s.dataset.step) === n));
        stepBodies.forEach(b => b.style.display = (parseInt(b.dataset.step) === n ? 'block' : 'none'));
      };

      // allow clicking steps to navigate backward (but not skip ahead of available)
      steps.forEach(s => {
        s.addEventListener('click', () => {
          const stepNum = parseInt(s.dataset.step);
          // Only allow moving to step <= current advanced step or equal to 1
          // For simplicity allow any click; client-side will validate before final confirm
          setActiveStep(stepNum);
        });
      });

      // Floating calendar logic
      const checkInInput = $('#checkInInput');
      const checkOutInput = $('#checkOutInput');
      const floatingCal = $('#floatingCalendar');
      const calIn = $('#calCheckIn');
      const calOut = $('#calCheckOut');
      const applyBtn = $('#applyDates');
      const closeCalBtn = $('#closeCal');
      const presets = Array.from(document.querySelectorAll('.preset'));
      const clearDates = $('#clearDates');

      const openCalendar = (anchor) => {
        // position near anchor: handled by CSS (absolute inside its parent)
        floatingCal.classList.add('visible');
        floatingCal.setAttribute('aria-hidden', 'false');
      };
      const closeCalendar = () => {
        floatingCal.classList.remove('visible');
        floatingCal.setAttribute('aria-hidden', 'true');
      };

      // Show calendar when either date input focused
      [checkInInput, checkOutInput].forEach(el => {
        el.addEventListener('click', (e) => {
          // set calendar dates from inputs if present
          if (checkInInput.value) calIn.value = isoFromDisplay(checkInInput.value);
          if (checkOutInput.value) calOut.value = isoFromDisplay(checkOutInput.value);
          openCalendar();
        });
      });

      // Apply
      applyBtn.addEventListener('click', () => {
        if (!calIn.value || !calOut.value) {
          alert('Please select check-in and check-out dates.');
          return;
        }
        if (new Date(calOut.value) <= new Date(calIn.value)) {
          alert('Check-out must be after check-in.');
          return;
        }
        checkInInput.value = displayFromIso(calIn.value);
        checkOutInput.value = displayFromIso(calOut.value);
        // Sync hidden inputs for server submission: they already use name attributes on inputs in forms with text values.
        closeCalendar();
        updateSummaryDates();
      });

      closeCalBtn.addEventListener('click', closeCalendar);

      presets.forEach(p => {
        p.addEventListener('click', () => {
          const d = parseInt(p.dataset.days || '0', 10);
          if (d > 0) {
            const start = new Date();
            const end = new Date();
            end.setDate(start.getDate() + d);
            calIn.value = isoDate(start);
            calOut.value = isoDate(end);
          } else if (p.id === 'clearDates') {
            calIn.value = '';
            calOut.value = '';
            checkInInput.value = '';
            checkOutInput.value = '';
            updateSummaryDates();
            closeCalendar();
          }
        });
      });

      // Utility functions for date formatting
      function isoDate(dt) {
        return dt.toISOString().slice(0, 10);
      }

      function displayFromIso(iso) {
        // Show human-friendly date: YYYY-MM-DD -> YYYY-MM-DD (you can change)
        return iso;
      }

      function isoFromDisplay(display) {
        return display; // since we keep YYYY-MM-DD
      }

      function displayToIso(display) {
        return display; // same
      }

      // summary calculations and price logic
      const basePrice = parseFloat("<?php echo floatval($roomType['base_price']); ?>");
      const summaryCheckIn = $('#summaryCheckIn');
      const summaryCheckOut = $('#summaryCheckOut');
      const summaryNights = $('#summaryNights');
      const summaryGuests = $('#summaryGuests');
      const roomNightPrice = $('#roomNightPrice');
      const roomNightsCount = $('#roomNightsCount');
      const summaryAddons = $('#summaryAddons');
      const summarySubtotal = $('#summarySubtotal');
      const summaryTotal = $('#summaryTotal');
      const summaryTax = $('#summaryTax');
      const summaryDiscount = document.getElementById('summaryDiscount');

      roomNightPrice.textContent = basePrice.toFixed(2);

      function daysBetween(a, b) {
        const d1 = new Date(a);
        const d2 = new Date(b);
        const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
        return diff > 0 ? diff : 0;
      }

      function updateSummaryDates() {
        const ci = checkInInput.value;
        const co = checkOutInput.value;
        summaryCheckIn.textContent = ci || '—';
        summaryCheckOut.textContent = co || '—';
        const nights = (ci && co) ? daysBetween(ci, co) : 0;
        summaryNights.textContent = nights;
        roomNightsCount.textContent = nights;
        // calculate subtotal
        const guestCount = parseInt($('#guestsInput').value || '1', 10);
        summaryGuests.textContent = guestCount;
        recalcTotals();
      }

      // Addons handling
      const addonCheckboxes = Array.from(document.querySelectorAll('.addon-checkbox'));
      addonCheckboxes.forEach(cb => {
        cb.addEventListener('change', recalcTotals);
      });

      function recalcTotals() {
        const nights = parseInt(summaryNights.textContent || '0', 10);
        const guests = parseInt(summaryGuests.textContent || '1', 10);
        let roomTotal = basePrice * nights;
        // some add-ons may be per-night or per-stay — we don't have that info in examples, so treat as flat per booking
        let addonsTotal = 0;
        let selected = [];
        addonCheckboxes.forEach(cb => {
          if (cb.checked) {
            const p = parseFloat(cb.dataset.price || '0');
            addonsTotal += p;
            selected.push({
              id: cb.dataset.id,
              price: p,
              label: cb.parentElement.textContent.trim()
            });
          }
        });
        // If you wanted add-ons per-night, multiply by nights as necessary.

        // show addons list
        summaryAddons.innerHTML = '';
        if (selected.length === 0) {
          const emptyCopy = isStakeholder ? 'Complimentary benefits included' : '— none selected —';
          summaryAddons.innerHTML = '<div class="tiny muted">' + emptyCopy + '</div>';
        } else {
          selected.forEach(s => {
            const div = document.createElement('div');
            div.className = 'tiny';
            div.textContent = s.label.replace(/\s+/g, ' ').trim() + ' — BDT ' + Number(s.price).toFixed(2);
            summaryAddons.appendChild(div);
          });
        }

        const gross = roomTotal + addonsTotal;
        let discountAmount = 0;
        if (discountRate > 0) {
          discountAmount = gross * discountRate;
        }
        const subtotalDisplay = isStakeholder ? 0 : gross;
        const totalDisplay = isStakeholder ? 0 : (gross - discountAmount);
        const tax = 0;

        summarySubtotal.textContent = 'BDT ' + Number(subtotalDisplay).toFixed(2);
        summaryTax.textContent = 'BDT ' + Number(tax).toFixed(2);
        if (summaryDiscount) {
          summaryDiscount.textContent = '- BDT ' + Number(isStakeholder ? 0 : discountAmount).toFixed(2);
        }
        summaryTotal.textContent = 'BDT ' + Number(totalDisplay + tax).toFixed(2);

        // Put selected addons in confirm form when moving to confirm
        const selectedInputsDiv = $('#selectedAddonsInputs');
        if (selectedInputsDiv) {
          selectedInputsDiv.innerHTML = '';
          selected.forEach(s => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'addon_ids[]';
            inp.value = s.id;
            selectedInputsDiv.appendChild(inp);
          });
        }

        // Update hidden final input values each time
        $('#final_check_in').value = checkInInput.value || '';
        $('#final_check_out').value = checkOutInput.value || '';
        $('#final_guests').value = $('#guestsInput').value || '1';
      }

      // Wire navigation buttons between steps
      $('#toConfirmBtn').addEventListener('click', () => {
        // Basic validation: need dates and availability > 0
        if (!checkInInput.value || !checkOutInput.value) {
          alert('Please choose check-in and check-out dates.');
          setActiveStep(1);
          return;
        }
        // if the server has returned availability_count, we can check it
        <?php if ($available_count !== null): ?>
          const serverAvailable = <?php echo (int)$available_count; ?>;
          if (serverAvailable <= 0) {
            alert('No rooms available for selected dates. Please pick different dates or check another room type.');
            setActiveStep(1);
            return;
          }
        <?php endif; ?>
        if (requiresAddons) {
          const hasAddon = addonCheckboxes.some(cb => cb.checked);
          if (!hasAddon) {
            alert('Please select at least one add-on to continue.');
            setActiveStep(2);
            return;
          }
        }
        setActiveStep(3);
        recalcTotals();
      });

      $('#backToStep1').addEventListener('click', () => setActiveStep(1));
      $('#backToStep2').addEventListener('click', () => setActiveStep(2));

      // When clicking "Proceed to Confirm" we should ensure selected addons are captured and total is updated. (Handler above moves to step 3)
      // open calendar when user clicks inputs
      // Apply initial summary values from server-rendered values:
      (function initFromServer() {
        // if server rendered check_in/out, put them in inputs
        const serverCI = "<?php echo htmlspecialchars($check_in ?? ''); ?>";
        const serverCO = "<?php echo htmlspecialchars($check_out ?? ''); ?>";
        if (serverCI) checkInInput.value = serverCI;
        if (serverCO) checkOutInput.value = serverCO;
        updateSummaryDates();
      })();

      // Small UX: clicking outside floating calendar closes it
      document.addEventListener('click', (e) => {
        const cal = floatingCal;
        if (!cal.contains(e.target) && !checkInInput.contains(e.target) && !checkOutInput.contains(e.target)) {
          closeCalendar();
        }
      });

      // convert click on checkInInput to show calendar anchored near it
      // We'll position calendar by placing it inside parent (already absolute)
      // To let the calendar open above if near bottom, simple approach kept.

      // Expose updateSummaryDates to window for safety (not required)
      window.updateSummaryDates = updateSummaryDates;

      // If user clicked check availability (server returned available_count), we want to show Step 2 automatically if available.
      <?php if ($available_count !== null && $available_count > 0): ?>
        // After page load, move to step 2 so user can select add-ons
        setTimeout(() => setActiveStep(2), 300);
      <?php endif; ?>

      // Clicking a step will switch to it (we allowed earlier)
      // Ensure date inputs open calendar when clicking - already done above

      // Support keyboard escape to close calendar
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeCalendar();
      });

    })();
  </script>

</body>

</html>