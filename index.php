<?php
session_start();
include 'db.php';

$stmt = $conn->prepare("SELECT rt.id, rt.name, rt.max_occupancy, rt.image, rt.base_price AS min_price,
  COUNT(b.id) AS bookings_count
  FROM room_types rt
  LEFT JOIN rooms r ON r.room_type_id = rt.id
  LEFT JOIN bookings b ON b.room_id = r.id AND b.booking_status = 'Confirmed'
  WHERE rt.status = 'Active'
  GROUP BY rt.id
  ORDER BY bookings_count DESC
  LIMIT 3");
$stmt->execute();
$popularVenues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rt_stmt = $conn->prepare("SELECT id, name FROM room_types WHERE status = 'Active' ORDER BY name");
$rt_stmt->execute();
$roomTypes = $rt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EliteStay — Premium Hotel Rooms</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    :root {
      --primary: #7c3aed;
      --primary-dark: #6d28d9;
      --primary-light: #a78bfa;
      --secondary: #1f2937;
      --accent: #ec4899;
      --accent-light: #f472b6;
      --bg-light: #f8f6fc;
      --bg-white: #ffffff;
      --text-dark: #1e1b4b;
      --text-light: #6b7280;
      --success: #10b981;
      --border: #e9d5ff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.6;
    }

    /* ===== HERO SECTION ===== */
    .hero {
      position: relative;
      height: 100vh;
      background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 50%, #5b21b6 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      text-align: center;
      color: white;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    .hero::after {
      content: '';
      position: absolute;
      bottom: -10%;
      left: 5%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(168, 85, 247, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      animation: float 8s ease-in-out infinite reverse;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(30px);
      }
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      margin-bottom: 3rem;
    }

    .hero-content h1 {
      font-size: 4rem;
      font-weight: 800;
      margin-bottom: 1rem;
      letter-spacing: -1px;
      text-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .hero-content p {
      font-size: 1.3rem;
      font-weight: 300;
      opacity: 0.95;
      margin-bottom: 2rem;
    }

    /* ===== SEARCH BOX ===== */
    .search-box {
      position: relative;
      z-index: 3;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 2.5rem;
      border-radius: 20px;
      max-width: 1000px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    .search-box form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1.5rem;
      width: 100%;
    }

    .search-box label {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-dark);
      display: block;
      margin-bottom: 0.5rem;
    }

    .search-box input,
    .search-box select {
      width: 100%;
      padding: 0.9rem;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s ease;
      background-color: var(--bg-white);
      color: var(--text-dark);
    }

    .search-box input:focus,
    .search-box select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }

    .search-box button {
      grid-column: 1 / -1;
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      font-weight: 700;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .search-box button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }

    /* ===== POPULAR ROOMS SECTION ===== */
    .venues {
      padding: 6rem 2rem;
      background: linear-gradient(180deg, var(--bg-light) 0%, var(--bg-white) 100%);
      text-align: center;
    }

    .venues h2 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 1rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .venues>p {
      font-size: 1.1rem;
      color: var(--text-light);
      margin-bottom: 4rem;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .venue-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2.5rem;
      max-width: 1300px;
      margin: auto;
    }

    .card {
      background: var(--bg-white);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(124, 58, 237, 0.08);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      border: 2px solid transparent;
    }

    .card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 50px rgba(124, 58, 237, 0.15);
      border-color: var(--primary-light);
    }

    .card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .card:hover img {
      transform: scale(1.05);
    }

    .card-content {
      padding: 2rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .card h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .card p {
      font-size: 0.95rem;
      color: var(--text-light);
      margin: 0.5rem 0;
    }

    .card .price {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--accent);
      margin: 1rem 0;
      flex-grow: 1;
    }

    .btn-book {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 0.9rem 1.8rem;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.9rem;
    }

    .btn-book:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }

    /* ===== ABOUT SECTION ===== */
    .about {
      padding: 6rem 2rem;
      background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 50%, #6d28d9 100%);
      color: white;
      text-align: center;
    }

    .about h2 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
    }

    .about>p {
      max-width: 700px;
      margin: auto 0 4rem;
      font-size: 1.05rem;
      opacity: 0.95;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      margin-top: 0;
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }

    .feature-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 16px;
      padding: 2.5rem 1.5rem;
      text-align: center;
      transition: all 0.3s ease;
      transform: translateY(0);
    }

    .feature-card:hover {
      transform: translateY(-10px);
      background: rgba(255, 255, 255, 0.15);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .feature-card i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: var(--accent-light);
    }

    .feature-card h4 {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 0.8rem;
    }

    .feature-card p {
      font-size: 0.95rem;
      opacity: 0.9;
    }

    /* ===== TESTIMONIAL SECTION ===== */
    .testimonial-section {
      padding: 6rem 2rem;
      background: var(--bg-white);
      text-align: center;
    }

    .testimonial-section h2 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 4rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .testimonials {
      display: flex;
      gap: 2rem;
      justify-content: center;
      flex-wrap: wrap;
      max-width: 1200px;
      margin: auto;
    }

    .testimonial {
      background: var(--bg-light);
      padding: 2rem;
      border-radius: 16px;
      max-width: 320px;
      box-shadow: 0 10px 30px rgba(124, 58, 237, 0.08);
      border-left: 4px solid var(--primary);
      transition: all 0.3s ease;
    }

    .testimonial:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(124, 58, 237, 0.15);
    }

    .testimonial p {
      font-style: italic;
      font-size: 0.95rem;
      color: var(--text-dark);
      margin-bottom: 1.5rem;
    }

    .testimonial h4 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--accent);
    }

    /* ===== CTA BANNER ===== */
    .cta-banner {
      padding: 5rem 2rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      text-align: center;
      margin: 4rem 2rem;
      border-radius: 20px;
      max-width: 1000px;
      margin-left: auto;
      margin-right: auto;
      position: relative;
      overflow: hidden;
    }

    .cta-banner::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      border-radius: 50%;
    }

    .cta-banner h2 {
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 1rem;
      position: relative;
      z-index: 1;
    }

    .cta-banner p {
      font-size: 1.1rem;
      margin-bottom: 2rem;
      position: relative;
      z-index: 1;
      opacity: 0.95;
    }

    .cta-banner button {
      padding: 1rem 2.5rem;
      background: white;
      color: var(--primary);
      font-weight: 700;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      position: relative;
      z-index: 1;
    }

    .cta-banner button:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    /* ===== FAQ SECTION ===== */
    .faq-section {
      padding: 6rem 2rem;
      background: var(--bg-light);
      text-align: center;
    }

    .faq-section h2 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 4rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .faq-item {
      max-width: 700px;
      margin: 2rem auto;
      text-align: left;
      background: var(--bg-white);
      padding: 2rem;
      border-radius: 12px;
      border-left: 4px solid var(--primary);
      box-shadow: 0 5px 15px rgba(124, 58, 237, 0.08);
      transition: all 0.3s ease;
    }

    .faq-item:hover {
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15);
      transform: translateX(5px);
    }

    .faq-item h4 {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.8rem;
    }

    .faq-item p {
      color: var(--text-light);
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /* ===== CONTACT SECTION ===== */
    .contact {
      background: var(--bg-white);
      padding: 6rem 2rem;
      text-align: center;
    }

    .contact h2 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .contact>p {
      color: var(--text-light);
      margin-bottom: 3rem;
      font-size: 1.05rem;
    }

    .contact-form {
      max-width: 600px;
      margin: auto;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .contact-form input,
    .contact-form textarea {
      padding: 1rem;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s ease;
      background-color: var(--bg-white);
      color: var(--text-dark);
    }

    .contact-form input:focus,
    .contact-form textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }

    .btn-submit {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 1rem;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-submit:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }

    /* ===== FOOTER ===== */
    .footer {
      background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%);
      color: white;
      text-align: center;
      padding: 3rem 2rem;
    }

    .footer p {
      margin-bottom: 1.5rem;
      font-size: 1rem;
    }

    .social-icons {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
    }

    .social-icons a {
      color: white;
      font-size: 1.5rem;
      transition: all 0.3s ease;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
    }

    .social-icons a:hover {
      background: var(--accent);
      transform: translateY(-5px);
    }

    /* ===== CHATBOT ===== */
    .chatbot-icon {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      cursor: pointer;
      box-shadow: 0 10px 30px rgba(124, 58, 237, 0.3);
      transition: all 0.3s ease;
      z-index: 999;
    }

    .chatbot-icon:hover {
      transform: scale(1.1);
      box-shadow: 0 15px 40px rgba(124, 58, 237, 0.4);
    }

    .chatbot-window {
      position: fixed;
      bottom: 5rem;
      right: 2rem;
      width: 350px;
      height: 450px;
      background: var(--bg-white);
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      display: none;
      flex-direction: column;
      z-index: 999;
      overflow: hidden;
    }

    .chat-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      padding: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .chat-header h4 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
    }

    .close-chat {
      background: none;
      border: none;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
      transition: transform 0.3s ease;
    }

    .close-chat:hover {
      transform: scale(1.2);
    }

    .chat-content {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
    }

    .user-message,
    .bot-message {
      margin: 0.5rem 0;
      padding: 0.8rem;
      border-radius: 8px;
      font-size: 0.9rem;
    }

    .user-message {
      background: var(--bg-light);
      color: var(--text-dark);
      margin-left: auto;
      max-width: 80%;
    }

    .bot-message {
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--accent-light) 100%);
      color: white;
      max-width: 80%;
    }

    .chat-input {
      border-top: 2px solid var(--border);
      padding: 1rem;
      display: flex;
      gap: 0.5rem;
    }

    .chat-input textarea {
      flex: 1;
      padding: 0.8rem;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      resize: none;
      font-size: 0.9rem;
    }

    .chat-input button {
      padding: 0.8rem 1.2rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 700;
      transition: all 0.3s ease;
    }

    .chat-input button:hover {
      transform: translateY(-2px);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      .hero-content h1 {
        font-size: 2.5rem;
      }

      .search-box {
        padding: 1.5rem;
      }

      .search-box form {
        grid-template-columns: 1fr;
      }

      .search-box button {
        grid-column: 1;
      }

      .venues h2,
      .about h2,
      .testimonial-section h2,
      .faq-section h2,
      .contact h2 {
        font-size: 2rem;
      }

      .venue-cards {
        grid-template-columns: 1fr;
      }

      .cta-banner {
        margin: 2rem 1rem;
        padding: 3rem 1.5rem;
      }

      .cta-banner h2 {
        font-size: 1.8rem;
      }

      .chatbot-window {
        width: 100vw;
        height: 100vh;
        max-width: none;
        bottom: 0;
        right: 0;
        border-radius: 0;
      }

      .chatbot-icon {
        bottom: 1rem;
        right: 1rem;
      }
    }
  </style>
