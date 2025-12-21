<?php
// Admin Sidebar Component
// Usage: include 'components/admin_sidebar.php';
// Make sure to set $currentPage variable before including

$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <h2>EliteStay Admin</h2>
  </div>
  <ul class="sidebar-menu">
    <li class="<?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
      <a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
    </li>
    <li class="<?= in_array($currentPage, ['admin_bookings.php', 'booking.php']) ? 'active' : '' ?>">
      <a href="admin_bookings.php"><i class="fas fa-calendar-check"></i><span>Reservations</span></a>
    </li>
    <li class="<?= $currentPage === 'customerProfile.php' ? 'active' : '' ?>">
      <a href="customerProfile.php"><i class="fas fa-user"></i><span>Members</span></a>
    </li>
    <li class="<?= $currentPage === 'roomsList.php' ? 'active' : '' ?>">
      <a href="roomsList.php"><i class="fas fa-bed"></i><span>Rooms</span></a>
    </li>
    <li class="<?= $currentPage === 'handleCancellationRequest.php' ? 'active' : '' ?>">
      <a href="handleCancellationRequest.php"><i class="fas fa-ban"></i><span>Cancellations</span></a>
    </li>
  </ul>
  <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
</aside>

