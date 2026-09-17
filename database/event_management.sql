-- =========================================================
-- EventHub - College Event Management Portal
-- Database: event_management
-- =========================================================

DROP DATABASE IF EXISTS event_management;

CREATE DATABASE event_management
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE event_management;


-- =========================================================
-- USERS
-- =========================================================

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    role ENUM(
        'student',
        'organizer',
        'admin'
    ) NOT NULL DEFAULT 'student',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================================
-- CATEGORIES
-- =========================================================

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- =========================================================
-- EVENTS
-- =========================================================

CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(150) NOT NULL,
    description TEXT,
    poster VARCHAR(255) DEFAULT NULL,

    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    venue VARCHAR(200) NOT NULL,

    capacity INT NOT NULL,

    registration_fee DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    event_type ENUM(
        'online',
        'offline',
        'hybrid'
    ) NOT NULL DEFAULT 'offline',

    status ENUM(
        'pending',
        'approved',
        'rejected',
        'completed'
    ) NOT NULL DEFAULT 'pending',

    organizer_id INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_event_capacity
        CHECK (capacity > 0),

    CONSTRAINT chk_registration_fee
        CHECK (registration_fee >= 0),

    CONSTRAINT fk_event_organizer
        FOREIGN KEY (organizer_id)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB;


-- =========================================================
-- EVENT CATEGORIES
-- =========================================================

CREATE TABLE event_categories (
    event_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (event_id, category_id),

    CONSTRAINT fk_ec_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ec_category
        FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- =========================================================
-- REGISTRATIONS
-- =========================================================

CREATE TABLE registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,
    event_id INT NOT NULL,

    status ENUM(
        'registered',
        'cancelled'
    ) NOT NULL DEFAULT 'registered',

    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_registration_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_registration_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE,

    CONSTRAINT uq_user_event
        UNIQUE (user_id, event_id)

) ENGINE=InnoDB;


-- =========================================================
-- ATTENDANCE
-- =========================================================

CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,

    registration_id INT NOT NULL UNIQUE,

    attended BOOLEAN NOT NULL DEFAULT FALSE,

    marked_at TIMESTAMP NULL,

    CONSTRAINT fk_attendance_registration
        FOREIGN KEY (registration_id)
        REFERENCES registrations(registration_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- =========================================================
-- PRIZES
-- =========================================================

CREATE TABLE prizes (
    prize_id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    position INT NOT NULL,

    amount DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    description VARCHAR(255),

    CONSTRAINT fk_prize_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE,

    CONSTRAINT chk_prize_position
        CHECK (position > 0),

    CONSTRAINT chk_prize_amount
        CHECK (amount >= 0),

    CONSTRAINT uq_event_position
        UNIQUE (event_id, position)

) ENGINE=InnoDB;


-- =========================================================
-- WINNERS
-- =========================================================

CREATE TABLE winners (
    winner_id INT AUTO_INCREMENT PRIMARY KEY,

    prize_id INT NOT NULL,

    registration_id INT NOT NULL UNIQUE,

    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_winner_prize
        FOREIGN KEY (prize_id)
        REFERENCES prizes(prize_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_winner_registration
        FOREIGN KEY (registration_id)
        REFERENCES registrations(registration_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- =========================================================
-- FEEDBACK
-- =========================================================

CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,
    event_id INT NOT NULL,

    rating INT NOT NULL,

    comments TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_feedback_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE,

    CONSTRAINT chk_feedback_rating
        CHECK (rating BETWEEN 1 AND 5),

    CONSTRAINT uq_feedback
        UNIQUE (user_id, event_id)

) ENGINE=InnoDB;


-- =========================================================
-- INDEXES
-- =========================================================

CREATE INDEX idx_events_date
ON events(event_date);

CREATE INDEX idx_events_status
ON events(status);

CREATE INDEX idx_events_organizer
ON events(organizer_id);

CREATE INDEX idx_registration_event
ON registrations(event_id);

CREATE INDEX idx_registration_user
ON registrations(user_id);

CREATE INDEX idx_feedback_event
ON feedback(event_id);


-- =========================================================
-- DEFAULT CATEGORIES
-- =========================================================

INSERT INTO categories (name)
VALUES
    ('Technology'),
    ('Sports'),
    ('Cultural'),
    ('Workshop'),
    ('Seminar'),
    ('Competition'),
    ('Music'),
    ('Arts'),
    ('Other');


-- =========================================================
-- DEFAULT ADMIN USER
-- Password: admin123 (hash generated by PHP password_hash)
-- =========================================================

INSERT INTO users (name, email, password, role)
VALUES (
    'Administrator',
    'admin@eventhub.com',
    '$2y$10$ZrqmH7/m6VnuqIHOzlJ/Q.9Q/pzVeEhdLzLsG4s6.WedCLdE2ctQW',
    'admin'
);


-- =========================================================
-- DATABASE COMPLETE
-- =========================================================