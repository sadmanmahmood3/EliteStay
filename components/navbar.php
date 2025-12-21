<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$current    = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
$userName   = $_SESSION['user_name']   ?? '';
$userType   = $_SESSION['user_type']   ?? '';
$userAvatar = isset($_SESSION['user_avatar']) ? $_SESSION['user_avatar'] : 'default-avatar.png';
$currentUrl = $_SERVER['REQUEST_URI'];
?>

<nav class="navbar">
  <div class="logo">🏨 EliteStay</div>
  <ul class="nav-links">
    <li><a href="index.php" class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>">Home</a></li>
    <li><a href="roomsList.php" class="nav-link <?= $current === 'roomsList.php' ? 'active' : '' ?>">Rooms</a></li>
    <li><a href="#about" class="nav-link">About</a></li>
    <li><a href="#contact" class="nav-link">Contact</a></li>

    <?php if (!$isLoggedIn): ?>
      <li>
        <button class="btn-login" onclick="location.href='login.php'">Login</button>
      </li>
    <?php else: ?>
      <li class="nav-profile">
        <div class="profile-section">
          <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
          <div class="profile-info">
            <span class="profile-name"><?= htmlspecialchars($userName) ?></span>
            <button id="navHamburger" class="nav-hamburger">
              <i class="fas fa-chevron-down"></i>
            </button>
          </div>
        </div>
        <nav id="navDropdown" class="nav-dropdown hidden">
          <ul>
            <li><a href="customerBooking.php" class="nav-link"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
            <li><a href="customerProfile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></li>
            <?php if ($userType === 'admin'): ?>
              <li><a href="admin_dashboard.php" class="nav-link"><i class="fas fa-cog"></i> Admin Panel</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="nav-link logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
          </ul>
        </nav>
      </li>
    <?php endif; ?>
  </ul>
</nav>

<style>
  .navbar {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    position: sticky;
    top: 0;
    z-index: 1000;
  }

  .logo {
    font-weight: 800;
    font-size: 1.8rem;
    color: #ff4d6d;
  }

  .nav-links {
    display: flex;
    gap: 1.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
  }

  .nav-links a {
    text-decoration: none;
    color: #374151;
    font-weight: 500;
    transition: color 0.2s;
  }

  .nav-links a:hover {
    color: #ff4d6d;
  }

  .nav-links a.active {
    color: #ff4d6d;
    border-bottom: 2px solid #ff4d6d;
  }

  .btn-login {
    background: #ff4d6d;
    color: #fff;
    padding: 0.5rem 1.2rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
  }

  .btn-login:hover {
    background: #dc3a56;
  }

  .nav-profile {
    position: relative;
    display: flex;
    align-items: center;
    list-style: none;
  }

  .profile-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
  }

  .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ff4d6d;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
  }

  .profile-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .profile-name {
    color: #374151;
    font-weight: 500;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .nav-hamburger {
    background: none;
    border: none;
    cursor: pointer;
    color: #374151;
    font-size: 0.9rem;
    padding: 0;
    margin-left: 0.25rem;
  }

  .nav-dropdown {
    position: absolute;
    top: 56px;
    right: 0;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 0.5rem;
    overflow: hidden;
    transform-origin: top right;
    transition: opacity 0.2s, transform 0.2s;
    min-width: 200px;
  }

  .nav-dropdown.hidden {
    opacity: 0;
    transform: scale(0.95);
    pointer-events: none;
  }

  .nav-dropdown ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .nav-dropdown li a {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    color: #374151;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s, color 0.2s;
  }

  .nav-dropdown li a:hover {
    background: #f9fafb;
    color: #ff4d6d;
  }

  .nav-dropdown li a.logout-link {
    color: #ef4444;
  }

  .nav-dropdown li a.logout-link:hover {
    background: #fee2e2;
    color: #dc2626;
  }
</style>

<script>
  // Toggle dropdown on hamburger click
  const btn = document.getElementById('navHamburger');
  const menu = document.getElementById('navDropdown');
  if (btn && menu) {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
      menu.classList.add('hidden');
    });

    // Prevent dropdown from closing when clicking inside it
    menu.addEventListener('click', (e) => {
      e.stopPropagation();
    });
  }
</script>