-- AgriRent Database Backup
-- Generated on: 2025-11-03 10:13:57

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `complaint_type` char(1) NOT NULL,
  `id` int(11) NOT NULL,
  `description` text NOT NULL,
  `status` char(1) DEFAULT 'O',
  `complaint_against` int(11) NOT NULL,
  `reply` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`complaint_id`),
  KEY `user_id` (`user_id`),
  KEY `complaint_against` (`complaint_against`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `complaints` VALUES('2','3','P','7','Not Give Fresh Toamto\n\n--- ADMIN RESPONSE (2025-11-02 18:07:03) ---\nNext time not issue','R','0',NULL,'2025-11-02 22:31:36','2025-11-02 22:37:03');

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `Equipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `Owner_id` int(11) NOT NULL,
  `Subcategories_id` int(11) NOT NULL,
  `Title` varchar(50) NOT NULL,
  `Brand` varchar(50) NOT NULL,
  `Model` varchar(50) NOT NULL,
  `Year` int(11) DEFAULT NULL,
  `Description` text NOT NULL,
  `Hourly_rate` decimal(10,2) DEFAULT NULL,
  `Daily_rate` decimal(10,2) DEFAULT NULL,
  `listed_date` datetime DEFAULT current_timestamp(),
  `Approval_status` char(3) DEFAULT 'PEN',
  PRIMARY KEY (`Equipment_id`),
  KEY `Owner_id` (`Owner_id`),
  KEY `Subcategories_id` (`Subcategories_id`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`Owner_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`Subcategories_id`) REFERENCES `equipment_subcategories` (`Subcategory_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment` VALUES('6','3','1','John Deer','John Deer','5054D','2020','Heavy Tractor','700.00','8400.00','2025-10-06 11:42:52','CON');
INSERT INTO `equipment` VALUES('7','3','1','John Deer','John Deer','5054D','2020','Heavy Tractor','700.00','8400.00','2025-10-06 11:57:48','PEN');

DROP TABLE IF EXISTS `equipment_bookings`;
CREATE TABLE `equipment_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `Hours` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` char(3) DEFAULT 'PEN',
  `time_slot` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `equipment_bookings_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`Equipment_id`),
  CONSTRAINT `equipment_bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment_bookings` VALUES('69','6','9','2025-10-19','2025-10-19','12','8400.00','PEN','8AM - 8PM');
INSERT INTO `equipment_bookings` VALUES('70','6','7','2025-10-19','2025-10-19','12','8400.00','COM','8AM - 8PM');
INSERT INTO `equipment_bookings` VALUES('71','6','7','2025-11-02','2025-11-02','12','8400.00','PEN','8AM - 8PM');
INSERT INTO `equipment_bookings` VALUES('72','6','9','2025-11-02','2025-11-02','12','8400.00','CON','8AM - 8PM');

DROP TABLE IF EXISTS `equipment_categories`;
CREATE TABLE `equipment_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment_categories` VALUES('1','Tractor','');
INSERT INTO `equipment_categories` VALUES('5','Harvester','');

DROP TABLE IF EXISTS `equipment_subcategories`;
CREATE TABLE `equipment_subcategories` (
  `Subcategory_id` int(11) NOT NULL AUTO_INCREMENT,
  `Category_id` int(11) NOT NULL,
  `Subcategory_name` varchar(70) NOT NULL,
  `Description` text DEFAULT NULL,
  PRIMARY KEY (`Subcategory_id`),
  KEY `Category_id` (`Category_id`),
  CONSTRAINT `equipment_subcategories_ibfk_1` FOREIGN KEY (`Category_id`) REFERENCES `equipment_categories` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment_subcategories` VALUES('1','1','Mini Tractors','');
INSERT INTO `equipment_subcategories` VALUES('3','5','all-crop harvester','');

DROP TABLE IF EXISTS `images`;
CREATE TABLE `images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `image_type` char(1) NOT NULL,
  `ID` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `idx_image_type_id` (`image_type`,`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `images` VALUES('10','E','6','uploads/equipment_images/equip_68e35de4bf983_1759731172.jpg','2025-10-06 11:42:52');
INSERT INTO `images` VALUES('11','E','7','uploads/equipment_images/equip_68e361643b5e7_1759732068.jpg','2025-10-06 11:57:48');
INSERT INTO `images` VALUES('12','P','7','uploads/products/product_3_1759734102.jpg','2025-10-06 12:31:42');
INSERT INTO `images` VALUES('13','P','8','uploads/products/product_3_1759734720.jpg','2025-10-06 12:42:00');
INSERT INTO `images` VALUES('14','P','9','uploads/products/product_3_1759737538.jpg','2025-10-06 13:28:58');
INSERT INTO `images` VALUES('16','P','6','uploads/product_images/prod_69063b1747f89_1762016023.jpg','2025-11-01 22:06:15');
INSERT INTO `images` VALUES('18','P','11','uploads/products/product_7_1762020256.jpg','2025-11-01 23:34:16');

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `Content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `messages` VALUES('1','3','6','hello','0','2025-08-30 10:46:16');
INSERT INTO `messages` VALUES('2','8','3','hii','1','2025-09-01 15:32:37');
INSERT INTO `messages` VALUES('3','3','8','hii','1','2025-09-01 15:33:29');
INSERT INTO `messages` VALUES('4','3','8','ayush','1','2025-09-01 15:33:35');
INSERT INTO `messages` VALUES('5','8','3','hello','1','2025-09-01 15:34:43');
INSERT INTO `messages` VALUES('6','3','8','equipment owner','1','2025-09-02 10:26:11');
INSERT INTO `messages` VALUES('7','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 18:48:04');
INSERT INTO `messages` VALUES('8','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 18:50:30');
INSERT INTO `messages` VALUES('9','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:02:14');
INSERT INTO `messages` VALUES('10','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:02:17');
INSERT INTO `messages` VALUES('11','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:03:11');
INSERT INTO `messages` VALUES('12','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:03:44');
INSERT INTO `messages` VALUES('13','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:03:48');
INSERT INTO `messages` VALUES('14','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:04:31');
INSERT INTO `messages` VALUES('15','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:04:37');
INSERT INTO `messages` VALUES('16','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:07:00');
INSERT INTO `messages` VALUES('17','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:07:10');
INSERT INTO `messages` VALUES('18','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:08:20');
INSERT INTO `messages` VALUES('19','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:10:18');
INSERT INTO `messages` VALUES('20','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:10:21');
INSERT INTO `messages` VALUES('21','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:11:04');
INSERT INTO `messages` VALUES('22','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:14:19');
INSERT INTO `messages` VALUES('23','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:14:51');
INSERT INTO `messages` VALUES('24','8','3','New booking request received for your equipment: John Deere','0','2025-09-04 19:14:52');
INSERT INTO `messages` VALUES('25','8','3','New booking request received for your equipment: John Deere from Sep 6, 2025 to Sep 6, 2025','0','2025-09-05 15:27:36');
INSERT INTO `messages` VALUES('26','8','3','New booking request received for your equipment: John Deere from Sep 6, 2025 to Sep 6, 2025','0','2025-09-05 15:44:39');
INSERT INTO `messages` VALUES('27','8','3','New booking request received for your equipment: John Deere from Sep 6, 2025 to Sep 6, 2025','0','2025-09-05 15:46:46');
INSERT INTO `messages` VALUES('28','8','3','New booking request received for your equipment: John Deere from Sep 6, 2025 to Sep 6, 2025','0','2025-09-05 15:54:07');
INSERT INTO `messages` VALUES('29','7','3','New booking request received for your equipment: John Deere from Sep 6, 2025 to Sep 6, 2025','0','2025-09-05 17:53:10');
INSERT INTO `messages` VALUES('30','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025','0','2025-09-05 17:58:10');
INSERT INTO `messages` VALUES('31','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025','0','2025-09-05 17:59:10');
INSERT INTO `messages` VALUES('32','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025','0','2025-09-05 18:00:12');
INSERT INTO `messages` VALUES('33','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025 (08:00-11:00)','0','2025-09-05 18:24:21');
INSERT INTO `messages` VALUES('34','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025 (08:10-10:10)','0','2025-09-05 18:25:58');
INSERT INTO `messages` VALUES('35','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025 (11:00-13:00)','0','2025-09-05 18:29:56');
INSERT INTO `messages` VALUES('36','7','3','New booking request received for your equipment: John Deere from Sep 8, 2025 to Sep 8, 2025','0','2025-09-05 18:32:29');
INSERT INTO `messages` VALUES('37','7','3','New booking request received for your equipment: John Deere from Sep 7, 2025 to Sep 7, 2025 (11:01-13:01)','0','2025-09-05 18:37:26');
INSERT INTO `messages` VALUES('38','8','3','New booking request received for your equipment: John Deere from Sep 9, 2025 to Sep 9, 2025 (01:39-18:39)','0','2025-09-08 13:39:44');
INSERT INTO `messages` VALUES('39','8','3','New booking request received for your equipment: John Deere from Sep 8, 2025 to Sep 8, 2025 (13:59-15:59)','0','2025-09-08 13:59:54');
INSERT INTO `messages` VALUES('40','8','3','New booking request received for your equipment: John Deere from Sep 8, 2025 to Sep 8, 2025 (14:01-16:01)','0','2025-09-08 14:02:03');
INSERT INTO `messages` VALUES('41','8','3','New booking request received for your equipment: John Deere from Sep 9, 2025 to Sep 9, 2025','0','2025-09-08 15:11:29');
INSERT INTO `messages` VALUES('42','8','3','New booking request received for your equipment: John Deere from Sep 9, 2025 to Sep 9, 2025 (17:12-20:12)','0','2025-09-08 15:12:10');
INSERT INTO `messages` VALUES('43','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (08:00-11:00)','0','2025-09-09 13:05:52');
INSERT INTO `messages` VALUES('44','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (10:07-13:07)','0','2025-09-09 13:08:12');
INSERT INTO `messages` VALUES('45','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (13:16-15:16)','0','2025-09-09 13:16:16');
INSERT INTO `messages` VALUES('46','8','3','New booking request received for your equipment: John Deere from Sep 12, 2025 to Sep 12, 2025','0','2025-09-10 13:36:18');
INSERT INTO `messages` VALUES('47','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025','0','2025-09-10 13:37:50');
INSERT INTO `messages` VALUES('48','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (13:34-16:34)','0','2025-09-10 13:39:15');
INSERT INTO `messages` VALUES('49','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025','0','2025-09-10 13:46:01');
INSERT INTO `messages` VALUES('50','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025','0','2025-09-10 13:47:04');
INSERT INTO `messages` VALUES('51','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (13:48-16:48)','0','2025-09-10 13:48:37');
INSERT INTO `messages` VALUES('52','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (8AM - 8PM)','0','2025-09-10 14:09:21');
INSERT INTO `messages` VALUES('53','8','3','New booking request received for your equipment: John Deere from Sep 10, 2025 to Sep 10, 2025 (09:00-18:10)','0','2025-09-10 14:11:14');
INSERT INTO `messages` VALUES('54','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 10:21:59');
INSERT INTO `messages` VALUES('55','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 11:19:54');
INSERT INTO `messages` VALUES('56','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 11:21:52');
INSERT INTO `messages` VALUES('57','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 12:01:24');
INSERT INTO `messages` VALUES('58','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 12:04:15');
INSERT INTO `messages` VALUES('59','8','3','New booking request received for your equipment: John Deer from Oct 6, 2025 to Oct 6, 2025 (8AM - 8PM)','0','2025-10-06 12:17:33');
INSERT INTO `messages` VALUES('60','8','3','New product order received for Tomato - Order #3','0','2025-10-06 14:54:19');
INSERT INTO `messages` VALUES('61','8','3','New product order received for Tomato - Order #4','0','2025-10-08 13:16:59');
INSERT INTO `messages` VALUES('62','8','3','New product order received for Tomato - Order #5','0','2025-10-08 13:18:31');
INSERT INTO `messages` VALUES('63','8','3','New booking request received for your equipment: John Deer from Oct 8, 2025 to Oct 8, 2025 (8AM - 8PM)','0','2025-10-08 13:21:30');
INSERT INTO `messages` VALUES('64','9','3','New booking request received for your equipment: John Deer from Oct 10, 2025 to Oct 10, 2025 (8AM - 8PM)','0','2025-10-10 14:23:55');
INSERT INTO `messages` VALUES('65','9','3','New booking request received for your equipment: John Deer from Oct 10, 2025 to Oct 10, 2025 (8AM - 8PM)','0','2025-10-10 14:26:19');
INSERT INTO `messages` VALUES('66','3','7','New product order received for Apple - Order #6','0','2025-10-10 15:06:27');
INSERT INTO `messages` VALUES('67','3','7','New product order received for Apple - Order #7','0','2025-10-10 15:17:08');
INSERT INTO `messages` VALUES('68','3','7','New product order received for Apple - Order #8','0','2025-10-10 15:17:56');
INSERT INTO `messages` VALUES('69','9','3','New booking request received for your equipment: John Deer from Oct 19, 2025 to Oct 19, 2025 (8AM - 8PM)','0','2025-10-19 22:11:53');
INSERT INTO `messages` VALUES('70','7','3','New booking request received for your equipment: John Deer from Oct 19, 2025 to Oct 19, 2025 (8AM - 8PM)','0','2025-10-19 22:19:31');
INSERT INTO `messages` VALUES('71','7','3','New product order received for Tomato - Order #9','0','2025-11-02 12:47:12');
INSERT INTO `messages` VALUES('72','7','3','New product order received for Tomato - Order #10','0','2025-11-02 13:08:50');
INSERT INTO `messages` VALUES('73','7','3','New product order received for Tomato - Order #11','0','2025-11-02 13:11:17');
INSERT INTO `messages` VALUES('74','7','3','New product order received for Tomato - Order #12','0','2025-11-02 13:13:17');
INSERT INTO `messages` VALUES('75','3','7','New product order received for German Butterball Potatoes - Order #13','0','2025-11-02 13:21:43');
INSERT INTO `messages` VALUES('76','7','3','New booking request received for your equipment: John Deer from Nov 2, 2025 to Nov 2, 2025 (8AM - 8PM)','0','2025-11-02 15:44:20');
INSERT INTO `messages` VALUES('77','9','3','New booking request received for your equipment: John Deer from Nov 2, 2025 to Nov 2, 2025 (8AM - 8PM)','0','2025-11-02 15:47:24');
INSERT INTO `messages` VALUES('78','3','7','New product order received for German Butterball Potatoes - Order #14','0','2025-11-03 08:36:13');
INSERT INTO `messages` VALUES('79','3','7','New product order received for German Butterball Potatoes - Order #15','0','2025-11-03 08:38:32');
INSERT INTO `messages` VALUES('80','3','7','New product order received for German Butterball Potatoes - Order #16','0','2025-11-03 08:53:26');
INSERT INTO `messages` VALUES('81','3','7','New product order received for German Butterball Potatoes - Order #17','0','2025-11-03 08:54:46');
INSERT INTO `messages` VALUES('82','3','7','New product order received for German Butterball Potatoes - Order #18','0','2025-11-03 09:24:46');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `Payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `Subscription_id` int(11) NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'razorpay',
  `payment_gateway_response` longtext DEFAULT NULL,
  `UPI_transaction_id` varchar(12) DEFAULT NULL,
  `Status` varchar(1) NOT NULL DEFAULT 'P',
  `payment_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Payment_id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  UNIQUE KEY `razorpay_payment_id` (`razorpay_payment_id`),
  UNIQUE KEY `razorpay_order_id` (`razorpay_order_id`),
  KEY `Subscription_id` (`Subscription_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`Subscription_id`) REFERENCES `user_subscriptions` (`subscription_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` VALUES('33','47','199.00','TXN17621553021501',NULL,NULL,NULL,'razorpay',NULL,'pay_RbBdkXOE','S','2025-11-03 13:05:31');

DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `Subcategory_id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Quantity` decimal(10,2) NOT NULL,
  `Unit` char(3) NOT NULL,
  `address_id` int(11) DEFAULT NULL,
  `listed_date` datetime DEFAULT current_timestamp(),
  `Approval_status` char(3) DEFAULT 'PEN',
  PRIMARY KEY (`product_id`),
  KEY `seller_id` (`seller_id`),
  KEY `Subcategory_id` (`Subcategory_id`),
  KEY `fk_product_address` (`address_id`),
  CONSTRAINT `fk_product_address` FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`address_id`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `product_ibfk_2` FOREIGN KEY (`Subcategory_id`) REFERENCES `product_subcategories` (`Subcategory_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product` VALUES('6','7','1','Apple','Juice Apple','100.00','10.00','KG',NULL,'2025-09-26 11:36:30','PEN');
INSERT INTO `product` VALUES('7','3','1','Tomato','asdgs','40.00','50.00','K',NULL,'2025-10-06 12:31:42','CON');
INSERT INTO `product` VALUES('8','3','1','Tomato1','asdgZXZXCZZC','40.00','50.00','K',NULL,'2025-10-06 12:42:00','PEN');
INSERT INTO `product` VALUES('9','3','1','Tomato2','qwertyuiop','10.00','50.00','K','1','2025-10-06 13:28:58','PEN');
INSERT INTO `product` VALUES('11','7','2','German Butterball Potatoes','Ptotato','30.00','20.00','K',NULL,'2025-11-01 23:34:16','CON');

DROP TABLE IF EXISTS `product_bookings`;
CREATE TABLE `product_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_address` text NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `status` enum('PEN','CON','REJ') DEFAULT 'PEN',
  `booking_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`booking_id`),
  KEY `product_id` (`product_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `product_bookings_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `product_bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `Category_id` int(11) NOT NULL AUTO_INCREMENT,
  `Category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`Category_id`),
  UNIQUE KEY `Category_name` (`Category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_categories` VALUES('1','Vegetables','Fresh and naturally grown vegetables that form an essential part of a healthy diet');

DROP TABLE IF EXISTS `product_orders`;
CREATE TABLE `product_orders` (
  `Order_id` int(11) NOT NULL AUTO_INCREMENT,
  `Product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `delivery_address` int(11) NOT NULL,
  `Status` char(3) DEFAULT 'PEN',
  `order_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Order_id`),
  KEY `Product_id` (`Product_id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `delivery_address` (`delivery_address`),
  CONSTRAINT `product_orders_ibfk_1` FOREIGN KEY (`Product_id`) REFERENCES `product` (`product_id`),
  CONSTRAINT `product_orders_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `product_orders_ibfk_3` FOREIGN KEY (`delivery_address`) REFERENCES `user_addresses` (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_orders` VALUES('6','6','3','10.00','1000.00','1','REJ','2025-10-10 15:06:27');
INSERT INTO `product_orders` VALUES('7','6','3','9.00','900.00','1','COM','2025-10-10 15:17:08');
INSERT INTO `product_orders` VALUES('8','6','3','1.00','100.00','1','CON','2025-10-10 15:17:56');
INSERT INTO `product_orders` VALUES('9','7','7','10.00','400.00','4','PEN','2025-11-02 12:47:12');
INSERT INTO `product_orders` VALUES('12','7','7','5.00','200.00','4','COM','2025-11-02 13:13:17');
INSERT INTO `product_orders` VALUES('17','11','3','15.00','450.00','1','CON','2025-11-03 08:54:46');

DROP TABLE IF EXISTS `product_subcategories`;
CREATE TABLE `product_subcategories` (
  `Subcategory_id` int(11) NOT NULL AUTO_INCREMENT,
  `Category_id` int(11) NOT NULL,
  `Subcategory_name` varchar(70) NOT NULL,
  `Description` text DEFAULT NULL,
  PRIMARY KEY (`Subcategory_id`),
  KEY `Category_id` (`Category_id`),
  CONSTRAINT `product_subcategories_ibfk_1` FOREIGN KEY (`Category_id`) REFERENCES `product_categories` (`Category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_subcategories` VALUES('1','1','Tomato','Juicy red tomatoes used in cooking, salads, and sauces. Rich in vitamins and antioxidants, grown locally for freshness.');
INSERT INTO `product_subcategories` VALUES('2','1','Potato','Fresh Potato');

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `Review_id` int(11) NOT NULL AUTO_INCREMENT,
  `Reviewer_id` int(11) NOT NULL,
  `Review_type` char(1) NOT NULL,
  `ID` int(11) NOT NULL,
  `Rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Review_id`),
  KEY `Reviewer_id` (`Reviewer_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`Reviewer_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `reviews` VALUES('7','9','E','6','5',NULL,'2025-10-10 14:34:52');
INSERT INTO `reviews` VALUES('8','9','P','7','4',NULL,'2025-10-10 14:45:52');
INSERT INTO `reviews` VALUES('9','3','P','11','3','nice','2025-11-03 09:22:10');

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `Plan_name` varchar(50) NOT NULL,
  `Plan_type` char(1) NOT NULL CHECK (`Plan_type` in ('M','Y')),
  `user_type` char(1) NOT NULL CHECK (`user_type` in ('F','O')),
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subscription_plans` VALUES('1','Farmer Monthly - Product Listings','M','F','199.00');
INSERT INTO `subscription_plans` VALUES('2','Farmer Yearly - Product Listings','Y','F','1990.00');
INSERT INTO `subscription_plans` VALUES('3','Equipment Owner Monthly - Equipment & Products','M','O','399.00');
INSERT INTO `subscription_plans` VALUES('4','Equipment Owner Yearly - Equipment & Products','Y','O','3990.00');
INSERT INTO `subscription_plans` VALUES('13','Equipment Owner 3 Years - Equipment & Products','Y','O','5000.00');
INSERT INTO `subscription_plans` VALUES('14','Farmer 3 Years - Products','Y','F','4000.00');

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(20) NOT NULL,
  `state` varchar(25) NOT NULL,
  `Pin_code` char(6) NOT NULL,
  PRIMARY KEY (`address_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_addresses` VALUES('1','3','wertyui','Surat','Gujarat','395010');
INSERT INTO `user_addresses` VALUES('2','8','qweweqewqwe','surat','Gujarat','123221');
INSERT INTO `user_addresses` VALUES('3','8','fgfdytyfy','surat','Gujarat','123221');
INSERT INTO `user_addresses` VALUES('4','7','VIP Circle','Surat','Gujarat','395009');

DROP TABLE IF EXISTS `user_subscriptions`;
CREATE TABLE `user_subscriptions` (
  `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `Status` char(1) DEFAULT 'P',
  PRIMARY KEY (`subscription_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_subscriptions` VALUES('32','3','3','2025-10-01','2025-10-31','C');
INSERT INTO `user_subscriptions` VALUES('33','8','1','2025-10-01','2025-10-31','C');
INSERT INTO `user_subscriptions` VALUES('34','7','2',NULL,NULL,'C');
INSERT INTO `user_subscriptions` VALUES('35','7','2',NULL,NULL,'C');
INSERT INTO `user_subscriptions` VALUES('36','7','1','2025-10-03','2025-11-02','C');
INSERT INTO `user_subscriptions` VALUES('37','3','3','2025-10-03','2025-11-02','C');
INSERT INTO `user_subscriptions` VALUES('38','3','3','2025-11-02','2025-11-03','E');
INSERT INTO `user_subscriptions` VALUES('39','9','3','2025-10-06','2025-11-05','A');
INSERT INTO `user_subscriptions` VALUES('40','8','1','2025-10-06','2025-11-05','A');
INSERT INTO `user_subscriptions` VALUES('41','7','1','2025-10-10','2025-11-03','E');
INSERT INTO `user_subscriptions` VALUES('42','11','1','2025-11-03','2025-11-03','E');
INSERT INTO `user_subscriptions` VALUES('43','11','1','2025-11-03','2025-11-03','C');
INSERT INTO `user_subscriptions` VALUES('44','11','1','2025-11-03','2025-11-03','C');
INSERT INTO `user_subscriptions` VALUES('47','11','1','2025-11-03',NULL,'A');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  `Email` varchar(90) NOT NULL,
  `Phone` char(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `User_type` char(1) NOT NULL CHECK (`User_type` in ('O','F','A')),
  `status` char(1) NOT NULL DEFAULT 'A',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `Phone` (`Phone`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES('3','Darshak Vaghamshi','23bmiit172@gmail.com','9016384962','$2y$10$j8AU7dfSqMU4vo/1jM/kMOzTQDnQ5/K/ZaRE/oAeg8k.YcTCGuiF.','O','A');
INSERT INTO `users` VALUES('6','Harmish Kachhadiya','23bmiit172@gmail.com','7777935854','$2y$10$8x5gggttYQPGhg.NITl6de8ZM3AhGzieZ2cu3Q5Ehd71NyVtk5.x.','A','A');
INSERT INTO `users` VALUES('7','Neel Gohil','23bmiit104@gmail.com','8347458209','$2y$10$58FAwXzm9FWXcqxl7s7c0OU6OwTr6gutRDXXAgFjgvOCFV8ZsDKOC','F','A');
INSERT INTO `users` VALUES('8','Ayush Jain','23bmiit147@gmail.com','9067276262','$2y$10$gkWI8fL6zVt6iXAgoJVnVOjGQm6EXDe.amTf0Mg9E.JWbyxn4cgT.','F','A');
INSERT INTO `users` VALUES('9','Khushal Savaliya','23bmiit089@gmail.com','7600524005','$2y$10$taJapk2fHiquO9zbLoWKge/Vf5DDwaPPggJXCuDsupi64pUh4XuiW','O','A');
INSERT INTO `users` VALUES('11','Yash Gadhiya','23bmiit141@gmail.com','9925875823','$2y$10$wwJxWFNzmmIEplipT8G4Vu8k.5bnNgiIuxAWJCcfYPmj60z6xwAzy','F','A');

