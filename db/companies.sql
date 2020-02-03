-- create companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    user_id INT,
    logo VARCHAR(255),
    website VARCHAR(255),
    description TEXT,
    industry VARCHAR(100),
    founded_year INT,
    company_size VARCHAR(50),
    headquarters VARCHAR(100),
    social_linkedin VARCHAR(255),
    social_twitter VARCHAR(255),
    social_facebook VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- add company_id to jobs table if not exists
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS company_id INT;
ALTER TABLE jobs ADD CONSTRAINT IF NOT EXISTS fk_company_job FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- index for better performance
CREATE INDEX IF NOT EXISTS idx_company_name ON companies(name);
CREATE INDEX IF NOT EXISTS idx_job_company ON jobs(company_id); 