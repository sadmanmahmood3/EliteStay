<?php
session_start();
include 'db.php';

// Read filters from GET
$room_type = isset($_GET['room_type']) && $_GET['room_type'] !== '' ? (int)$_GET['room_type'] : null;
$city = isset($_GET['city']) && $_GET['city'] !== '' ? $conn->real_escape_string($_GET['city']) : null;
$area = isset($_GET['area']) && $_GET['area'] !== '' ? $conn->real_escape_string($_GET['area']) : null;
$guests = isset($_GET['guests']) && is_numeric($_GET['guests']) ? (int)$_GET['guests'] : null;
$check_in = isset($_GET['check_in']) && $_GET['check_in'] !== '' ? $_GET['check_in'] : null;
$check_out = isset($_GET['check_out']) && $_GET['check_out'] !== '' ? $_GET['check_out'] : null;

// Fetch room types that match basic filters
$sql = "SELECT rt.id, rt.name, rt.base_price, rt.max_occupancy, rt.image
        FROM room_types rt
        WHERE rt.status = 'Active'";
$params = [];

if ($room_type) {
  $sql .= " AND rt.id = ?";
  $params[] = $room_type;
}
if ($guests) {
  $sql .= " AND rt.max_occupancy >= ?";
  $params[] = $guests;
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die('Prepare failed: ' . $conn->error);
}
// bind params dynamically
if (!empty($params)) {
  $types = str_repeat('i', count($params));
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$roomTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For each room type, compute number of available rooms between dates (if dates provided)
foreach ($roomTypes as &$rt) {
  $rt_id = (int)$rt['id'];
  $countSql = "SELECT COUNT(r.id) AS available_count
                 FROM rooms r
                 WHERE r.room_type_id = ? AND r.status = 'Available'";
  $countParams = [$rt_id];

  if ($check_in && $check_out) {
    // exclude rooms that have conflicting bookings
    $countSql .= " AND r.id NOT IN (
            SELECT room_id FROM bookings WHERE booking_status IN ('Confirmed','Pending','Checked_In')
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        )";
    // exclude days blocked in room_availability
    $countSql .= " AND r.id NOT IN (
            SELECT room_id FROM room_availability WHERE available_date BETWEEN ? AND ? AND is_available = 'No'
        )";
    $countParams[] = $check_in;
    $countParams[] = $check_out;
    $countParams[] = $check_in;
    $countParams[] = $check_out;
  }

  $cstmt = $conn->prepare($countSql);
  if ($cstmt === false) {
    $rt['available_count'] = 0;
    continue;
  }
  // build types for bind
  // bind all params as strings for simplicity
  $types = str_repeat('s', count($countParams));
  $cstmt->bind_param($types, ...$countParams);
  $cstmt->execute();
  $res = $cstmt->get_result()->fetch_assoc();
  $rt['available_count'] = isset($res['available_count']) ? (int)$res['available_count'] : 0;
  $cstmt->close();
}
unset($rt);

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Available Rooms — EliteStay</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
      --primary: #7c3aed;
      --primary-dark: #6d28d9;
      --accent: #ec4899;
      --bg-light: #f8f6fc;
      --card-shadow: rgba(124, 58, 237, 0.08);
      --muted: #6b7280;
      --border: #e9d5ff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-light);
      color: #1e1b4b
    }

    .container {
      max-width: 1200px;
      margin: 2.5rem auto;
      padding: 0 1rem
    }

    .top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem
    }

    h2 {
      font-size: 2rem;
      color: var(--primary)
    }

    .filters {
      display: flex;
      gap: 0.75rem;
      align-items: center
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1.5rem
    }

    .card {
      background: linear-gradient(180deg, #fff, #fbf8ff);
      border-radius: 12px;
      padding: 1rem;
      box-shadow: 0 12px 30px var(--card-shadow);
      border: 1px solid rgba(124, 58, 237, 0.06);
      display: flex;
      flex-direction: column;
      gap: 0.6rem
    }

    .card img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-radius: 8px
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.15rem;
      margin-top: 6px
    }

    .card p {
      color: var(--muted);
      font-size: 0.95rem;
      margin: 0
    }

    .card .meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 8px
    }

    .price {
      font-weight: 800;
      color: var(--accent)
    }

    .btn-primary {
      padding: 10px 12px;
      border-radius: 10px;
      border: none;
      background: linear-gradient(90deg, var(--primary), var(--primary-dark));
      color: #fff;
      font-weight: 700;
      cursor: pointer
    }

    .btn-ghost {
      padding: 8px 10px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--primary-dark);
      cursor: pointer
    }

    .empty {
      color: var(--muted);
      padding: 1rem;
      text-align: center
    }

    @media(max-width:800px) {
      .top-row {
        flex-direction: column;
        align-items: flex-start
      }

      .card img {
        height: 160px
      }
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <div class="container">
    <div class="top-row">
      <h2>Search Results</h2>
      <div class="filters muted">Results: <?php echo count($roomTypes); ?></div>
    </div>

    <div class="grid">
      <?php if (empty($roomTypes)): ?>
        <div class="empty">No room types match your filters.</div>
      <?php else: ?>
        <?php foreach ($roomTypes as $rt):
          $img = 'images/rooms/' . ($rt['image'] ?? 'default-room.jpg');
          if (!file_exists(__DIR__ . '/' . $img)) {
            $img = 'images/rooms/default-room.jpg';
          }
        ?>
          <div class="card">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($rt['name']); ?>">
            <h3><?php echo htmlspecialchars($rt['name']); ?></h3>
            <p>Max Occupancy: <?php echo htmlspecialchars($rt['max_occupancy']); ?></p>
            <div class="meta">
              <div class="price">BDT <?php echo number_format($rt['base_price'], 0); ?>/night</div>
              <div class="muted">Available: <?php echo htmlspecialchars($rt['available_count']); ?></div>
            </div>
            <div style="margin-top:10px;display:flex;gap:8px">
              <button class="btn-primary" onclick="location.href='roomdetails.php?room_type_id=<?php echo (int)$rt['id']; ?>'">View Details</button>
              <button class="btn-ghost" onclick="location.href='booking.php?room_type_id=<?php echo (int)$rt['id']; ?>'">Book</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</body>

</html>