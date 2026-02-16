-- phpMyAdmin SQL Dump
-- version 5.2.1
-- Safe anonymized demo database
-- Database: `farm_fresh_db`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table `cart_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product_unique` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'paid',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` enum('fruit','vegetable','herb') NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `unit_type` enum('kg','g') NOT NULL,
  `image_path` varchar(255) DEFAULT 'placeholders/default.png',
  `is_available` tinyint(1) DEFAULT 1,
  `stock_quantity` int(11) DEFAULT 100,
  `available_margao` tinyint(1) DEFAULT 1,
  `available_panjim` tinyint(1) DEFAULT 1,
  `available_vasco` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample product data
INSERT INTO `products`
(`id`,`name`,`category`,`price_per_unit`,`unit_type`,`image_path`,`is_available`,`stock_quantity`,`available_margao`,`available_panjim`,`available_vasco`)
VALUES
(1,'Apples','fruit',150.00,'kg','placeholders/apple.png',1,100,1,1,1),
(2,'Broccoli','vegetable',90.00,'kg','placeholders/broccoli.png',1,95,1,1,1),
(3,'Mint Leaves','herb',10.00,'g','placeholders/mint.png',1,100,1,1,1),
(4,'Tomatoes','vegetable',60.00,'kg','placeholders/tomato.png',1,90,1,1,1),
(5,'Pears','fruit',100.00,'kg','uploads/demo_pear.png',1,100,1,0,0),
(6,'Spinach','vegetable',100.00,'kg','placeholders/vegetable.png',1,100,1,0,1);

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `user_type` enum('customer','admin') DEFAULT 'customer',
  `location` varchar(255) DEFAULT NULL,
  `cookie_token` varchar(255) DEFAULT NULL,
  `admin_code` varchar(10) DEFAULT NULL,
  `auth_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample anonymized users
INSERT INTO `users`
(`id`,`username`,`password`,`email`,`user_type`,`location`)
VALUES
(1,'admin_user','$2y$10$A9s8df7asf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf','admin@demo.com','admin',NULL),
(2,'user_one','$2y$10$B8sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7','user1@demo.com','customer','panjim'),
(3,'user_two','$2y$10$C7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf','user2@demo.com','customer','margao'),
(4,'admin_two','$2y$10$D6sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf','admin2@demo.com','admin','vasco'),
(5,'user_three','$2y$10$E5sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf7sdf','user3@demo.com','customer','vasco');

-- --------------------------------------------------------
-- Foreign Key Constraints
-- --------------------------------------------------------

ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_user_fk`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_product_fk`
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_fk`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_product_fk`
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

COMMIT;
