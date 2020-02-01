-- Add role field to users table
ALTER TABLE `users` ADD COLUMN `role` ENUM('user', 'recruiter', 'admin') NOT NULL DEFAULT 'user' AFTER `password`;

-- Add recruiter_id field to jobs table
ALTER TABLE `jobs` ADD COLUMN `recruiter_id` INT(11) DEFAULT NULL AFTER `requirements`;
ALTER TABLE `jobs` ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`;
ALTER TABLE `jobs` ADD CONSTRAINT `fk_jobs_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL; 