<?php
include "db.php";

$venue_id = $_GET['venue_id'];
$selected_date = $_GET['date'];

$stmt = $conn->prepare("
  SELECT vs.slot_time,
         CASE 
           WHEN EXISTS (
             SELECT 1 FROM bookings b 
             WHERE b.venue_id = vs.venue_id 
               AND b.booking_date = ? 
               AND b.timeslot = vs.slot_time
           ) THEN 0
           ELSE 1
         END AS is_available
  FROM venue_slots vs
  WHERE vs.venue_id = ?
");
$stmt->bind_param("si", $selected_date, $venue_id);
$stmt->execute();
$slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['slots' => $slots]);
$conn->close();
