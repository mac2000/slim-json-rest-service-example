DROP DATABASE IF EXISTS slim;
CREATE DATABASE slim CHARACTER SET utf8 COLLATE utf8_general_ci;
USE slim;
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    age INT(3) NOT NULL DEFAULT 0
);

INSERT INTO users VALUES(NULL, 'alex', 'alex@example.com', 26);
INSERT INTO users VALUES(NULL, 'maria', 'maria@example.com', 23);
