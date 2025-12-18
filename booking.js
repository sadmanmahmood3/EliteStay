// GSAP Animations
document.addEventListener("DOMContentLoaded", () => {
  gsap.to(".booking-container", { 
      opacity: 1, 
      y: 0, 
      duration: 1.5, 
      ease: "power3.out" 
  });

  // Button Click Effect
  const btn = document.querySelector(".glowing-btn");
  btn.addEventListener("click", (e) => {
      e.preventDefault();
      gsap.to(".booking-container", { 
          scale: 0.9, 
          duration: 0.1, 
          yoyo: true, 
          repeat: 1 
      });

      setTimeout(() => {
          alert("Booking Confirmed! 🎉");
      }, 500);
  });
});