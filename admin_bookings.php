<?php
session_start();
require_once __DIR__ . '/db.php';

// Simple access control: only allow logged-in admins
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
  header('Location: login.php');
  exit;
}

$conn->set_charset('utf8mb4');
$adminName = $_SESSION['user_name'] ?? 'Admin';

// Fetch all bookings with filters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

$whereConditions = [];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
  $whereConditions[] = "b.booking_status = ?";
  $params[] = $statusFilter;
  $types .= 's';
}

if (!empty($searchQuery)) {
  $whereConditions[] = "(m.name LIKE ? OR m.email LIKE ? OR r.room_number LIKE ?)";
  $searchParam = "%{$searchQuery}%";
  $params[] = $searchParam;
  $params[] = $searchParam;
  $params[] = $searchParam;
  $types .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$bookingsQuery = "
  SELECT 
    b.id,
    b.check_in_date,
    b.check_out_date,
    b.booking_status,
    b.payment_status,
    b.cancellation_status,
    b.total_price,
    b.num_guests,
    b.created_at,
    m.name AS member_name,
    m.email AS member_email,
    r.room_number,
    r.floor,
    rt.name AS room_type_name,
    COALESCE(SUM(p.amount), 0) AS total_paid
  FROM bookings b
  JOIN members m ON b.member_id = m.id
  JOIN rooms r ON b.room_id = r.id
  JOIN room_types rt ON r.room_type_id = rt.id
  LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_status = 'Success'
  {$whereClause}
  GROUP BY b.id
  ORDER BY b.created_at DESC
";

$stmt = $conn->prepare($bookingsQuery);
if ($types && $params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$allBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function statusClass(string $status): string
{
  $normalized = strtolower($status);
  if (in_array($normalized, ['confirmed', 'checked_in', 'checked_out'], true)) {
    return 'status status-success';
  }
  if ($normalized === 'pending') {
    return 'status status-pending';
  }
  if ($normalized === 'cancelled') {
    return 'status status-cancelled';
  }
  return 'status';
}

$currentPage = 'admin_bookings.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Bookings | EliteStay Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="dashboard.css">
</head>

<body>
  <?php include 'components/admin_sidebar.php'; ?>

  <main class="main-content">
    <header class="page-header">
      <div class="welcome-wrapper">
        <p class="eyebrow">Reservations</p>
        <h1>All Bookings</h1>
        <p class="muted">Manage and track all hotel reservations</p>
      </div>
      <div class="header-actions">
        <a href="booking.php" class="btn primary"><i class="fas fa-plus"></i> New reservation</a>
        <a href="admin_dashboard.php" class="btn ghost"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
      </div>
    </header>

    <section class="recent-activity">
      <div class="table-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <h3>All Reservations</h3>
          <div style="display:flex; gap:1rem; align-items:center;">
            <form method="GET" action="admin_bookings.php" style="display:flex; gap:0.5rem;">
              <input type="text" name="search" placeholder="Search by name, email, room..."
                value="<?= htmlspecialchars($searchQuery) ?>"
                style="padding:0.5rem; border:1px solid #ddd; border-radius:6px;">
              <select name="status" style="padding:0.5rem; border:1px solid #ddd; border-radius:6px;">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Confirmed" <?= $statusFilter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="Checked_In" <?= $statusFilter === 'Checked_In' ? 'selected' : '' ?>>Checked In</option>
                <option value="Checked_Out" <?= $statusFilter === 'Checked_Out' ? 'selected' : '' ?>>Checked Out
                </option>
                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
              </select>
              <button type="submit" class="btn ghost sm"><i class="fas fa-search"></i> Filter</button>
              <?php if ($statusFilter !== 'all' || !empty($searchQuery)): ?>
                <a href="admin_bookings.php" class="btn soft sm"><i class="fas fa-times"></i> Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Guest</th>
              <th>Email</th>
              <th>Room</th>
              <th>Check-in</th>
              <th>Check-out</th>
              <th>Guests</th>
              <th>Status</th>
              <th>Payment Status</th>
              <th>Total Paid</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allBookings)): ?>
              <tr>
                <td colspan="12" class="table-empty">No bookings found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($allBookings as $booking): ?>
                <tr>
                  <td>#<?php echo (int) $booking['id']; ?></td>
                  <td><?php echo htmlspecialchars($booking['member_name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['member_email']); ?></td>
                  <td><?php echo htmlspecialchars($booking['room_type_name']); ?> -
                    <?php echo htmlspecialchars($booking['room_number']); ?></td>
                  <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                  <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                  <td><?php echo (int) $booking['num_guests']; ?></td>
                  <td>
                    <span class="<?php echo statusClass($booking['booking_status']); ?>">
                      <?php echo htmlspecialchars($booking['booking_status']); ?>
                    </span>
                    <?php if ($booking['cancellation_status'] === 'Requested'): ?>
                      <br><small style="color:#ffc107;">Cancellation requested</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span
                      class="status <?= $booking['payment_status'] === 'Completed' ? 'status-success' : ($booking['payment_status'] === 'Pending' ? 'status-pending' : '') ?>">
                      <?php echo htmlspecialchars($booking['payment_status']); ?>
                    </span>
                  </td>
                  <td>
                    <strong>Tk <?php echo number_format((float) ($booking['total_paid'] ?? 0), 2); ?></strong>
                    <?php if ($booking['payment_status'] === 'Pending' && (float)($booking['total_paid'] ?? 0) > 0): ?>
                      <br><small style="color:#ff9800;">(Partial)</small>
                    <?php endif; ?>
                  </td>

                  <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>

</html>