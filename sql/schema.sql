CREATE DATABASE IF NOT EXISTS weather_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE weather_app;

CREATE TABLE IF NOT EXISTS weather_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(100) NOT NULL UNIQUE,
    weather_data TEXT NOT NULL,
    last_updated DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE USER IF NOT EXISTS 'weather_user'@'localhost' IDENTIFIED BY 'weather_pass_2026';
GRANT ALL PRIVILEGES ON weather_app.* TO 'weather_user'@'localhost';
FLUSH PRIVILEGES;