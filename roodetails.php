<?php
session_start();
include 'db.php';

$room_type_id = isset($_GET['room_type_id']) ? (int)$_GET['room_type_id'] : 0;
if ($room_type_id <= 0) {
  die('Invalid room type id');
}

// Fetch room type details
$stmt = $conn->prepare("SELECT id, name, description, base_price, max_occupancy, features, image FROM room_types WHERE id = ? AND status = 'Active'");
$stmt->bind_param('i', $room_type_id);
$stmt->execute();
$rt = $stmt->get_result()->fetch_assoc();
if (!$rt) {
  die('Room type not found');
}

// Fetch images
$imgStmt = $conn->prepare("SELECT image FROM room_images WHERE room_type_id = ? ORDER BY display_order ASC");
$imgStmt->bind_param('i', $room_type_id);
$imgStmt->execute();
$images = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count available rooms (next 30 days) as an example
$today = date('Y-m-d');
$future = date('Y-m-d', strtotime('+30 days'));
$countStmt = $conn->prepare("SELECT COUNT(r.id) AS available_count FROM rooms r
    WHERE r.room_type_id = ? AND r.status = 'Available' AND r.id NOT IN (
      SELECT room_id FROM bookings WHERE booking_status IN ('Confirmed','Pending','Checked_In')
      AND NOT (check_out_date <= ? OR check_in_date >= ?)
    )");
$countStmt->bind_param('iss', $room_type_id, $today, $future);
$countStmt->execute();
$availableRow = $countStmt->get_result()->fetch_assoc();
$available_count = isset($availableRow['available_count']) ? (int)$availableRow['available_count'] : 0;

// Fetch all rooms of this type with their details
$roomsStmt = $conn->prepare("SELECT r.id, r.room_number, r.floor, r.status FROM rooms r WHERE r.room_type_id = ? ORDER BY r.floor ASC, r.room_number ASC");
$roomsStmt->bind_param('i', $room_type_id);
$roomsStmt->execute();
$rooms = $roomsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($rt['name']); ?> — EliteStay</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    :root {
      --primary: #7c3aed;
      --primary-dark: #6d28d9;
      --accent: #ec4899;
      --bg-light: #f8f6fc;
      --bg-white: #ffffff;
      --text-dark: #1e1b4b;
      --muted: #6b7280;
      --card-shadow: rgba(124, 58, 237, 0.08);
      --glass: rgba(255, 255, 255, 0.75);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-light);
      color: var(--text-dark);
    }

    .container {
      max-width: 1200px;
      margin: 2.5rem auto;
      padding: 0 1rem;
    }

    .header-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .room-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--primary);
    }

    .room-layout {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 1.75rem;
      align-items: start;
    }

    .card {
      background: linear-gradient(180deg, var(--bg-white), #fbf8ff);
      border-radius: 14px;
      padding: 1.25rem;
      box-shadow: 0 12px 30px var(--card-shadow);
      border: 1px solid rgba(124, 58, 237, 0.06);
    }

    .gallery {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin-top: 0.85rem;
    }

    .gallery img {
      width: 110px;
      height: 72px;
      object-fit: cover;
      border-radius: 8px;
      box-shadow: 0 6px 18px rgba(36, 11, 59, 0.06);
      cursor: pointer;
      transition: transform 0.25s ease;
    }

    .gallery img:hover {
      transform: translateY(-6px);
    }

    .primary-image {
      width: 100%;
      height: 420px;
      object-fit: cover;
      border-radius: 12px;
      display: block;
    }

    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .meta-row {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }

    .meta-label {
      font-weight: 700;
      color: var(--muted);
      min-width: 100px;
    }

    .meta-value {
      font-weight: 600;
      color: var(--text-dark);
    }

    .price-badge {
      background: linear-gradient(90deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 10px 14px;
      border-radius: 10px;
      font-weight: 800;
      font-size: 1.1rem;
    }

    .room-actions {
      display: flex;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .btn {
      padding: 0.85rem 1.1rem;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
      border: none;
    }

    .btn-primary {
      background: linear-gradient(90deg, var(--primary), var(--accent));
      color: white;
      box-shadow: 0 10px 30px rgba(124, 58, 237, 0.18);
    }

    .btn-ghost {
      background: transparent;
      border: 1px solid rgba(124, 58, 237, 0.12);
      color: var(--primary-dark);
    }

    /* Right column list */
    .room-list {
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
    }

    .room-row {
      display: flex;
      gap: 0.75rem;
      align-items: center;
      padding: 0.7rem;
      border-radius: 10px;
      background: linear-gradient(180deg, #fff, #fbf8ff);
      border: 1px solid rgba(124, 58, 237, 0.04);
      box-shadow: 0 8px 20px rgba(124, 58, 237, 0.04);
    }

    .room-thumb {
      width: 110px;
      height: 72px;
      object-fit: cover;
      border-radius: 8px;
    }

    .room-meta {
      flex: 1;
    }

    .status-pill {
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.85rem;
    }

    .status-available {
      background: #e6ffef;
      color: #0a6b2f;
    }

    .status-unavailable {
      background: #fff1f2;
      color: #7a1921;
    }

    .muted {
      color: var(--muted);
      font-size: 0.95rem;
    }

    @media (max-width: 900px) {
      .room-layout {
        grid-template-columns: 1fr;
      }

      .primary-image {
        height: 260px;
      }
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <div class="container">
    <div class="header-row">
      <div>
        <div class="room-title"><?php echo htmlspecialchars($rt['name']); ?></div>

      </div>
      <div class="price-badge">From BDT <?php echo number_format($rt['base_price'], 0); ?></div>
    </div>

    <div class="room-layout">
      <!-- LEFT: Large image, overview, gallery -->
      <div>
        <div class="card">
          <?php
          $mainImg = 'images/rooms/' . ($rt['image'] ?: 'default-room.jpg');
          if (!file_exists(__DIR__ . '/' . $mainImg)) {
            $mainImg = 'images/rooms/default-room.jpg';
          }
          ?>
          <img class="primary-image" src="<?php echo htmlspecialchars($mainImg); ?>" alt="<?php echo htmlspecialchars($rt['name']); ?>">

          <h3 style="margin-top:12px">Overview</h3>
          <p class="muted" style="margin-top:8px"><?php echo nl2br(htmlspecialchars($rt['description'])); ?></p>

          <div class="details-grid">
            <div>
              <div class="meta-row"><span class="meta-label">Max Occupancy</span><span class="meta-value"><?php echo htmlspecialchars($rt['max_occupancy']); ?></span></div>
              <div class="meta-row" style="margin-top:6px"><span class="meta-label">Available (30d)</span><span class="meta-value"><?php echo $available_count; ?></span></div>
            </div>
            <div>
              <div class="meta-row"><span class="meta-label">Type</span><span class="meta-value"><?php echo htmlspecialchars($rt['name']); ?></span></div>
              <div class="meta-row" style="margin-top:6px"><span class="meta-label">Amenities</span><span class="meta-value muted"><?php echo htmlspecialchars($rt['features']); ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Individual room list with booking buttons -->
      <aside>
        <div class="card">
          <h3 style="margin:0 0 10px 0">Available Rooms</h3>
          <div class="room-list">
            <?php if (empty($rooms)): ?>
              <div class="muted">No rooms found for this type.</div>
            <?php else: ?>
              <?php foreach ($rooms as $room):
                $serverImg = __DIR__ . '/images/rooms/room_' . $room['id'] . '.jpg';
                if (file_exists($serverImg)) {
                  $roomImg = 'images/rooms/room_' . $room['id'] . '.jpg';
                } elseif (!empty($rt['image']) && file_exists(__DIR__ . '/images/rooms/' . $rt['image'])) {
                  $roomImg = 'images/rooms/' . $rt['image'];
                } else {
                  $roomImg = 'images/rooms/default-room.jpg';
                }
              ?>
                <div class="room-row">
                  <img class="room-thumb" src="<?php echo htmlspecialchars($roomImg); ?>" alt="Room <?php echo htmlspecialchars($room['room_number']); ?>">
                  <div class="room-meta">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                      <div>
                        <div style="font-weight:700">Room <?php echo htmlspecialchars($room['room_number']); ?></div>
                        <div class="muted" style="font-size:0.9rem">Floor <?php echo htmlspecialchars($room['floor']); ?> • Capacity <?php echo htmlspecialchars($rt['max_occupancy']); ?></div>
                      </div>
                      <div style="text-align:right">
                        <div class="status-pill <?php echo ($room['status'] === 'Available') ? 'status-available' : 'status-unavailable'; ?>"><?php echo htmlspecialchars($room['status']); ?></div>
                      </div>
                    </div>
                    <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end">
                      <button class="btn btn-primary" onclick="window.location.href='booking.php?room_id=<?php echo (int)$room['id']; ?>'">Book</button>

                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </aside>
    </div>
  </div>

</body>

</html>