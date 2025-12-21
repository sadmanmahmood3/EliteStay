<?php
session_start();
require_once __DIR__ . '/db.php';

// Simple access control: only allow logged-in admins
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
  header('Location: login.php');
  exit;
}

$conn->set_charset('utf8mb4');
$adminName   = $_SESSION['user_name'] ?? 'Admin';
$todayLabel  = date('F j, Y');
$timeLabel   = date('h:i A');

/**
 * Fetch a single scalar value from the database.
 */
function fetchSingleValue(mysqli $conn, string $sql, string $types = '', array $params = [])
{
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }

  if ($types && $params) {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $stmt->bind_result($value);
  $stmt->fetch();
  $stmt->close();

  return $value ?? 0;
}

/**
 * Fetch multiple rows from the database.
 */
function fetchRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return [];
  }

  if ($types && $params) {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $result = $stmt->get_result();
  $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();

  return $rows;
}

// Summary metrics
$summary = [
  'totalRevenue'         => fetchSingleValue($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Success'"),
  'monthlyRevenue'       => fetchSingleValue($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Success' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())"),
  'totalBookings'        => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings"),
  'monthlyBookings'      => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"),
  'upcomingBookings'     => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings WHERE check_in_date >= CURDATE() AND booking_status IN ('Pending','Confirmed','Checked_In')"),
  'activeBookings'       => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings WHERE booking_status IN ('Confirmed','Checked_In')"),
  'pendingBookings'      => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Pending'"),
  'cancellationRequests' => fetchSingleValue($conn, "SELECT COUNT(*) FROM bookings WHERE cancellation_status = 'Requested'"),
  'activeMembers'        => fetchSingleValue($conn, "SELECT COUNT(*) FROM members WHERE status = 'Active'"),
  'newMembersMonth'      => fetchSingleValue($conn, "SELECT COUNT(*) FROM members WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"),
];

// Trends for the last 6 months
$bookingTrend = fetchRows(
  $conn,
  "SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COUNT(*) AS total
   FROM bookings
   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
   GROUP BY YEAR(created_at), MONTH(created_at)
   ORDER BY YEAR(created_at), MONTH(created_at)"
);

$revenueTrend = fetchRows(
  $conn,
  "SELECT DATE_FORMAT(payment_date, '%b %Y') AS label, COALESCE(SUM(amount), 0) AS total
   FROM payments
   WHERE payment_status = 'Success' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
   GROUP BY YEAR(payment_date), MONTH(payment_date)
   ORDER BY YEAR(payment_date), MONTH(payment_date)"
);

// Recent reservations
$recentBookings = fetchRows(
  $conn,
  "SELECT b.id,
          m.name       AS member_name,
          r.room_number,
          b.check_in_date,
          b.check_out_date,
          b.booking_status,
          b.cancellation_status,
          b.total_price,
          b.created_at
   FROM bookings b
   JOIN members m ON b.member_id = m.id
   JOIN rooms r ON b.room_id = r.id
   ORDER BY b.created_at DESC
   LIMIT 8"
);

// Recent cancellations and requests
$recentCancellations = fetchRows(
  $conn,
  "SELECT b.id,
          m.name AS member_name,
          r.room_number,
          b.check_in_date,
          b.check_out_date,
          b.booking_status,
          b.cancellation_status,
          b.cancellation_reason,
          b.updated_at
   FROM bookings b
   JOIN members m ON b.member_id = m.id
   JOIN rooms r ON b.room_id = r.id
   WHERE b.booking_status = 'Cancelled'
      OR b.cancellation_status IN ('Requested','Approved')
   ORDER BY b.updated_at DESC
   LIMIT 8"
);

// Recent members
$recentMembers = fetchRows(
  $conn,
  "SELECT m.id,
          m.name,
          m.email,
          m.status,
          m.created_at,
          mt.name AS tier_name
   FROM members m
   LEFT JOIN membership_tiers mt ON m.membership_tier_id = mt.id
   ORDER BY m.created_at DESC
   LIMIT 6"
);

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
  if ($normalized === 'approved') {
    return 'status status-success';
  }
  if ($normalized === 'requested') {
    return 'status status-pending';
  }
  return 'status';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | EliteStay</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-pb3VM+n5N5D5bU6Y4ZCkxzgv2Fne5+5ZTCDJ/NzNw2K6JqqPiR+jYwIV/pY5Pja/qvpDMAYA9Yg3wjRA+XDU9w=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="dashboard.css">
</head>

<body>
  <?php
  $currentPage = 'admin_dashboard.php';
  include 'components/admin_sidebar.php';
  ?>

  <main class="main-content">
    <header class="page-header">
      <div class="welcome-wrapper">
        <p class="eyebrow">Control center</p>
        <h1>Welcome back, <span><?php echo htmlspecialchars($adminName); ?></span></h1>
        <p class="muted">Live reservation health for <?php echo $todayLabel; ?> · <span class="live-dot"></span> synced
          <?php echo $timeLabel; ?></p>
      </div>
      <div class="header-actions">
        <a href="booking.php" class="btn primary"><i class="fas fa-plus"></i> New reservation</a>
        <a href="roomsList.php" class="btn ghost"><i class="fas fa-bed"></i> Manage rooms</a>
        <span class="pill soft">Revenue view · last 6 months</span>
      </div>
    </header>

    <section class="action-grid">
      <div class="action-card">
        <div class="action-head">
          <div>
            <p class="eyebrow">Reservations</p>
            <h3>Stay pipeline</h3>
            <p class="muted">Track confirmations, arrivals, and cancellation requests.</p>
          </div>
          <div class="chips">
            <span class="chip success"><?php echo (int) $summary['activeBookings']; ?> active</span>
            <span class="chip warning"><?php echo (int) $summary['pendingBookings']; ?> pending</span>
            <span class="chip danger"><?php echo (int) $summary['cancellationRequests']; ?> requests</span>
          </div>
        </div>
        <div class="action-actions">
          <a href="admin_bookings.php" class="btn primary sm"><i class="fas fa-list"></i> View all</a>
          <a href="admin_bookings.php?status=Pending" class="btn ghost sm"><i class="fas fa-clock"></i> Pending</a>
          <a href="handleCancellationRequest.php" class="btn ghost sm"><i class="fas fa-ban"></i> Cancellations</a>
          <a href="booking.php" class="btn soft sm"><i class="fas fa-plus"></i> New reservation</a>
        </div>
      </div>

      <div class="action-card">
        <div class="action-head">
          <div>
            <p class="eyebrow">Members</p>
            <h3>Guest community</h3>
            <p class="muted">Onboard, verify, and engage your members quickly.</p>
          </div>
          <div class="chips">
            <span class="chip success"><?php echo (int) $summary['activeMembers']; ?> active</span>
            <span class="chip primary"><?php echo (int) $summary['newMembersMonth']; ?> joined this month</span>
          </div>
        </div>
        <div class="action-actions">
          <a href="customerProfile.php" class="btn primary sm"><i class="fas fa-users"></i> View members</a>
          <a href="signup.php" class="btn ghost sm"><i class="fas fa-user-plus"></i> Add member</a>
          <a href="customerProfile.php#tiers" class="btn soft sm"><i class="fas fa-gem"></i> Manage tiers</a>
        </div>
      </div>
    </section>

    <section class="cards">
      <div class="card-single">
        <div>
          <span>Total revenue</span>
          <h1>Tk <?php echo number_format((float) $summary['totalRevenue'], 0); ?></h1>
          <p>All-time successful payments</p>
        </div>
        <div><i class="fas fa-wallet"></i></div>
      </div>

      <div class="card-single">
        <div>
          <span>Revenue (this month)</span>
          <h1>Tk <?php echo number_format((float) $summary['monthlyRevenue'], 0); ?></h1>
          <p>Completed payments this month</p>
        </div>
        <div><i class="fas fa-coins"></i></div>
      </div>

      <div class="card-single">
        <div>
          <span>Bookings (this month)</span>
          <h1><?php echo (int) $summary['monthlyBookings']; ?></h1>
          <p><?php echo (int) $summary['totalBookings']; ?> total all-time</p>
        </div>
        <div><i class="fas fa-calendar-check"></i></div>
      </div>

      <div class="card-single">
        <div>
          <span>Upcoming stays</span>
          <h1><?php echo (int) $summary['upcomingBookings']; ?></h1>
          <p>Active queue & arrivals</p>
        </div>
        <div><i class="fas fa-suitcase-rolling"></i></div>
      </div>

      <div class="card-single">
        <div>
          <span>Active bookings</span>
          <h1><?php echo (int) $summary['activeBookings']; ?></h1>
          <p>Confirmed or checked-in</p>
        </div>
        <div><i class="fas fa-clipboard-check"></i></div>
      </div>

      <div class="card-single">
        <div>
          <span>Active members</span>
          <h1><?php echo (int) $summary['activeMembers']; ?></h1>
          <p>Verified profiles</p>
        </div>
        <div><i class="fas fa-users"></i></div>
      </div>
    </section>

    <section class="charts">
      <div class="chart-card">
        <h3>Bookings (last 6 months)</h3>
        <div class="chart-container">
          <canvas id="bookingsChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <h3>Revenue (last 6 months)</h3>
        <div class="chart-container">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </section>

    <section class="recent-activity">
      <div class="table-card">
        <h3>Recent reservations</h3>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Guest</th>
              <th>Room</th>
              <th>Check-in</th>
              <th>Check-out</th>
              <th>Status</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentBookings)): ?>
              <tr>
                <td colspan="7" class="table-empty">No reservations yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentBookings as $booking): ?>
                <tr>
                  <td>#<?php echo (int) $booking['id']; ?></td>
                  <td><?php echo htmlspecialchars($booking['member_name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                  <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                  <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                  <td><span
                      class="<?php echo statusClass($booking['booking_status']); ?>"><?php echo htmlspecialchars($booking['booking_status']); ?></span>
                  </td>
                  <td>Tk <?php echo number_format((float) $booking['total_price'], 0); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="recent-activity">
      <div class="table-card">
        <h3>Cancellation activity</h3>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Guest</th>
              <th>Room</th>
              <th>Check-in</th>
              <th>Status</th>
              <th>Reason</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentCancellations)): ?>
              <tr>
                <td colspan="7" class="table-empty">No cancellation activity.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentCancellations as $booking): ?>
                <tr>
                  <td>#<?php echo (int) $booking['id']; ?></td>
                  <td><?php echo htmlspecialchars($booking['member_name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                  <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                  <td>
                    <span
                      class="<?php echo statusClass($booking['cancellation_status'] !== 'None' ? $booking['cancellation_status'] : $booking['booking_status']); ?>">
                      <?php echo htmlspecialchars($booking['cancellation_status'] !== 'None' ? $booking['cancellation_status'] : $booking['booking_status']); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($booking['cancellation_reason'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($booking['updated_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="recent-activity">
      <div class="table-card">
        <h3>Newest members</h3>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Tier</th>
              <th>Status</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentMembers)): ?>
              <tr>
                <td colspan="5" class="table-empty">No members yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentMembers as $member): ?>
                <tr>
                  <td>#<?php echo (int) $member['id']; ?></td>
                  <td><?php echo htmlspecialchars($member['name']); ?></td>
                  <td><?php echo htmlspecialchars($member['tier_name'] ?? '—'); ?></td>
                  <td><span
                      class="<?php echo statusClass($member['status']); ?>"><?php echo htmlspecialchars($member['status']); ?></span>
                  </td>
                  <td><?php echo htmlspecialchars(date('M d, Y', strtotime($member['created_at']))); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const bookingTrend = <?php echo json_encode($bookingTrend ?: []); ?>;
    const revenueTrend = <?php echo json_encode($revenueTrend ?: []); ?>;

    const bookingLabels = bookingTrend.map(item => item.label);
    const bookingValues = bookingTrend.map(item => Number(item.total));

    const revenueLabels = revenueTrend.map(item => item.label);
    const revenueValues = revenueTrend.map(item => Number(item.total));

    const bookingsCtx = document.getElementById('bookingsChart');
    if (bookingsCtx) {
      new Chart(bookingsCtx, {
        type: 'line',
        data: {
          labels: bookingLabels,
          datasets: [{
            label: 'Bookings',
            data: bookingValues,
            borderColor: '#a855f7',
            backgroundColor: 'rgba(168, 85, 247, 0.18)',
            fill: true,
            tension: 0.25
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0
              }
            }
          }
        }
      });
    }

    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
      new Chart(revenueCtx, {
        type: 'bar',
        data: {
          labels: revenueLabels,
          datasets: [{
            label: 'Revenue (Tk)',
            data: revenueValues,
            backgroundColor: '#6366f1'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }
  </script>

  <!-- Zapier Chatbot Embed -->
  <script async type='module'
    src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'>
  </zapier-interfaces-chatbot-embed>
</body>

</html>