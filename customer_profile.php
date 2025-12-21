<?php
session_start();

// Allow both legacy "member" type and the new tier-specific labels
$allowedUserTypes = ['member', 'cardholder', 'stakeholder'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
  header('Location: login.php');
  exit;
}

include 'db.php';

$userId = (int) $_SESSION['user_id'];

// Fetch the latest member snapshot from the database
$profileStmt = $conn->prepare("
  SELECT 
    m.id,
    m.name,
    m.email,
    m.phone,
    m.city,
    m.area,
    m.avatar,
    m.status,
    m.created_at,
    mt.name            AS tier_name,
    mt.description     AS tier_description,
    mt.tier_level
  FROM members m
  LEFT JOIN membership_tiers mt ON m.membership_tier_id = mt.id
  WHERE m.id = ?
  LIMIT 1
");

if (!$profileStmt) {
  die('Unable to load profile. Please try again later.');
}

$profileStmt->bind_param('i', $userId);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
$member = $profileResult->fetch_assoc();
$profileStmt->close();

if (!$member) {
  die('Member profile not found.');
}

// Fetch stats for the dashboard cards
$statStmt = $conn->prepare("
  SELECT 
    COUNT(*) AS total_bookings,
    SUM(
      CASE 
        WHEN b.check_in_date >= CURDATE() 
          AND b.booking_status NOT IN ('Cancelled','Checked_Out') 
        THEN 1 ELSE 0 
      END
    ) AS upcoming_bookings,
    COALESCE(SUM(b.total_price), 0) AS lifetime_spend
  FROM bookings b
  WHERE b.member_id = ?
");
$stats = ['total_bookings' => 0, 'upcoming_bookings' => 0, 'lifetime_spend' => 0];
if ($statStmt) {
  $statStmt->bind_param('i', $userId);
  $statStmt->execute();
  $statResult = $statStmt->get_result();
  $stats = $statResult->fetch_assoc();
  $statStmt->close();
}

// Pull the three latest bookings to show recent activity
$recentBookings = [];
$recentStmt = $conn->prepare("
  SELECT 
    b.id,
    b.check_in_date,
    b.check_out_date,
    b.booking_status,
    b.total_price,
    r.room_number,
    rt.name AS room_type
  FROM bookings b
  LEFT JOIN rooms r ON b.room_id = r.id
  LEFT JOIN room_types rt ON r.room_type_id = rt.id
  WHERE b.member_id = ?
  ORDER BY b.check_in_date DESC
  LIMIT 3
");
if ($recentStmt) {
  $recentStmt->bind_param('i', $userId);
  $recentStmt->execute();
  $recentResult = $recentStmt->get_result();
  $recentBookings = $recentResult->fetch_all(MYSQLI_ASSOC);
  $recentStmt->close();
}

// Get active perks tied to the member's tier (fallback to session cache if query fails)
$perks = [];
$perksStmt = $conn->prepare("
  SELECT perk_name, description
  FROM membership_perks
  WHERE membership_tier_id = (
    SELECT membership_tier_id FROM members WHERE id = ?
  )
  AND is_active = 'Yes'
  ORDER BY id ASC
");
if ($perksStmt) {
  $perksStmt->bind_param('i', $userId);
  $perksStmt->execute();
  $perksResult = $perksStmt->get_result();
  $perks = $perksResult->fetch_all(MYSQLI_ASSOC);
  $perksStmt->close();
} elseif (!empty($_SESSION['membership_perks'])) {
  $perks = $_SESSION['membership_perks'];
}

function formatCurrency($amount)
{
  if ($amount === null) {
    return '—';
  }
  return '৳' . number_format((float) $amount, 2);
}

$tierBadgeLabel = ucfirst($_SESSION['user_type']);
$joinedOn = date('M d, Y', strtotime($member['created_at']));
$avatarPath = $member['avatar'] ?: 'images/avatar/prince.jpeg';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile • EliteStay</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="customer_profile.css">
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <main class="profile-shell">
    <section class="profile-hero glass-card">
      <div class="hero-left">
        <div class="avatar-frame">
          <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Profile avatar"
            onerror="this.src='images/avatar/sadman.jpg'">
        </div>
        <div>
          <p class="eyebrow">Logged in as</p>
          <h1><?= htmlspecialchars($member['name']) ?></h1>
          <div class="badge-row">
            <span class="badge badge-tier"><?= htmlspecialchars($tierBadgeLabel) ?></span>
            <?php if (!empty($member['tier_name'])): ?>
              <span class="badge badge-luxe"><?= htmlspecialchars($member['tier_name']) ?> Tier</span>
            <?php endif; ?>
            <span class="badge badge-muted">Member since <?= htmlspecialchars($joinedOn) ?></span>
          </div>
        </div>
      </div>
      <div class="hero-right">
        <div class="contact-group">
          <p><i class="fa-solid fa-envelope"></i><?= htmlspecialchars($member['email']) ?></p>
          <?php if (!empty($member['phone'])): ?>
            <p><i class="fa-solid fa-phone"></i><?= htmlspecialchars($member['phone']) ?></p>
          <?php endif; ?>
          <?php if (!empty($member['city']) || !empty($member['area'])): ?>
            <p><i
                class="fa-solid fa-location-dot"></i><?= htmlspecialchars(trim($member['area'] . ', ' . $member['city'], ', ')) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="info-grid">
      <article class="stat-card glass-card">
        <span class="stat-label">Total Bookings</span>
        <h2><?= (int) $stats['total_bookings'] ?></h2>
        <p>Memorable stays you've planned.</p>
      </article>
      <article class="stat-card glass-card">
        <span class="stat-label">Upcoming Trips</span>
        <h2><?= (int) $stats['upcoming_bookings'] ?></h2>
        <p>Awaiting your arrival.</p>
      </article>
      <article class="stat-card glass-card">
        <span class="stat-label">Lifetime Spend</span>
        <h2><?= formatCurrency($stats['lifetime_spend']) ?></h2>
        <p>Across all confirmed bookings.</p>
      </article>
      <article class="story-card glass-card">
        <p class="eyebrow">Tier Story</p>
        <h3><?= htmlspecialchars($member['tier_name'] ?? 'Loyal Guest') ?></h3>
        <p>
          <?= htmlspecialchars($member['tier_description'] ?? 'Enjoy curated experiences and member-only benefits crafted for you.') ?>
        </p>
      </article>
    </section>

    <section class="content-panels">
      <div class="panel glass-card perks-panel">
        <div class="panel-header">
          <div>
            <p class="eyebrow">Membership perks</p>
            <h3>Your curated benefits</h3>
          </div>
          <span class="badge badge-luxe"><?= count($perks) ?> active</span>
        </div>
        <?php if (count($perks) === 0): ?>
          <p class="empty-state">No perks available for your tier just yet. Stay tuned!</p>
        <?php else: ?>
          <ul class="perk-list">
            <?php foreach ($perks as $perk): ?>
              <li>
                <div class="perk-icon">
                  <i class="fa-solid fa-sparkles"></i>
                </div>
                <div>
                  <h4><?= htmlspecialchars($perk['perk_name']) ?></h4>
                  <p><?= htmlspecialchars($perk['description'] ?? 'Exclusive benefit unlocked for your tier.') ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="panel glass-card activity-panel">
        <div class="panel-header">
          <div>
            <p class="eyebrow">Recent journeys</p>
            <h3>Latest bookings</h3>
          </div>
          <a class="ghost-link" href="customerBooking.php">View all</a>
        </div>
        <?php if (count($recentBookings) === 0): ?>
          <p class="empty-state">No bookings yet. Start planning your next celebration!</p>
        <?php else: ?>
          <ul class="activity-list">
            <?php foreach ($recentBookings as $booking): ?>
              <li>
                <div>
                  <h4><?= htmlspecialchars($booking['room_type'] ?? 'Room Reservation') ?></h4>
                  <p>Room <?= htmlspecialchars($booking['room_number'] ?? '—') ?></p>
                  <p><?= htmlspecialchars(date('M d', strtotime($booking['check_in_date']))) ?> –
                    <?= htmlspecialchars(date('M d', strtotime($booking['check_out_date']))) ?></p>
                </div>
                <div class="activity-meta">
                  <span><?= formatCurrency($booking['total_price']) ?></span>
                  <span class="status-pill status-<?= strtolower($booking['booking_status']) ?>">
                    <?= htmlspecialchars($booking['booking_status']) ?>
                  </span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    // Add a subtle tilt effect to stat cards (purely visual)
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = ((y - centerY) / centerY) * -4;
        const rotateY = ((x - centerX) / centerX) * 4;
        card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = 'rotateX(0deg) rotateY(0deg)';
      });
    });
  </script>
  <!-- Zapier Chatbot Embed -->
  <script async type='module' src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'></zapier-interfaces-chatbot-embed>
</body>

</html>