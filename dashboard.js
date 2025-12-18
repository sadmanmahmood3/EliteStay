// Wait for the DOM to load
window.addEventListener('DOMContentLoaded', () => {
  // Bookings Over Time Chart (Line Chart)
  const ctxBookings = document.getElementById('bookingsChart');
  if (ctxBookings) {
    new Chart(ctxBookings, {
      type: 'line',
      data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          label: 'Bookings',
          data: [12, 19, 9, 17, 25, 22, 30],
          borderColor: '#1f0758',
          backgroundColor: 'rgba(12, 5, 27, 0.1)',
          fill: true,
          tension: 0.2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      }
    });
  }

  // Revenue Over Time Chart (Bar Chart)
  const ctxRevenue = document.getElementById('revenueChart');
  if (ctxRevenue) {
    new Chart(ctxRevenue, {
      type: 'bar',
      data: {
        labels: ['Week1', 'Week2', 'Week3', 'Week4'],
        datasets: [{
          label: 'Revenue ($)',
          data: [5000, 7500, 6000, 9000],
          backgroundColor: '#1f0758',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      }
    });
  }
});
