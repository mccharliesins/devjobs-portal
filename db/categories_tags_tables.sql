-- SQL file to create categories and tags tables with sample data

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create tags table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create job_categories junction table
CREATE TABLE IF NOT EXISTS job_categories (
    job_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (job_id, category_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Create job_tags junction table
CREATE TABLE IF NOT EXISTS job_tags (
    job_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (job_id, tag_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Web Development', 'Positions related to frontend, backend, or full-stack web development'),
('Mobile Development', 'Jobs focused on iOS, Android, or cross-platform mobile app development'),
('Data Science', 'Positions involving data analysis, machine learning, and AI'),
('DevOps', 'Jobs focused on CI/CD, infrastructure management, and deployment'),
('UI/UX Design', 'Positions related to user interface and user experience design'),
('QA & Testing', 'Quality assurance and software testing roles'),
('Cybersecurity', 'Positions focused on application and network security'),
('Game Development', 'Jobs related to game design, development, and programming'),
('Product Management', 'Roles involving product planning, roadmaps, and development'),
('Project Management', 'Positions focused on managing software development projects');

-- Insert sample tags
INSERT INTO tags (name) VALUES
('JavaScript'),
('Python'),
('Java'),
('PHP'),
('C#'),
('Ruby'),
('Swift'),
('Kotlin'),
('React'),
('Angular'),
('Vue.js'),
('Node.js'),
('Django'),
('Laravel'),
('Spring Boot'),
('ASP.NET'),
('Flutter'),
('React Native'),
('TensorFlow'),
('PyTorch'),
('Docker'),
('Kubernetes'),
('AWS'),
('Azure'),
('GCP'),
('Git'),
('CI/CD'),
('Agile'),
('Scrum'),
('SQL'),
('NoSQL'),
('MongoDB'),
('PostgreSQL'),
('MySQL'),
('Redis'),
('GraphQL'),
('REST API'),
('Microservices'),
('Serverless'),
('Machine Learning'); 