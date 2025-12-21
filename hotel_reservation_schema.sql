

-- ===================================================================
-- 1. MEMBERSHIP TIERS TABLE
-- ===================================================================
CREATE TABLE `membership_tiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tier_level` int(11) NOT NULL UNIQUE COMMENT 'Higher = higher tier (1=Cardholder, 2=Stakeholder)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO membership_tiers (id, name, description, tier_level) VALUES
(1, 'Cardholder', 'Standard membership card holder with basic benefits', 1),
(2, 'Stakeholder', 'Premium stakeholder with exclusive access and special privileges', 2);


-- ===================================================================
-- 2. MEMBERS TABLE
-- NOTE: corrected INSERTs so the provided id value maps to `id`.
-- ===================================================================
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL COMMENT 'Use bcrypt via password_hash()',
  `phone` varchar(20) DEFAULT NULL,
  `membership_tier_id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `membership_tier_id` (`membership_tier_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`membership_tier_id`) REFERENCES `membership_tiers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Corrected: include `id` in the column list since values include id as first value
INSERT INTO `members`
  (`id`, `name`, `email`, `password`, `phone`, `membership_tier_id`, `city`, `area`, `avatar`, `status`)
VALUES
  (1, 'Rajesh Kumar', 'rajesh.kumar@example.com', 'hashed_password_1', '9876543210', 1, 'New Delhi', 'Connaught Place', 'avatar1.jpg', 'Active'),
  (2, 'Priya Sharma', 'priya.sharma@example.com', 'hashed_password_2', '9876543211', 2, 'Mumbai', 'Bandra', 'avatar2.jpg', 'Active'),
  (3, 'Amit Patel', 'amit.patel@example.com', 'hashed_password_3', '9876543212', 1, 'Bangalore', 'Koramangala', 'avatar3.jpg', 'Active'),
  (4, 'Neha Singh', 'neha.singh@example.com', 'hashed_password_4', '9876543213', 2, 'Hyderabad', 'Jubilee Hills', 'avatar4.jpg', 'Active'),
  (5, 'Vikram Reddy', 'vikram.reddy@example.com', 'hashed_password_5', '9876543214', 1, 'Chennai', 'Nungambakkam', 'avatar5.jpg', 'Active');


-- ===================================================================
-- 3. ADMINISTRATORS TABLE
-- ===================================================================
CREATE TABLE `administrators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL COMMENT 'Use bcrypt via password_hash()',
  `role` enum('SuperAdmin','Manager','Viewer') DEFAULT 'Manager',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Corrected: include `id` in the column list since values include id as first value
INSERT INTO `administrators`
  (`id`, `name`, `email`, `password`, `role`, `status`)
VALUES
  (1, 'Admin User', 'admin@hotel.com', 'hashed_admin_pass', 'SuperAdmin', 'Active'),
  (2, 'Manager User', 'manager@hotel.com', 'hashed_manager_pass', 'Manager', 'Active'),
  (3, 'Viewer User', 'viewer@hotel.com', 'hashed_viewer_pass', 'Viewer', 'Active');


