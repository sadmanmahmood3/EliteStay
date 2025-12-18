<?php
session_start();

$allowedTypes = ['member', 'cardholder', 'stakeholder'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', $allowedTypes, true)) {
  $here = $_SERVER['REQUEST_URI'];
  header('Location: login.php?redirect=' . urlencode($here));
  exit;
}

include 'db.php';

$memberId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Guest';
$userType = $_SESSION['user_type'] ?? 'member';
$today = date('Y-m-d');

$bookingStmt = $conn->prepare("
  SELECT 
    b.id,
    b.check_in_date,
    b.check_out_date,
    b.booking_status,
    b.payment_status,
    b.total_price,
    b.num_guests,
    b.created_at,
    b.cancellation_status,
    r.room_number,
    r.floor,
    rt.name AS room_type,
    rt.image
  FROM bookings b
  LEFT JOIN rooms r ON b.room_id = r.id
  LEFT JOIN room_types rt ON r.room_type_id = rt.id
  WHERE b.member_id = ?
  ORDER BY b.check_in_date DESC
");
$bookingStmt->bind_param('i', $memberId);
$bookingStmt->execute();
$bookings = $bookingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bookingStmt->close();

$upcomingBookings = [];
$historyBookings = [];
$outstandingTotal = 0;
$completedCount = 0;
$upcomingCount = 0;

foreach ($bookings as $booking) {
  $isUpcoming = ($booking['check_in_date'] >= $today) && !in_array($booking['booking_status'], ['Checked_Out', 'Cancelled']);
  if ($isUpcoming) {
    $upcomingBookings[] = $booking;
    $upcomingCount++;
  } else {
    $historyBookings[] = $booking;
    if ($booking['booking_status'] === 'Checked_Out') {
      $completedCount++;
    }
  }
  if ($booking['payment_status'] !== 'Completed' && $booking['booking_status'] !== 'Cancelled') {
    $outstandingTotal += (float) $booking['total_price'];
  }
}

function formatCurrency($amount)
{
  if ($amount === null) {
    return '৳0.00';
  }
  return '৳' . number_format((float) $amount, 2);
}

function dateRangeLabel($checkIn, $checkOut)
{
  $start = date('M d', strtotime($checkIn));
  $end = date('M d, Y', strtotime($checkOut));
  return $start . ' — ' . $end;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Bookings • EliteStay</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
  :root {
    --bg: #050a24;
    --card: rgba(255, 255, 255, 0.08);
    --glass: rgba(14, 23, 53, 0.65);
    --accent: #ff4d6d;
    --muted: #a5b4fc;
    --text: #f8fafc;
    --border: rgba(255, 255, 255, 0.12);
    --success: #34d399;
    --warning: #fbbf24;
    --danger: #f87171;
  }

  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: 'Space Grotesk', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    background: radial-gradient(circle at top, rgba(79, 70, 229, 0.25), transparent 45%), var(--bg);
    color: var(--text);
    min-height: 100vh;
  }

  main {
    width: min(1200px, 95%);
    margin: 2.5rem auto 4rem;
    display: flex;
    flex-direction: column;
    gap: 2rem;
  }

  .hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    border-radius: 24px;
    background: var(--glass);
    border: 1px solid var(--border);
    backdrop-filter: blur(18px);
    box-shadow: 0 20px 45px rgba(5, 10, 36, 0.4);
  }

  .hero h1 {
    font-size: clamp(2rem, 3vw, 2.8rem);
    margin-bottom: 0.5rem;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    border: 1px solid var(--border);
    color: var(--muted);
  }

  .badge.accent {
    background: var(--accent);
    color: #fff;
    border-color: transparent;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.2rem;
  }

  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
  }

  .stat-card span {
    color: var(--muted);
    font-size: 0.85rem;
    letter-spacing: 0.15em;
  }

  .stat-card h3 {
    font-size: 2.2rem;
    margin-top: 0.4rem;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .section-header h2 {
    font-size: 1.3rem;
  }

  .section {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 1.8rem;
    box-shadow: 0 16px 40px rgba(5, 10, 36, 0.35);
  }

  .booking-card {
    display: flex;
    gap: 1.25rem;
    padding: 1.2rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid transparent;
    transition: transform 0.2s, border 0.2s;
  }

  .booking-card:hover {
    border-color: var(--border);
    transform: translateY(-4px);
  }

  .booking-cover {
    width: 120px;
    height: 120px;
    border-radius: 16px;
    object-fit: cover;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.08);
  }

  .booking-meta {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
  }

  .booking-meta h3 {
    font-size: 1.2rem;
  }

  .pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.85rem;
    border-radius: 999px;
    font-size: 0.85rem;
    text-transform: capitalize;
  }

  .pill.confirmed {
    background: rgba(34, 197, 94, 0.15);
    color: #34d399;
  }

  .pill.pending {
    background: rgba(251, 191, 36, 0.15);
    color: #facc15;
  }

  .pill.cancelled {
    background: rgba(248, 113, 113, 0.15);
    color: #f87171;
  }

  .pill.payment {
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--muted);
  }

  .actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
  }

  .btn {
    border: none;
    border-radius: 12px;
    padding: 0.65rem 1.2rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
  }

  .btn:hover {
    opacity: 0.85;
  }

  .btn-ghost {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text);
    border: 1px solid var(--border);
  }

  .btn-accent {
    background: var(--accent);
    color: #fff;
  }

  .empty-state {
    padding: 1.4rem;
    border-radius: 16px;
    border: 1px dashed rgba(255, 255, 255, 0.25);
    text-align: center;
    color: var(--muted);
  }

  .timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .timeline-item {
    padding-left: 1.5rem;
    border-left: 2px solid rgba(255, 255, 255, 0.12);
  }

  .timeline-item h4 {
    margin-bottom: 0.3rem;
  }

  .modal {
    position: fixed;
    inset: 0;
    background: rgba(5, 10, 36, 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
  }

  .modal-content {
    background: #0f172a;
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 1.8rem;
    width: min(480px, 90%);
    color: var(--text);
  }

  .modal-content textarea {
    width: 100%;
    min-height: 120px;
    margin-top: 1rem;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text);
    padding: 0.85rem;
    font-family: inherit;
  }

  @media (max-width: 700px) {
    .hero {
      flex-direction: column;
      gap: 1rem;
      text-align: center;
    }

    .booking-card {
      flex-direction: column;
    }

    .actions {
      width: 100%;
      flex-direction: row;
      justify-content: space-between;
    }
  }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>
  <main>
    <section class="hero">
      <div>
        <p class="badge">Dashboard</p>
        <h1>Welcome back, <?= htmlspecialchars($userName) ?></h1>
        <p class="badge accent">
          <i class="fa-solid fa-crown"></i>
          <?= htmlspecialchars(ucfirst($userType)) ?> tier
        </p>
      </div>
      <div class="actions">
        <span class="pill payment"><?= count($bookings) ?> total bookings</span>
        <a href="roomsList.php" class="btn btn-accent">Book another stay</a>
      </div>
    </section>

    <section class="stats-grid">
      <article class="stat-card">
        <span>UPCOMING</span>
        <h3><?= $upcomingCount ?></h3>
        <p><?= $upcomingCount > 0 ? 'Awaiting your arrival' : 'No trips on the calendar' ?></p>
      </article>
      <article class="stat-card">
        <span>COMPLETED</span>
        <h3><?= $completedCount ?></h3>
        <p>Checked-out stays</p>
      </article>
      <article class="stat-card">
        <span>OUTSTANDING</span>
        <h3><?= formatCurrency($outstandingTotal) ?></h3>
        <p>Complete payment before arrival</p>
      </article>
      <article class="stat-card">
        <span>LAST UPDATED</span>
        <h3><?= date('M d') ?></h3>
        <p>Live sync with your stays</p>
      </article>
    </section>

    <section class="section">
      <div class="section-header">
        <h2>Upcoming itineraries</h2>
        <span class="badge"><?= count($upcomingBookings) ?> bookings</span>
      </div>
      <?php if (count($upcomingBookings) === 0): ?>
      <div class="empty-state">
        No upcoming stays yet. When you book a room, it will appear here with payment status, benefits, and quick
        actions.
      </div>
      <?php else: ?>
      <div class="bookings-list" style="display:flex; flex-direction:column; gap:1.1rem;">
        <?php foreach ($upcomingBookings as $booking): ?>
        <article class="booking-card">
          <?php
              $cover = 'images/rooms/default-room.jpg';
              if (!empty($booking['image_url']) && file_exists(__DIR__ . '/' . $booking['image_url'])) {
                $cover = $booking['image_url'];
              }
              ?>
          <img src="<?= htmlspecialchars($cover) ?>" alt="Room preview" class="booking-cover"
            onerror="this.src='images/rooms/default-room.jpg'">
          <div class="booking-meta">
            <h3><?= htmlspecialchars($booking['room_type'] ?? 'Room reservation') ?></h3>
            <p class="badge">Room <?= htmlspecialchars($booking['room_number'] ?? '—') ?> •
              <?= htmlspecialchars($booking['num_guests']) ?> guests</p>
            <p><?= dateRangeLabel($booking['check_in_date'], $booking['check_out_date']) ?></p>
            <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
              <span
                class="pill <?= strtolower($booking['booking_status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $booking['booking_status'])) ?></span>
              <span class="pill payment">
                <i class="fa-solid fa-wallet"></i>
                <?= htmlspecialchars($booking['payment_status']) ?>
              </span>
            </div>
          </div>
          <div class="actions">
            <strong><?= formatCurrency($booking['total_price']) ?></strong>
            <button class="btn btn-ghost request-cancel" data-booking="<?= $booking['id'] ?>"
              <?= $booking['cancellation_status'] === 'Requested' ? 'disabled' : '' ?>>
              <?= $booking['cancellation_status'] === 'Requested' ? 'Cancellation pending' : 'Request cancellation' ?>
            </button>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="section">
      <div class="section-header">
        <h2>Booking history</h2>
        <a href="#" class="badge">Chronological</a>
      </div>
      <?php if (count($historyBookings) === 0): ?>
      <div class="empty-state">
        You do not have any past stays yet. Completed bookings and cancelled reservations will appear here.
      </div>
      <?php else: ?>
      <div class="timeline">
        <?php foreach ($historyBookings as $booking): ?>
        <div class="timeline-item">
          <h4><?= htmlspecialchars($booking['room_type'] ?? 'Room reservation') ?></h4>
          <p><?= dateRangeLabel($booking['check_in_date'], $booking['check_out_date']) ?> •
            <?= htmlspecialchars(str_replace('_', ' ', $booking['booking_status'])) ?></p>
          <small><?= formatCurrency($booking['total_price']) ?> • Payment
            <?= htmlspecialchars($booking['payment_status']) ?></small>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <?php if (isset($_SESSION['cancellation_message'])): ?>
    <div class="section" style="border:1px solid rgba(52, 211, 153, 0.3); color:#34d399;">
      <strong><?= htmlspecialchars($_SESSION['cancellation_message']); ?></strong>
    </div>
    <?php unset($_SESSION['cancellation_message']); ?>
    <?php endif; ?>
  </main>

  <!-- Cancellation Modal -->
  <div class="modal" id="cancellationModal">
    <div class="modal-content">
      <div class="section-header" style="margin-bottom:1rem;">
        <h2>Request cancellation</h2>
        <button class="btn btn-ghost" id="closeModal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <p>A standard cancellation fee may apply depending on your tier and window. Share a quick note for the concierge
        team.</p>
      <form action="submit_cancellation.php" method="POST">
        <textarea name="cancellation_explanation" placeholder="Optional: add context for your cancellation"></textarea>
        <input type="hidden" name="booking_id" id="modalBookingId">
        <div class="actions" style="flex-direction:row; justify-content:flex-end; margin-top:1rem;">
          <button type="button" class="btn btn-ghost" id="cancelModal">Never mind</button>
          <button type="submit" class="btn btn-accent">Submit request</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  const modal = document.getElementById('cancellationModal');
  const modalBookingId = document.getElementById('modalBookingId');
  const closeModal = document.getElementById('closeModal');
  const cancelModal = document.getElementById('cancelModal');
  const triggers = document.querySelectorAll('.request-cancel');

  function openModal(id) {
    modalBookingId.value = id;
    modal.style.display = 'flex';
  }

  function hideModal() {
    modal.style.display = 'none';
    modalBookingId.value = '';
  }

  triggers.forEach(btn => {
    if (!btn.disabled) {
      btn.addEventListener('click', () => openModal(btn.dataset.booking));
    }
  });

  closeModal.addEventListener('click', hideModal);
  cancelModal.addEventListener('click', hideModal);
  window.addEventListener('click', (e) => {
    if (e.target === modal) hideModal();
  });
  </script>
</body>

</html>