<?php
session_start();
include 'db.php';

// Fetch all rooms with their type information
$stmt = $conn->prepare("
    SELECT 
        r.id, 
        r.room_number, 
        r.floor, 
        r.status, 
        rt.id AS room_type_id,
        rt.name AS room_type_name, 
        rt.base_price, 
        rt.max_occupancy, 
        rt.image
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE rt.status = 'Active'
    ORDER BY r.floor ASC, r.room_number ASC
");
$stmt->execute();
$allRooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Rooms - EliteStay</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    :root {
      --primary: #ff4d6d;
      --secondary: #1f2937;
      --accent: #a855f7;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f3f4f6;
      color: var(--secondary);
      padding-top: 0;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem;
    }

    .page-header {
      margin-bottom: 3rem;
    }

    .page-header h1 {
      font-size: 2.5rem;
      color: var(--secondary);
      margin-bottom: 0.5rem;
    }

    .page-header p {
      font-size: 1rem;
      color: #6b7280;
    }

    .filters {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }

    .filter-group {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }

    .filter-group label {
      font-weight: 600;
      color: var(--secondary);
    }

    .filter-group select {
      padding: 0.5rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      background-color: white;
      cursor: pointer;
      font-size: 0.95rem;
    }

    .filter-group select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(255, 77, 109, 0.1);
    }

    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 2rem;
    }

    .room-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .room-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .room-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
      background-color: #e5e7eb;
    }

    .room-body {
      padding: 1.5rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .room-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
    }

    .room-header h3 {
      font-size: 1.3rem;
      color: var(--secondary);
      margin-bottom: 0.25rem;
    }

    .room-number {
      font-size: 0.85rem;
      color: #6b7280;
    }

    .room-status {
      display: inline-block;
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .room-status.available {
      background-color: #d1fae5;
      color: #065f46;
    }

    .room-status.occupied {
      background-color: #fee2e2;
      color: #7f1d1d;
    }

    .room-status.maintenance {
      background-color: #fef3c7;
      color: #78350f;
    }

    .room-details {
      margin-bottom: 1rem;
      border-top: 1px solid #e5e7eb;
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 0;
    }

    .detail-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
    }

    .detail-item:last-child {
      margin-bottom: 0;
    }

    .detail-label {
      color: #6b7280;
      font-weight: 500;
    }

    .detail-value {
      color: var(--secondary);
      font-weight: 600;
    }

    .room-price {
      font-size: 1.2rem;
      color: var(--primary);
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .room-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

    .btn {
      padding: 0.6rem 1rem;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: #dc3a56;
      transform: scale(1.02);
    }

    .btn-secondary {
      background-color: #e5e7eb;
      color: var(--secondary);
    }

    .btn-secondary:hover {
      background-color: #d1d5db;
      transform: scale(1.02);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
    }

    .empty-state i {
      font-size: 3rem;
      color: #d1d5db;
      margin-bottom: 1rem;
    }

    .empty-state h3 {
      color: var(--secondary);
      margin-bottom: 0.5rem;
    }

    .empty-state p {
      color: #6b7280;
    }

    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }

      .page-header h1 {
        font-size: 2rem;
      }

      .rooms-grid {
        grid-template-columns: 1fr;
      }

      .filters {
        flex-direction: column;
      }

      .filter-group {
        flex-direction: column;
        align-items: flex-start;
      }

      .room-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <div class="container">
    <div class="page-header">
      <h1><i class="fas fa-door-open"></i> All Rooms</h1>
      <p>Browse our complete collection of available rooms and book your stay</p>
    </div>

    <div class="filters">
      <div class="filter-group">
        <label for="filterFloor">Filter by Floor:</label>
        <select id="filterFloor">
          <option value="">All Floors</option>
          <?php
          $floors = array_unique(array_column($allRooms, 'floor'));
          sort($floors);
          foreach ($floors as $floor):
          ?>
            <option value="<?= $floor ?>"><?= $floor ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label for="filterStatus">Filter by Status:</label>
        <select id="filterStatus">
          <option value="">All Status</option>
          <option value="Available">Available</option>
          <option value="Occupied">Occupied</option>
          <option value="Maintenance">Maintenance</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="filterType">Filter by Room Type:</label>
        <select id="filterType">
          <option value="">All Types</option>
          <?php
          $types = array_unique(array_column($allRooms, 'room_type_name'));
          sort($types);
          foreach ($types as $type):
          ?>
            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="rooms-grid" id="roomsContainer">
      <?php if (empty($allRooms)): ?>
        <div class="empty-state" style="grid-column: 1 / -1;">
          <i class="fas fa-inbox"></i>
          <h3>No Rooms Found</h3>
          <p>There are currently no rooms available to display.</p>
        </div>
      <?php else: ?>
        <?php foreach ($allRooms as $room): ?>
          <div class="room-card" data-floor="<?= $room['floor'] ?>" data-status="<?= htmlspecialchars($room['status']) ?>" data-type="<?= htmlspecialchars($room['room_type_name']) ?>">
            <img src="images/rooms/<?= htmlspecialchars($room['image']) ?>" alt="<?= htmlspecialchars($room['room_type_name']) ?>" class="room-image" onerror="this.src='images/default-room.jpg'">

            <div class="room-body">
              <div class="room-header">
                <div>
                  <h3><?= htmlspecialchars($room['room_type_name']) ?></h3>
                  <span class="room-number">Room #<?= htmlspecialchars($room['room_number']) ?> - Floor <?= htmlspecialchars($room['floor']) ?></span>
                </div>
                <span class="room-status <?= strtolower($room['status']) ?>">
                  <?= htmlspecialchars($room['status']) ?>
                </span>
              </div>

              <div class="room-details">
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-users"></i> Max Occupancy:</span>
                  <span class="detail-value"><?= htmlspecialchars($room['max_occupancy']) ?> Guest(s)</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-home"></i> Room Type:</span>
                  <span class="detail-value"><?= htmlspecialchars($room['room_type_name']) ?></span>
                </div>
              </div>

              <div class="room-price">
                BDT <?= number_format($room['base_price'], 0) ?>/night
              </div>

              <div class="room-actions">
                <a href="roomdetails.php?room_type_id=<?= $room['room_type_id'] ?>" class="btn btn-secondary">
                  <i class="fas fa-info-circle"></i> View Details
                </a>
                <a href="booking.php?room_id=<?= $room['id'] ?>" class="btn btn-primary" <?= $room['status'] !== 'Available' ? 'disabled onclick="return false"' : '' ?>>
                  <i class="fas fa-calendar-check"></i> Book Now
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const filterFloor = document.getElementById('filterFloor');
    const filterStatus = document.getElementById('filterStatus');
    const filterType = document.getElementById('filterType');
    const roomsContainer = document.getElementById('roomsContainer');

    function filterRooms() {
      const floor = filterFloor.value;
      const status = filterStatus.value;
      const type = filterType.value;

      const cards = roomsContainer.querySelectorAll('.room-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const cardFloor = card.dataset.floor;
        const cardStatus = card.dataset.status;
        const cardType = card.dataset.type;

        const floorMatch = !floor || cardFloor === floor;
        const statusMatch = !status || cardStatus === status;
        const typeMatch = !type || cardType === type;

        if (floorMatch && statusMatch && typeMatch) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      // Show empty state if no rooms match filters
      if (visibleCount === 0 && roomsContainer.children.length > 0) {
        if (!document.getElementById('empty-state')) {
          const emptyState = document.createElement('div');
          emptyState.id = 'empty-state';
          emptyState.className = 'empty-state';
          emptyState.style.gridColumn = '1 / -1';
          emptyState.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>No Rooms Match Your Filters</h3>
                        <p>Try adjusting your search criteria.</p>
                    `;
          roomsContainer.appendChild(emptyState);
        }
      } else {
        const emptyState = document.getElementById('empty-state');
        if (emptyState) emptyState.remove();
      }
    }

    filterFloor.addEventListener('change', filterRooms);
    filterStatus.addEventListener('change', filterRooms);
    filterType.addEventListener('change', filterRooms);
  </script>
  <!-- Zapier Chatbot Embed -->
  <script async type='module' src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'></zapier-interfaces-chatbot-embed>
</body>
</body>

</html>