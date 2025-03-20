-- categories table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- insert default categories
INSERT INTO `categories` (`name`, `slug`, `description`) VALUES
('Web Development', 'web-development', 'Jobs related to web development, including frontend, backend, and full-stack positions'),
('Mobile Development', 'mobile-development', 'Jobs related to mobile app development for iOS, Android, and cross-platform'),
('Data Science', 'data-science', 'Jobs in data science, machine learning, and artificial intelligence'),
('DevOps', 'devops', 'Jobs in DevOps, site reliability, and infrastructure management'),
('Quality Assurance', 'quality-assurance', 'Jobs in software testing, quality assurance, and test automation'),
('UI/UX Design', 'ui-ux-design', 'Jobs in user interface and user experience design'),
('Project Management', 'project-management', 'Jobs in project management, scrum master, and product owner roles'),
('Cybersecurity', 'cybersecurity', 'Jobs in cybersecurity, penetration testing, and security engineering'),
('Blockchain', 'blockchain', 'Jobs in blockchain development and cryptocurrency'),
('Cloud Computing', 'cloud-computing', 'Jobs related to cloud platforms like AWS, Azure, and Google Cloud'),
('Game Development', 'game-development', 'Jobs in game design and development'),
('Embedded Systems', 'embedded-systems', 'Jobs in embedded systems and IoT development');

-- job_categories junction table
CREATE TABLE IF NOT EXISTS `job_categories` (
    `job_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`job_id`, `category_id`),
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
); 