</head>

<body>
  <?php include 'components/navbar.php'; ?>

  <section id="home" class="hero">
    <div class="hero-content">
      <h1>Find Your Perfect Room</h1>
      <p>Experience luxury, comfort, and unforgettable stays with EliteStay</p>
    </div>
    <div class="search-box">
      <form action="availableRooms.php" method="GET">
        <div>
          <label>Room Type</label>
          <select name="room_type">
            <option value="">Any</option>
            <?php if (!empty($roomTypes)): ?>
              <?php foreach ($roomTypes as $rt): ?>
                <option value="<?php echo htmlspecialchars($rt['id']); ?>"><?php echo htmlspecialchars($rt['name']); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label>City</label>
          <select name="city">
            <option value="">Any</option>
            <option value="Dhaka">Dhaka</option>
            <option value="Chittagong">Chittagong</option>
            <option value="Sylhet">Sylhet</option>
            <option value="Rajshahi">Rajshahi</option>
          </select>
        </div>

        <div>
          <label>Area</label>
          <input type="text" name="area" placeholder="Enter area" />
        </div>

        <div>
          <label>Guests</label>
          <input type="number" name="guests" placeholder="No. of Guests" min="1" />
        </div>

        <div>
          <label>Check-in</label>
          <input type="date" name="check_in" />
        </div>

        <div>
          <label>Check-out</label>
          <input type="date" name="check_out" />
        </div>

        <button type="submit">Search Rooms</button>
      </form>
    </div>
  </section>

  <section class="venues">
    <h2>Popular Room Types</h2>
    <p>Discover our most-loved accommodations featuring premium comfort and world-class amenities</p>
    <div class="venue-cards">
      <?php foreach ($popularVenues as $venue): ?>
        <div class="card">
          <img src="images/rooms/<?php echo htmlspecialchars($venue['image']); ?>" alt="<?php echo htmlspecialchars($venue['name']); ?>" onerror="this.src='images/default-room.jpg'" />
          <div class="card-content">
            <h3><?php echo htmlspecialchars($venue['name']); ?></h3>
            <p class="capacity"><i class="fas fa-users"></i> Max Occupancy: <?php echo htmlspecialchars($venue['max_occupancy']); ?> Guests</p>
            <p class="price">BDT <?php echo number_format($venue['min_price'], 0); ?>/night</p>
            <button class="btn-book" onclick="window.location.href='roomdetails.php?room_type_id=<?= $venue['id'] ?>'"><i class="fas fa-arrow-right"></i> View Details</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="about" class="about">
    <h2>Why Choose EliteStay?</h2>
    <p>Experience luxury and comfort with our exclusive collection of premium hotel rooms. Book your perfect stay today and enjoy world-class amenities and exceptional service.</p>
    <div class="features-grid">
      <div class="feature-card">
        <i class="fas fa-star"></i>
        <h4>Premium Comfort</h4>
        <p>Luxury rooms designed for your ultimate relaxation and comfort.</p>
      </div>
      <div class="feature-card">
        <i class="fas fa-bolt"></i>
        <h4>Instant Booking</h4>
        <p>Quick and easy online reservation process in just a few clicks.</p>
      </div>
      <div class="feature-card">
        <i class="fas fa-map-marker-alt"></i>
        <h4>Prime Locations</h4>
        <p>Strategically located rooms in the heart of the city.</p>
      </div>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <i class="fas fa-concierge-bell"></i>
        <h4>24/7 Service</h4>
        <p>Round-the-clock customer support for your peace of mind.</p>
      </div>
      <div class="feature-card">
        <i class="fas fa-shield-alt"></i>
        <h4>Secure Booking</h4>
        <p>100% secure transactions with encrypted payment processing.</p>
      </div>
      <div class="feature-card">
        <i class="fas fa-gift"></i>
        <h4>Best Prices</h4>
        <p>Competitive rates with exclusive deals and discounts available.</p>
      </div>
    </div>
  </section>

  <section class="testimonial-section">
    <h2>Guest Stories</h2>
    <div class="testimonials">
      <div class="testimonial">
        <p>"EliteStay offered me the perfect room for my business trip. The booking was seamless and the amenities were exceptional!"</p>
        <h4>- Sarah Johnson</h4>
      </div>
      <div class="testimonial">
        <p>"Best hotel experience ever! The staff was friendly, rooms were immaculate, and the location was perfect for exploring the city."</p>
        <h4>- Imran Ahmed</h4>
      </div>
      <div class="testimonial">
        <p>"Wonderful stay with my family. Great rooms, excellent service, and very reasonable prices. Will definitely book again!"</p>
        <h4>- Fatima Rahman</h4>
      </div>
    </div>
  </section>

  <section class="cta-banner">
    <h2>Ready for Your Next Adventure?</h2>
    <p>Book your ideal room today and experience the comfort and luxury you deserve</p>
    <button onclick="window.location.href='roomsList.php'">Browse All Rooms</button>
  </section>

  <section class="faq-section">
    <h2>Frequently Asked Questions</h2>
    <div class="faq-item">
      <h4><i class="fas fa-question-circle"></i> How do I book a room?</h4>
      <p>Simply browse our room collection, select your preferred dates, and follow the easy checkout process. You can complete your booking in just a few minutes!</p>
    </div>
    <div class="faq-item">
      <h4><i class="fas fa-question-circle"></i> Can I modify or cancel my booking?</h4>
      <p>Yes! You can manage your bookings anytime from your account dashboard. Cancellations are processed according to our cancellation policy.</p>
    </div>
    <div class="faq-item">
      <h4><i class="fas fa-question-circle"></i> What payment methods do you accept?</h4>
      <p>We accept multiple payment methods including credit cards, debit cards, and mobile wallet payments for your convenience.</p>
    </div>
    <div class="faq-item">
      <h4><i class="fas fa-question-circle"></i> Is there a loyalty program?</h4>
      <p>Yes! Our membership program offers exclusive benefits, discounts, and rewards for frequent bookers. Join now to start earning points!</p>
    </div>
  </section>

  <section id="contact" class="contact">
    <h2>Get In Touch</h2>
    <p>Have questions? We'd love to hear from you. Drop us a message anytime!</p>
    <form class="contact-form">
      <input type="text" placeholder="Your Name" required />
      <input type="email" placeholder="Your Email" required />
      <textarea placeholder="Your Message" rows="5" required></textarea>
      <button type="submit" class="btn-submit">Send Message</button>
    </form>
  </section>

  <footer class="footer">
    <p>&copy; 2025 EliteStay. All rights reserved. | Premium Hotel Booking Platform</p>
    <div class="social-icons">
      <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
      <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
      <a href="#" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
    </div>
  </footer>

  <div id="chatbot-icon" class="chatbot-icon">
    <i class="fas fa-comments"></i>
  </div>

  <div id="chatbot-window" class="chatbot-window">
    <div class="chat-header">
      <h4>Chat with EliteStay</h4>
      <button id="close-chat" class="close-chat">&times;</button>
    </div>
    <div id="chat-content" class="chat-content"></div>
    <div class="chat-input">
      <textarea id="chat-message" placeholder="Type your message..." rows="3"></textarea>
      <button id="send-chat" onclick="sendMessage()">Send</button>
    </div>
  </div>

  <script>
    document.getElementById('chatbot-icon').onclick = function() {
      document.getElementById('chatbot-window').style.display = 'flex';
    };

    document.getElementById('close-chat').onclick = function() {
      document.getElementById('chatbot-window').style.display = 'none';
    };

    function sendMessage() {
      var message = document.getElementById('chat-message').value;
      if (message.trim() === '') return;

      var chatContent = document.getElementById('chat-content');
      chatContent.innerHTML += `<div class="user-message">${message}</div>`;
      document.getElementById('chat-message').value = '';

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'chatbot.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          chatContent.innerHTML += `<div class="bot-message">${response.response}</div>`;
          chatContent.scrollTop = chatContent.scrollHeight;
        }
      };
      xhr.send('query=' + encodeURIComponent(message));
    }
  </script>
   <!-- Zapier Chatbot Embed -->
  <script async type='module'
    src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script>
  <zapier-interfaces-chatbot-embed is-popup='true' chatbot-id='cmjcec2e2003kt64t301y5j8p'>
  </zapier-interfaces-chatbot-embed>
</body>

</html>