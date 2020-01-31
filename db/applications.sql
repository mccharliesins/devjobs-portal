CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `cover_letter` TEXT NOT NULL,
  `resume` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 