-- ===================================================================
-- 4. ROOM TYPES TABLE
-- ===================================================================
CREATE TABLE `room_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL COMMENT 'Price per night in currency',
  `max_occupancy` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `features` text DEFAULT NULL COMMENT 'JSON or comma-separated: AC, WiFi, Balcony, TV, etc.',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Corrected: include `id` in column list to match provided values
INSERT INTO `room_types`
  (`id`, `name`, `description`, `base_price`, `max_occupancy`, `image_url`, `features`, `status`)
VALUES
  (1, 'Standard Room', 'Comfortable room with essential amenities for 1-2 guests', 5000.00, 2, 'standard_room.jpg', 'AC,WiFi,TV,Private Bathroom,Work Desk', 'Active'),
  (2, 'Deluxe Room', 'Spacious room with premium furnishings and city view', 8000.00, 2, 'deluxe_room.jpg', 'AC,WiFi,TV,Private Bathroom,Work Desk,Bathrobe,Minibar', 'Active'),
  (3, 'Suite', 'Luxury suite with separate living area and premium services', 15000.00, 4, 'suite_room.jpg', 'AC,WiFi,TV,Private Bathroom,Work Desk,Bathrobe,Minibar,Living Area,Jacuzzi', 'Active'),
  (4, 'Executive Room', 'Business-focused room with conference facilities', 10000.00, 2, 'executive_room.jpg', 'AC,WiFi,TV,Private Bathroom,Work Desk,Conference Phone,Printer', 'Active');

-- ===================================================================
-- 5. ROOMS TABLE (Inventory)
-- ===================================================================
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(20) NOT NULL UNIQUE COMMENT 'e.g., 101, 205A, 501-B',
  `room_type_id` int(11) NOT NULL,
  `floor` int(11) DEFAULT NULL,
  `status` enum('Available','Occupied','Maintenance','Disabled') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_number` (`room_number`),
  KEY `room_type_id` (`room_type_id`),
  KEY `floor` (`floor`),
  KEY `status` (`status`),
  FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rooms` (`id`, `room_number`, `room_type_id`, `floor`, `status`) VALUES
(1, '101', 1, 1, 'Available'),
(2, '102', 1, 1, 'Available'),
(3, '103', 1, 1, 'Maintenance'),
(4, '201', 2, 1, 'Available'),
(5, '202', 2, 1, 'Available'),
(6, '203', 3, 2, 'Available'),
(7, '301', 3, 2, 'Available'),
(8, '302', 4, 3, 'Available'),
(9, '303', 4, 3, 'Available'),
(10, '401', 4, 3, 'Available');

-- ===================================================================
-- 6. TIER-ROOM ACCESS (Pricing & Eligibility)
-- ===================================================================
CREATE TABLE `tier_room_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_tier_id` int(11) NOT NULL,
  `room_type_id` int(11) NOT NULL,
  `special_price` decimal(10,2) DEFAULT NULL COMMENT 'NULL = use base_price; otherwise override',
  `discount_percentage` decimal(5,2) DEFAULT 0 COMMENT 'e.g., 10.00 for 10% discount',
  `is_exclusive` enum('Yes','No') DEFAULT 'No' COMMENT 'Yes = only this tier can book',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tier_room` (`membership_tier_id`, `room_type_id`),
  FOREIGN KEY (`membership_tier_id`) REFERENCES `membership_tiers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert tier-room access
INSERT INTO `tier_room_access` (`membership_tier_id`, `room_type_id`, `special_price`, `discount_percentage`, `is_exclusive`) VALUES
(1, 1, NULL, 0.00, 'No'),
(1, 2, NULL, 5.00, 'No'),
(2, 1, NULL, 10.00, 'No'),
(2, 2, NULL, 15.00, 'No'),
(2, 3, NULL, 20.00, 'No'),
(2, 4, NULL, 15.00, 'No');

-- ===================================================================
-- 7. MEMBERSHIP PERKS TABLE
-- ===================================================================
CREATE TABLE `membership_perks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_tier_id` int(11) NOT NULL,
  `perk_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` enum('Yes','No') DEFAULT 'Yes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `membership_tier_id` (`membership_tier_id`),
  FOREIGN KEY (`membership_tier_id`) REFERENCES `membership_tiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample perks
INSERT INTO `membership_perks` (`membership_tier_id`, `perk_name`, `description`, `is_active`) VALUES
(1, 'Complimentary WiFi', 'Free high-speed WiFi in all rooms and common areas', 'Yes'),
(1, 'Breakfast Voucher', 'One complimentary breakfast per stay', 'Yes'),
(2, 'Complimentary WiFi', 'Free high-speed WiFi in all rooms and common areas', 'Yes'),
(2, 'Complimentary Breakfast', 'Full complimentary breakfast daily', 'Yes'),
(2, 'Spa Access', 'Free access to hotel spa facilities', 'Yes'),
(2, 'Late Checkout', 'Complimentary late checkout until 4:00 PM', 'Yes'),
(2, 'Airport Transfer', 'Complimentary airport pickup/drop service', 'Yes');

