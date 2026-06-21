-- 1. DROP EXISTING CONFLICTING TABLES IF ANY REMAIN
DROP TABLE IF EXISTS child;
DROP TABLE IF EXISTS sponsors;
DROP TABLE IF EXISTS users;


-- 2. Create the users table INCLUDING the email field your script expects
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE, -- Added to support your new script
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Coordinator', 'Sponsor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Recreate the child table with a proper cascading deletion profile
CREATE TABLE child (
    internal_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id VARCHAR(15) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    age INT NOT NULL,
    mother_first_name VARCHAR(50) NOT NULL,
    mother_last_name VARCHAR(50) NOT NULL,
    mother_dob DATE NOT NULL,
    mother_age INT NOT NULL,
    mother_occupation VARCHAR(100) NOT NULL,
    father_first_name VARCHAR(50) NOT NULL,
    father_last_name VARCHAR(50) NOT NULL,
    father_dob DATE NOT NULL,
    father_age INT NOT NULL,
    residence_country VARCHAR(100) DEFAULT 'Sri Lanka' NOT NULL,
    religion VARCHAR(50) NOT NULL,
    nationality VARCHAR(50) DEFAULT 'Sri Lankan' NOT NULL,
    language VARCHAR(50) NOT NULL,
    education_level VARCHAR(50) NOT NULL,
    health_status VARCHAR(100) NOT NULL,
    registered_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- ON DELETE CASCADE allows truncating/deleting users seamlessly in dev mode
    CONSTRAINT child_ibfk_1 FOREIGN KEY (registered_by_user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- 3. CREATE SPONSORS TABLE (Required for register_sponsor.php)

CREATE TABLE sponsors (
    internal_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id VARCHAR(15) NULL, -- Will be populated instantly by the trigger below
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    residence_country VARCHAR(100) NOT NULL,
    language VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    age INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active' NOT NULL, -- Added Status Field
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create a Trigger to auto-generate the 'S000000001' string format automatically
DELIMITER $$

CREATE TRIGGER auto_generate_sponsor_id
BEFORE INSERT ON sponsors
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    
    -- Find out what the next auto_increment ID value is going to be
    SELECT AUTO_INCREMENT INTO next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsors';
    
    -- Format it into the string pattern dynamically
    SET NEW.id = CONCAT('S', LPAD(next_id, 9, '0'));
END$$

DELIMITER ;



-- 3. child_sponsor_matches TABLE (Required for match_sponsor.php)

CREATE TABLE IF NOT EXISTS child_sponsor_matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(15) NOT NULL,
    sponsor_user_id INT UNSIGNED NOT NULL,
    match_status ENUM('Active', 'Terminated', 'Pending') DEFAULT 'Active' NOT NULL,
    assigned_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_child_sponsor (child_id, sponsor_user_id),
    CONSTRAINT fk_match_child FOREIGN KEY (child_id) REFERENCES child(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_match_sponsor FOREIGN KEY (sponsor_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_match_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- 1. Remove the old broken Foreign Key constraint
ALTER TABLE child_sponsor_matches 
DROP FOREIGN KEY fk_match_sponsor;

-- 2. Ensure your sponsor_user_id column is capable of holding the 'S000000001' VARCHAR string 
-- (If it was previously an INT, we change it to VARCHAR(15) to match sponsors.id)
ALTER TABLE child_sponsor_matches 
MODIFY sponsor_user_id VARCHAR(15) NOT NULL;

-- 3. Add the updated Foreign Key pointing to your new sponsors table
ALTER TABLE child_sponsor_matches 
ADD CONSTRAINT fk_match_sponsor 
FOREIGN KEY (sponsor_user_id) REFERENCES sponsors (id) 
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE sponsors ADD COLUMN user_id INT(11) NULL AFTER id;


CREATE TABLE IF NOT EXISTS letters (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(50) NOT NULL,
    sponsor_id VARCHAR(50) NOT NULL,
    sender_role ENUM('Sponsor', 'Child', 'Coordinator', 'Admin') NOT NULL,
    letter_title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE users 
ADD COLUMN password_changed TINYINT(1) DEFAULT 0 AFTER role;

ALTER TABLE letters CHANGE COLUMN letter_title letter_content TEXT NOT NULL;


ALTER TABLE child_sponsor_matches CHANGE COLUMN sponsor_user_id sponsor_id INT NOT NULL;

ALTER TABLE `users` ADD COLUMN `user_type_id` VARCHAR(50) NULL DEFAULT NULL AFTER `role`;


ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('Admin', 'Coordinator', 'Sponsor', 'Child') NOT NULL;