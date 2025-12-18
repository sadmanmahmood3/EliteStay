// Hamburger Menu Toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');

hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('active');
});

// Smooth Scrolling for Navigation Links
document.querySelectorAll('.nav-links a').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const targetId = this.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);
    targetElement.scrollIntoView({ behavior: 'smooth' });

    // Close mobile menu after clicking a link
    navLinks.classList.remove('active');
  });
});

// Button Hover Effects
document.querySelectorAll('.btn-book, .btn-submit').forEach(button => {
  button.addEventListener('mouseenter', () => {
    button.style.transform = 'scale(1.05)';
  });
  button.addEventListener('mouseleave', () => {
    button.style.transform = 'scale(1)';
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const carousel = document.querySelector(".carousel");
  const carouselInner = document.querySelector(".carousel-inner");
  const prevButton = document.querySelector(".carousel-prev");
  const nextButton = document.querySelector(".carousel-next");
  const items = document.querySelectorAll(".carousel-item");

  let currentIndex = 0;
  const totalItems = items.length;

  function updateCarousel() {
    const offset = -currentIndex * 100; // Move items left based on index
    carouselInner.style.transform = `translateX(${offset}%)`;
  }

  nextButton.addEventListener("click", function () {
    currentIndex = (currentIndex + 1) % totalItems;
    updateCarousel();
  });

  prevButton.addEventListener("click", function () {
    currentIndex = (currentIndex - 1 + totalItems) % totalItems;
    updateCarousel();
  });

  // Auto-slide every 3 seconds
  setInterval(() => {
    currentIndex = (currentIndex + 1) % totalItems;
    updateCarousel();
  }, 3000);
});
