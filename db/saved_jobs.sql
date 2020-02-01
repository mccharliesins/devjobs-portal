CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(11) NOT NULL,
  `job_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `user_job` (`user_id`, `job_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 