-- ===================================================================
-- 8. BOOKINGS TABLE
-- ===================================================================
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `num_guests` int(11) NOT NULL DEFAULT 1,
  `booking_status` enum('Pending','Confirmed','Checked_In','Checked_Out','Cancelled') DEFAULT 'Pending',
  `cancellation_status` enum('None','Requested','Approved','Rejected') DEFAULT 'None',
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `cancellation_requested_at` timestamp NULL DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `room_id` (`room_id`),
  KEY `check_in_date` (`check_in_date`),
  KEY `check_out_date` (`check_out_date`),
  KEY `booking_status` (`booking_status`),
  KEY `payment_status` (`payment_status`),
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample bookings
INSERT INTO `bookings` (`member_id`, `room_id`, `check_in_date`, `check_out_date`, `num_guests`, `booking_status`, `cancellation_status`, `total_price`, `payment_status`) VALUES
(1, 1, '2025-12-01', '2025-12-03', 1, 'Confirmed', 'None', 10000.00, 'Completed'),
(2, 6, '2025-12-05', '2025-12-07', 2, 'Confirmed', 'None', 30000.00, 'Completed'),
(3, 2, '2025-12-10', '2025-12-12', 1, 'Pending', 'None', 10000.00, 'Pending'),
(4, 7, '2025-12-15', '2025-12-17', 3, 'Confirmed', 'Requested', 45000.00, 'Completed'),
(5, 4, '2025-12-20', '2025-12-22', 2, 'Confirmed', 'None', 16000.00, 'Completed');

-- ===================================================================
-- 9. PAYMENTS TABLE
-- ===================================================================
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL COMMENT 'Credit Card, PayPal, Stripe, UPI, Net Banking, etc.',
  `transaction_id` varchar(255) UNIQUE DEFAULT NULL COMMENT 'Gateway transaction reference',
  `payment_status` enum('Pending','Success','Failed') DEFAULT 'Pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `receipt_number` varchar(50) UNIQUE DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `payment_status` (`payment_status`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample payments
INSERT INTO `payments` (`booking_id`, `amount`, `payment_method`, `transaction_id`, `payment_status`, `payment_date`, `receipt_number`) VALUES
(1, 10000.00, 'Credit Card', 'TXN001', 'Success', '2025-11-20 10:30:00', 'RCP001'),
(2, 30000.00, 'PayPal', 'TXN002', 'Success', '2025-11-22 14:15:00', 'RCP002'),
(3, 10000.00, 'Credit Card', 'TXN003', 'Pending', NULL, NULL),
(4, 45000.00, 'Net Banking', 'TXN004', 'Success', '2025-11-25 09:45:00', 'RCP004'),
(5, 16000.00, 'UPI', 'TXN005', 'Success', '2025-11-26 11:20:00', 'RCP005');

-- ===================================================================
-- 10. NOTIFICATIONS TABLE
-- ===================================================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `notification_type` enum('Booking_Confirmation','Payment_Receipt','Cancellation_Notice','Reminder','Promotional') DEFAULT 'Booking_Confirmation',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `channel` enum('Email','SMS','Both') DEFAULT 'Email',
  `is_sent` enum('Yes','No') DEFAULT 'No',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `booking_id` (`booking_id`),
  KEY `is_sent` (`is_sent`),
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample notifications
INSERT INTO `notifications` (`member_id`, `booking_id`, `notification_type`, `subject`, `message`, `channel`, `is_sent`) VALUES
(1, 1, 'Booking_Confirmation', 'Your Booking Confirmation - Room 101', 'Dear Rajesh, your booking for Room 101 from Dec 1-3 has been confirmed.', 'Email', 'Yes'),
(2, 2, 'Payment_Receipt', 'Payment Received - Room 203', 'Dear Priya, your payment of Rs. 30,000 has been successfully processed.', 'Email', 'Yes'),
(3, 3, 'Booking_Confirmation', 'Your Booking Confirmation - Room 102', 'Dear Amit, your booking for Room 102 from Dec 10-12 is pending confirmation.', 'Both', 'No');

