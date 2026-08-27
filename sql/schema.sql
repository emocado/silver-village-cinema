-- Silver Village Cinema Database Schema & Seed Data
-- Database: silver_village_cinema

CREATE DATABASE IF NOT EXISTS `silver_village_cinema` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `silver_village_cinema`;

-- 1. Users Table
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `booked_seats`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `booking_wishlist`;
DROP TABLE IF EXISTS `screenings`;
DROP TABLE IF EXISTS `halls`;
DROP TABLE IF EXISTS `movies`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'admin') DEFAULT 'customer',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Movies Table
CREATE TABLE `movies` (
  `movie_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `synopsis` TEXT NOT NULL,
  `genre` VARCHAR(100) NOT NULL,
  `duration_minutes` INT NOT NULL,
  `rating` VARCHAR(10) NOT NULL, -- PG, PG13, NC16, M18, R21
  `director` VARCHAR(100) NOT NULL,
  `cast` VARCHAR(255) NOT NULL,
  `poster_image` VARCHAR(255) NOT NULL,
  `backdrop_image` VARCHAR(255) DEFAULT '',
  `trailer_url` VARCHAR(255) DEFAULT '',
  `status` ENUM('now_showing', 'coming_soon') DEFAULT 'now_showing',
  `release_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Halls Table
CREATE TABLE `halls` (
  `hall_id` INT AUTO_INCREMENT PRIMARY KEY,
  `hall_name` VARCHAR(50) NOT NULL,
  `experience_type` VARCHAR(50) NOT NULL DEFAULT 'Dolby Atmos',
  `total_rows` INT NOT NULL,
  `seats_per_row` INT NOT NULL,
  `premium_row_start` INT NOT NULL, -- Row index (1-based) where premium recliner starts
  `standard_price` DECIMAL(6,2) NOT NULL DEFAULT 10.50,
  `premium_price` DECIMAL(6,2) NOT NULL DEFAULT 14.50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Screenings Table
CREATE TABLE `screenings` (
  `screening_id` INT AUTO_INCREMENT PRIMARY KEY,
  `movie_id` INT NOT NULL,
  `hall_id` INT NOT NULL,
  `screening_date` DATE NOT NULL,
  `screening_time` TIME NOT NULL,
  CONSTRAINT `fk_screenings_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_screenings_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`hall_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Booking Wishlist Table (Enables Multi-Booking Preferences)
CREATE TABLE `booking_wishlist` (
  `wishlist_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `screening_id` INT NOT NULL,
  `selected_seats` VARCHAR(255) NOT NULL, -- e.g. "D3,D4"
  `preference_rank` INT NOT NULL DEFAULT 1, -- 1 = Top Choice, 2 = Alternative, 3 = Backup
  `estimated_total` DECIMAL(8,2) NOT NULL,
  `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_screening` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Bookings Table
CREATE TABLE `bookings` (
  `booking_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_reference` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `screening_id` INT NOT NULL,
  `total_price` DECIMAL(8,2) NOT NULL,
  `status` ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
  `payment_status` ENUM('pending', 'success', 'failed') DEFAULT 'success',
  `booking_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_screening` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Booked Seats Table
CREATE TABLE `booked_seats` (
  `booked_seat_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `seat_label` VARCHAR(10) NOT NULL, -- e.g. "A3", "D5"
  `seat_type` ENUM('standard', 'premium') DEFAULT 'standard',
  `price` DECIMAL(6,2) NOT NULL,
  CONSTRAINT `fk_seats_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Feedback / Reviews Table
CREATE TABLE `feedback` (
  `feedback_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `movie_id` INT NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========================================================
-- SEED DATA
-- ========================================================

-- Users: Admin (password: Admin123!) and Customers (password: Pass1234!)
-- Password hashes generated via PHP password_hash(..., PASSWORD_DEFAULT)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `date_of_birth`, `password_hash`, `role`) VALUES
(1, 'Administrator', 'admin@silvervillage.local', '68881234', '1985-05-15', '$2y$10$ZiagQOVNRfqUuRwCvEYPsO7bRraBJCTiQ0BkULAQFCy/RY.5vRJ4u', 'admin'),
(2, 'Johnathan Tan', 'user@silvervillage.local', '91234567', '1998-08-20', '$2y$10$HbZeZ2/iqkToP0fsEh80I.BllcvX6pGsHc8SfPdRgYIkdPpDG743C', 'customer'),
(3, 'Sarah Lim', 'sarah@silvervillage.local', '98765432', '2001-11-04', '$2y$10$HbZeZ2/iqkToP0fsEh80I.BllcvX6pGsHc8SfPdRgYIkdPpDG743C', 'customer');

-- Halls
INSERT INTO `halls` (`hall_id`, `hall_name`, `experience_type`, `total_rows`, `seats_per_row`, `premium_row_start`, `standard_price`, `premium_price`) VALUES
(1, 'Hall A', 'Dolby Atmos 4K Laser', 6, 10, 4, 10.50, 14.50), -- 60 seats (Rows A-C standard, D-F premium)
(2, 'Hall B', 'VIP Premiere Recliner', 8, 10, 5, 12.50, 16.50), -- 80 seats (Rows A-D standard, E-H premium)
(3, 'Hall C', 'Standard Digital Cinema', 10, 10, 7, 10.50, 14.50); -- 100 seats (Rows A-F standard, G-J premium)

-- Movies (Real Currently-Screening & Upcoming blockbusters)
INSERT INTO `movies` (`movie_id`, `title`, `synopsis`, `genre`, `duration_minutes`, `rating`, `director`, `cast`, `poster_image`, `backdrop_image`, `status`, `release_date`) VALUES
(1, 'Spider-Man: Brand New Day', 'Peter Parker navigates life after the world forgot his identity. As a sinister underground crime syndicate emerges with dangerous high-tech weapons, Spider-Man must forge unexpected alliances to protect the city he loves.', 'Action, Sci-Fi, Adventure', 148, 'PG13', 'Destin Daniel Cretton', 'Tom Holland, Zendaya, Mark Ruffalo, Sadie Sink', 'spiderman.jpg', 'hero_spiderman.jpg', 'now_showing', '2026-07-30'),
(2, 'The Odyssey', 'An epic cinematic retelling of Homer\'s ancient legend. King Odysseus of Ithaca embarks on a perilous ten-year journey across treacherous seas, mythical beasts, and vengeful gods to return home to his kingdom and beloved Penelope.', 'Adventure, Drama, Mythological', 163, 'PG', 'Christopher Nolan', 'Christian Bale, Cillian Murphy, Florence Pugh', 'odyssey.jpg', 'hero_odyssey.jpg', 'now_showing', '2026-08-06'),
(3, 'Insidious: Out of the Further', 'The Lambert family thought they had sealed the dark realm forever. When paranormal anomalies resurface in a quiet coastal town, psychic investigators must venture deeper into the uncharted corners of The Further than ever before.', 'Horror, Mystery, Thriller', 105, 'NC16', 'Patrick Wilson', 'Patrick Wilson, Rose Byrne, Ty Simpkins, Lin Shaye', 'insidious.jpg', 'hero_insidious.jpg', 'now_showing', '2026-08-13'),
(4, 'The End of Oak Street', 'A quiet suburban neighborhood wakes up to discover that their entire street has been mysteriously transported into an uncharted, alien ecosystem surrounded by impenetrable mist. Survival forces neighbors to confront hidden secrets.', 'Sci-Fi, Mystery, Thriller', 120, 'PG13', 'David Robert Mitchell', 'Anne Hathaway, Ewan McGregor, Stanley Tucci', 'oak_street.jpg', 'hero_oak_street.jpg', 'now_showing', '2026-08-14'),
(5, 'Minions & Monsters', 'Gru and his mischievous yellow Minions discover a hidden world of ancient underground creatures. When a rogue monster escapes into the city, the Minions embark on a chaotic and heartwarming mission to save the day.', 'Animation, Comedy, Family', 95, 'PG', 'Pierre Coffin', 'Steve Carell, Pierre Coffin, Sandra Bullock', 'minions.jpg', 'hero_minions.jpg', 'now_showing', '2026-08-01'),
(6, 'The Dog Stars', 'In a post-apocalyptic world following a devastating global pandemic, a solitary pilot lives with his loyal dog at an abandoned airstrip. A faint radio signal offers a beacon of hope and leads him on a dangerous expedition across the continent.', 'Drama, Sci-Fi, Post-Apocalyptic', 132, 'M18', 'Ridley Scott', 'Jacob Elordi, Josh Brolin, Margaret Qualley', 'dog_stars.jpg', 'hero_dog_stars.jpg', 'now_showing', '2026-08-20'),
(7, 'Toy Story 5', 'Woody, Buzz, and the beloved toy gang face a modern dilemma when high-tech interactive smart gadgets capture children\'s attention. Together, they embark on a grand adventure to prove the enduring power of heartfelt friendship.', 'Animation, Adventure, Family', 100, 'PG', 'Andrew Stanton', 'Tom Hanks, Tim Allen, Joan Cusack', 'toystory5.jpg', 'hero_toystory5.jpg', 'now_showing', '2026-08-10'),
(8, 'Coyote vs. Acme', 'Wile E. Coyote takes the Acme Corporation to court after their defective products repeatedly fail to catch the Road Runner. A determined down-on-his-luck human lawyer takes on the corporate giant in a riotous legal showdown.', 'Comedy, Animation, Family', 98, 'PG', 'Dave Green', 'Will Forte, John Cena, Lana Condor', 'coyote.jpg', 'hero_coyote.jpg', 'now_showing', '2026-08-25'),
(9, 'Practical Magic 2', 'Decades after breaking the family curse, the Owens sisters reunite with a new generation of witches when a mysterious ancestral artifact resurfaces, unleashing a whimsical yet potent ancient magic.', 'Fantasy, Comedy, Romance', 115, 'PG13', 'Akiva Goldsman', 'Sandra Bullock, Nicole Kidman, Evan Rachel Wood', 'practical_magic.jpg', 'hero_magic.jpg', 'coming_soon', '2026-09-11'),
(10, 'Resident Evil: Biohazard Redux', 'A covert special operations unit is dispatched to a quarantined subterranean biotech facility after communication goes dark, uncovering terrifying bio-organic weapons and an impending global outbreak.', 'Action, Horror, Sci-Fi', 110, 'M18', 'Johannes Roberts', 'Kaya Scodelario, Robbie Amell, Hannah John-Kamen', 'resident_evil.jpg', 'hero_resident.jpg', 'coming_soon', '2026-09-18');

-- Screenings (Dynamic schedule for current dates)
INSERT INTO `screenings` (`screening_id`, `movie_id`, `hall_id`, `screening_date`, `screening_time`) VALUES
-- Spider-Man: Brand New Day (Hall A & B)
(1, 1, 1, CURDATE(), '14:00:00'),
(2, 1, 1, CURDATE(), '17:15:00'),
(3, 1, 1, CURDATE(), '20:30:00'),
(4, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00'),
(5, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:30:00'),
(6, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '20:45:00'),
(7, 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:30:00'),
(8, 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '17:45:00'),
(9, 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '21:00:00'),
(10, 1, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '19:30:00'),

-- The Odyssey (Hall B)
(11, 2, 2, CURDATE(), '13:30:00'),
(12, 2, 2, CURDATE(), '17:00:00'),
(13, 2, 2, CURDATE(), '20:30:00'),
(14, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00'),
(15, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:45:00'),
(16, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '21:15:00'),
(17, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '15:00:00'),
(18, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '19:00:00'),

-- Insidious: Out of the Further (Hall C)
(19, 3, 3, CURDATE(), '16:00:00'),
(20, 3, 3, CURDATE(), '19:00:00'),
(21, 3, 3, CURDATE(), '21:45:00'),
(22, 3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '18:30:00'),
(23, 3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '21:30:00'),
(24, 3, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '21:30:00'),

-- The End of Oak Street (Hall C)
(25, 4, 3, CURDATE(), '13:15:00'),
(26, 4, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '13:30:00'),
(27, 4, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '16:00:00'),
(28, 4, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00'),
(29, 4, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:45:00'),

-- Minions & Monsters (Hall A)
(30, 5, 1, CURDATE(), '11:30:00'),
(31, 5, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:30:00'),
(32, 5, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00'),

-- The Dog Stars (Hall B)
(33, 6, 2, CURDATE(), '10:30:00'),
(34, 6, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00'),
(35, 6, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:30:00'),

-- Toy Story 5 (Hall C)
(36, 7, 3, CURDATE(), '10:45:00'),
(37, 7, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00'),

-- Coyote vs. Acme (Hall A & C)
(38, 8, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:30:00'),
(39, 8, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:15:00');

-- Pre-seed some booked seats for realistic availability demo (Screening #3: Spider-Man today 20:30 Hall A)
INSERT INTO `bookings` (`booking_id`, `booking_reference`, `user_id`, `screening_id`, `total_price`, `status`, `payment_status`, `booking_date`) VALUES
(1, 'SVC-2026-1001', 3, 3, 29.00, 'confirmed', 'success', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'SVC-2026-1002', 3, 13, 33.00, 'confirmed', 'success', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `booked_seats` (`booked_seat_id`, `booking_id`, `seat_label`, `seat_type`, `price`) VALUES
(1, 1, 'D5', 'premium', 14.50),
(2, 1, 'D6', 'premium', 14.50),
(3, 2, 'E5', 'premium', 16.50),
(4, 2, 'E6', 'premium', 16.50);

-- Pre-seed Wishlist Items for User #2 (Johnathan Tan) to showcase the multi-booking preference feature immediately
INSERT INTO `booking_wishlist` (`wishlist_id`, `user_id`, `screening_id`, `selected_seats`, `preference_rank`, `estimated_total`) VALUES
(1, 2, 5, 'D3,D4', 1, 29.00), -- Spider-Man tomorrow 17:30
(2, 2, 16, 'E5,E6', 2, 33.00), -- The Odyssey tomorrow 21:15
(3, 2, 23, 'C4,C5', 3, 21.00); -- Insidious tomorrow 21:30

-- Seed verified feedback
INSERT INTO `feedback` (`feedback_id`, `user_id`, `movie_id`, `rating`, `review_text`, `created_at`) VALUES
(1, 2, 1, 5, 'Spectacular visual effects and a deeply emotional story arc for Peter Parker. The Dolby Atmos audio in Hall A was breathtaking!', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 3, 1, 5, 'Easily the best superhero film of the year. Tom Holland delivers his finest performance yet. Highly recommend the premium recliner seats!', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 2, 2, 5, 'Christopher Nolan does it again. The cinematic scale of The Odyssey is mind-bending and demands the largest screen possible.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 3, 3, 4, 'Genuine chills and suspense throughout. Sound design was terrifying in the best way possible.', DATE_SUB(NOW(), INTERVAL 12 HOUR));
