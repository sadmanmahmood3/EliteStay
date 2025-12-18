<?php include 'navbar.php'; ?>
<style>
  /* Global Styles */
  :root {
    --primary-color: #006400;
    /* Deep Green (Pin color) for key elements */
    --secondary-color: #f1c40f;
    /* Warm Gold for accents */
    --background-color: #f7f9fb;
    /* Light background for the page */
    --text-color: #333;
    /* Dark text color for readability */
    --cta-background: #ffffff;
    /* White background for CTA to stand out */
  }

  .book-now {
    padding: 4rem 2rem;
    background: var(--background-color);
    /* Neutral background */
    text-align: center;
    color: var(--text-color);
  }

  /* Full-Width Hero Section (CTA) */
  .cta {
    width: 100%;
    background: linear-gradient(to top, #006400, #004d00);
    text-align: center;
    color: white;
  }

  .cta-content {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem;
    border-radius: 10px;
  }

  .cta h1 {
    font-size: 3.5rem;
    margin-bottom: 1.5rem;
  }

  .cta p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
  }

  .btn-cta {
    background-color: var(--secondary-color);
    /* Gold */
    color: var(--background-color);
    font-size: 1.3rem;
    padding: 1rem 2.5rem;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
  }

  .btn-cta:hover {
    background-color: #f39c12;
    /* Slightly brighter gold for hover */
    transform: scale(1.05);
    /* Subtle scale transition */
  }

  /* Booking Process Overview */
  .process-overview {
    margin-top: 4rem;
    max-width: 1000px;
    margin: 2rem auto;
  }

  .process-overview h3 {
    font-size: 2.5rem;
    color: var(--primary-color);
    /* Deep Green */
    margin-bottom: 2rem;
  }

  .steps {
    display: flex;
    justify-content: space-around;
    margin-top: 2rem;
  }

  .step {
    background: white;
    padding: 2.5rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 25%;
    text-align: center;
    transition: all 0.3s ease;
  }

  .step:hover {
    background-color: #f4f4f4;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
  }

  .step i {
    font-size: 3rem;
    color: var(--primary-color);
    /* Deep Green */
    margin-bottom: 1rem;
  }

  .step h4 {
    font-size: 1.5rem;
    color: var(--primary-color);
    /* Deep Green */
    margin-bottom: 1rem;
  }

  .step p {
    font-size: 1.1rem;
    color: #555;
  }

  /* Additional Information */
  .additional-info {
    margin-top: 3rem;
    max-width: 900px;
    margin: 2rem auto;
    text-align: center;
  }

  .additional-info h3 {
    font-size: 2rem;
    color: var(--primary-color);
    /* Deep Green */
    margin-bottom: 1rem;
  }

  /* Book Now Button */
  .cta-button {
    margin-top: 3rem;
  }

  .cta-button a {
    font-size: 1.4rem;
    padding: 1rem 2.5rem;
    background-color: var(--secondary-color);
    /* Gold */
    color: var(--background-color);
    border-radius: 30px;
    text-decoration: none;
    transition: background-color 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
  }

  .cta-button a:hover {
    background-color: #f39c12;
    /* Brighter gold on hover */
  }
</style>

<section class="book-now">
  <!-- Full-Width Hero Section with Bold CTA -->
  <div class="cta">
    <div class="cta-content">
      <h1>Find the Perfect Venue for Your Event!</h1>
      <p>Your dream event starts with the right venue. Let's find the one that's perfect for you.</p>
      <a href="venues.php" class="btn-cta">Browse Venues</a>
    </div>
  </div>

  <!-- Booking Process Overview Section -->
  <div class="process-overview">
    <h3>How It Works:</h3>
    <div class="steps">
      <div class="step">
        <i class="fas fa-building"></i>
        <h4>Select Venue</h4>
        <p>Choose the perfect venue that suits your event needs.</p>
      </div>
      <div class="step">
        <i class="fas fa-calendar-alt"></i>
        <h4>Pick Date & Time</h4>
        <p>Choose the best date and time for your event.</p>
      </div>
      <div class="step">
        <i class="fas fa-credit-card"></i>
        <h4>Confirm & Pay</h4>
        <p>Confirm your booking and proceed with payment securely.</p>
      </div>
    </div>
  </div>

  <!-- Additional Information (Optional) -->
  <div class="additional-info">
    <h3>Why Choose Us?</h3>
    <p>We offer a variety of venues with personalized services and flexible packages to make your event truly special.</p>
  </div>

  <!-- Book Now Button -->
  <div class="cta-button">
    <a href="venues.php" class="btn-cta">Browse Venues</a>
  </div>
</section>