-- ===================================================================
-- 11. ROOM IMAGES TABLE
-- ===================================================================
CREATE TABLE `room_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_type_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_type_id` (`room_type_id`),
  FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample room images
INSERT INTO `room_images` (`room_type_id`, `image_url`, `display_order`) VALUES
(1, 'images/rooms/standard_1.jpg', 1),
(1, 'images/rooms/standard_2.jpg', 2),
(2, 'images/rooms/deluxe_1.jpg', 1),
(2, 'images/rooms/deluxe_2.jpg', 2),
(3, 'images/rooms/suite_1.jpg', 1),
(3, 'images/rooms/suite_2.jpg', 2),
(4, 'images/rooms/executive_1.jpg', 1);

-- ===================================================================
-- 12. ROOM AVAILABILITY TABLE (Optional: for blocked dates)
-- ===================================================================
CREATE TABLE `room_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `is_available` enum('Yes','No') DEFAULT 'Yes',
  `reason` varchar(255) DEFAULT NULL COMMENT 'Maintenance, cleaning, etc.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_room_date` (`room_id`, `available_date`),
  KEY `available_date` (`available_date`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample availability (mark some dates as unavailable)
INSERT INTO `room_availability` (`room_id`, `available_date`, `is_available`, `reason`) VALUES
(1, '2025-12-01', 'No', 'Maintenance'),
(3, '2025-12-05', 'No', 'Deep cleaning'),
(3, '2025-12-06', 'No', 'Deep cleaning');

-- ===================================================================
-- 13. AUDIT LOGS TABLE
-- ===================================================================
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL COMMENT 'e.g., Room Created, Booking Cancelled',
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'e.g., Room, Booking, Member',
  `entity_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `created_at` (`created_at`),
  KEY `action` (`action`),
  FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample audit logs
INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`) VALUES
(1, 'Room Created', 'Room', 1, NULL, '{\"room_number\": \"101\", \"room_type_id\": 1, \"floor\": 1}'),
(1, 'Booking Confirmed', 'Booking', 1, '{\"booking_status\": \"Pending\"}', '{\"booking_status\": \"Confirmed\"}'),
(2, 'Member Updated', 'Member', 2, '{\"status\": \"Active\"}', '{\"status\": \"Active\"}');

-- ===================================================================
-- AUTO_INCREMENT SETTINGS
-- ===================================================================
ALTER TABLE `membership_tiers` AUTO_INCREMENT = 3;
ALTER TABLE `members` AUTO_INCREMENT = 6;
ALTER TABLE `administrators` AUTO_INCREMENT = 4;
ALTER TABLE `room_types` AUTO_INCREMENT = 5;
ALTER TABLE `rooms` AUTO_INCREMENT = 11;
ALTER TABLE `tier_room_access` AUTO_INCREMENT = 7;
ALTER TABLE `membership_perks` AUTO_INCREMENT = 8;
ALTER TABLE `bookings` AUTO_INCREMENT = 6;
ALTER TABLE `payments` AUTO_INCREMENT = 6;
ALTER TABLE `notifications` AUTO_INCREMENT = 4;
ALTER TABLE `room_images` AUTO_INCREMENT = 8;
ALTER TABLE `room_availability` AUTO_INCREMENT = 4;
ALTER TABLE `audit_logs` AUTO_INCREMENT = 4;

-- ===================================================================
-- FINAL COMMIT
-- ===================================================================
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
