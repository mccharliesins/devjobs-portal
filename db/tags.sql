-- tags table
CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(30) NOT NULL UNIQUE,
    `slug` VARCHAR(30) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- insert default tags
INSERT INTO `tags` (`name`, `slug`) VALUES
('JavaScript', 'javascript'),
('PHP', 'php'),
('Python', 'python'),
('Java', 'java'),
('C#', 'c-sharp'),
('Ruby', 'ruby'),
('Swift', 'swift'),
('Kotlin', 'kotlin'),
('Go', 'go'),
('React', 'react'),
('Angular', 'angular'),
('Vue.js', 'vue-js'),
('Node.js', 'node-js'),
('Laravel', 'laravel'),
('Django', 'django'),
('Spring', 'spring'),
('ASP.NET', 'asp-net'),
('SQL', 'sql'),
('NoSQL', 'nosql'),
('MongoDB', 'mongodb'),
('MySQL', 'mysql'),
('PostgreSQL', 'postgresql'),
('AWS', 'aws'),
('Azure', 'azure'),
('Google Cloud', 'google-cloud'),
('Docker', 'docker'),
('Kubernetes', 'kubernetes'),
('Git', 'git'),
('CI/CD', 'ci-cd'),
('APIs', 'apis'),
('Microservices', 'microservices'),
('REST', 'rest'),
('GraphQL', 'graphql'),
('Testing', 'testing'),
('Agile', 'agile'),
('Scrum', 'scrum'),
('Remote', 'remote'),
('Hybrid', 'hybrid'),
('Blockchain', 'blockchain'),
('AI', 'ai'),
('Machine Learning', 'machine-learning');

-- job_tags junction table
CREATE TABLE IF NOT EXISTS `job_tags` (
    `job_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`job_id`, `tag_id`),
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
); 