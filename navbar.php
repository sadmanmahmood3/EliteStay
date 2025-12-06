<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$current    = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer';
$userName   = $_SESSION['user_name']   ?? '';
$userAvatar = isset($_SESSION['user_avatar']) ? $_SESSION['user_avatar'] : 'default-avatar.png'; // Check if avatar exists, else use a default

// capture full request URI (path + query) for redirect
$currentUrl = $_SERVER['REQUEST_URI'];
?>
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
  }

  .nav-links a {
    text-decoration: none;
    color: #374151;
    font-weight: 500;
  }

  .nav-links a.active {
    color: #ff4d6d;
    border-bottom: 2px solid #ff4d6d;
  }

  .btn-login {
    background: #ff4d6d;
    color: #fff;
    padding: .5rem 1.2rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
  }

  /* Profile/avatar styling */
  .nav-profile {
    position: relative;
    display: flex;
    align-items: center;
  }

  .nav-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: .5rem;
  }

  .nav-hamburger {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0;
  }

  .nav-hamburger span {
    width: 20px;
    height: 2px;
    background: #374151;
    border-radius: 2px;
  }

  .nav-dropdown {
    position: absolute;
    top: 56px;
    right: 0;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: .5rem;
    overflow: hidden;
    transform-origin: top right;
    transition: opacity .2s, transform .2s;
  }

  .nav-dropdown.hidden {
    opacity: 0;
    transform: scale(.95);
    pointer-events: none;
  }

  .nav-dropdown ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .nav-dropdown li a {
    display: block;
    padding: .75rem 1.5rem;
    color: #374151;
    text-decoration: none;
    font-weight: 500;
  }

  .nav-dropdown li a:hover {
    background: #f9fafb;
  }
</style>

<nav class="navbar">
  <div class="inner">
    <div class="logo">EliteStay</div>
    <ul class="nav-links">
      <li><a href="index.php" class="<?= $current === 'index.php'   ? 'active' : '' ?>">Home</a></li>
      <li><a href="#about" class="<?= $current === 'about.php'   ? 'active' : '' ?>">About</a></li>
      <li><a href="venues.php" class="<?= $current === 'venues.php'  ? 'active' : '' ?>">Venues</a></li>
      <li><a href="bookNow.php" class="<?= $current === 'bookNow.php' ? 'active' : '' ?>">Book Now</a></li>
      <li><a href="addOns.php" class="<?= $current === 'addOns.php'  ? 'active' : '' ?>">Services</a></li>
      <li><a href="#contact" class="<?= $current === 'contact.php' ? 'active' : '' ?>">Contact</a></li>

      <?php if (! $isLoggedIn): ?>
        <li>
          <button
            class="btn-login"
            onclick="location.href='login.php?redirect=<?= urlencode($currentUrl) ?>'">Login</button>
        </li>
      <?php else: ?>
        <li class="nav-profile">
          <img
            src="<?= '/images/avatar/' . htmlspecialchars($userAvatar) ?>"
            alt="Avatar"
            class="nav-avatar">
          <button id="navHamburger" class="nav-hamburger">
            <span></span><span></span><span></span>
          </button>
          <nav id="navDropdown" class="nav-dropdown hidden">
            <ul>
              <li><a href="customer_dashboard.php">Dashboard</a></li>
              <li><a href="customerBooking.php">My Bookings</a></li>
              <li><a href="customerProfile.php">Profile</a></li>
              <li>
                <a href="logout.php?redirect=<?= urlencode($currentUrl) ?>">
                  Logout
                </a>
              </li>
            </ul>
  </div>
  </ul>
</nav>

<script>
  function adjustBodyPaddingForNavbar() {
    var nav = document.querySelector('.navbar');
    if (!nav) return;
    var h = nav.offsetHeight;
    document.documentElement.style.setProperty('--navbar-height', h + 'px');
    document.body.style.paddingTop = h + 'px';
  }
  window.addEventListener('load', adjustBodyPaddingForNavbar);
  window.addEventListener('resize', adjustBodyPaddingForNavbar);
</script>
</li>
<?php endif; ?>
</ul>
</nav>

<script>
  const btn = document.getElementById('navHamburger');
  const menu = document.getElementById('navDropdown');
  if (btn) {
    btn.addEventListener('click', () => {
      menu.classList.toggle('hidden');
    });
  }
</script>