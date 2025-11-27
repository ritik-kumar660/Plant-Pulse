-- Create pest_identifications table
CREATE TABLE IF NOT EXISTS pest_identifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    pest_name VARCHAR(100),
    confidence DECIMAL(4,3),
    description TEXT,
    treatments TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 