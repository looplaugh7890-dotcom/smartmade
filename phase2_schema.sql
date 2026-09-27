-- ============================================================================
-- SmartMade — PHASE 2 SCHEMA (E-Commerce, Customers, Reviews, Gallery, SEO)
-- Run AFTER smartmade_db.sql  |  MySQL 5.7+ / MariaDB 10.4+  |  InnoDB utf8mb4
-- Money: DECIMAL(10,2) GBP.  No existing table is modified or dropped.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. CUSTOMER ACCOUNTS
-- ---------------------------------------------------------------------------

CREATE TABLE `customers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `company` varchar(160) DEFAULT NULL,
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `marketing_optin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_addresses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `label` varchar(60) NOT NULL DEFAULT 'Home',
  `full_name` varchar(160) NOT NULL,
  `line1` varchar(180) NOT NULL,
  `line2` varchar(180) DEFAULT NULL,
  `city` varchar(120) NOT NULL,
  `county` varchar(120) DEFAULT NULL,
  `postcode` varchar(20) NOT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'United Kingdom',
  `phone` varchar(40) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_addr_customer` (`customer_id`),
  CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_tokens` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `type` enum('password_reset','email_verify','remember_me') NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_token_lookup` (`customer_id`,`type`,`expires_at`),
  CONSTRAINT `fk_token_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. PRODUCT CATALOGUE
--    NOTE: `categories` already exists for PORTFOLIO only. Store uses
--    `product_categories` so the two systems never collide.
-- ---------------------------------------------------------------------------

CREATE TABLE `product_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','hidden') NOT NULL DEFAULT 'active',
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pcat_slug` (`slug`),
  KEY `idx_pcat_parent` (`parent_id`),
  KEY `idx_pcat_status` (`status`),
  CONSTRAINT `fk_pcat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(11) UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `short_description` varchar(400) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `compare_at_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 20.00,
  `pricing_unit` enum('each','from','set') NOT NULL DEFAULT 'each',
  `min_order_qty` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `max_order_qty` int(11) UNSIGNED DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `manage_stock` tinyint(1) NOT NULL DEFAULT 0,
  `lead_time_days` int(11) UNSIGNED NOT NULL DEFAULT 7,
  `has_variants` tinyint(1) NOT NULL DEFAULT 0,
  `requires_personalisation` tinyint(1) NOT NULL DEFAULT 0,
  `allow_artwork_upload` tinyint(1) NOT NULL DEFAULT 0,
  `artwork_help_text` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sales_count` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `view_count` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_cat` (`category_id`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_featured` (`is_featured`),
  KEY `idx_products_price` (`base_price`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(11) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `alt` varchar(200) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pimg_product` (`product_id`,`sort_order`),
  CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Size / Colour variants (proposal only requires size + colour)
CREATE TABLE `product_variants` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(11) UNSIGNED NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `size` varchar(40) DEFAULT NULL,
  `colour_name` varchar(60) DEFAULT NULL,
  `colour_hex` char(7) DEFAULT NULL,
  `price_delta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `manage_stock` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_sku` (`sku`),
  KEY `idx_variant_product` (`product_id`,`is_active`),
  KEY `idx_variant_lookup` (`product_id`,`size`,`colour_name`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-product personalisation fields (name, custom text, club number, etc.)
CREATE TABLE `product_personalisation` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(11) UNSIGNED NOT NULL,
  `field_key` varchar(60) NOT NULL,
  `label` varchar(160) NOT NULL,
  `input_type` enum('text','textarea','select','checkbox','date','file') NOT NULL DEFAULT 'text',
  `placeholder` varchar(160) DEFAULT NULL,
  `help_text` varchar(255) DEFAULT NULL,
  `options_json` text DEFAULT NULL,
  `max_length` int(11) UNSIGNED DEFAULT NULL,
  `max_files` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `price_add` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pers_field` (`product_id`,`field_key`),
  CONSTRAINT `fk_pers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. BASKET (session-based, survives guest checkout)
-- ---------------------------------------------------------------------------

CREATE TABLE `carts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `session_id` varchar(128) NOT NULL,
  `coupon_code` varchar(40) DEFAULT NULL,
  `status` enum('active','converted','abandoned','merged') NOT NULL DEFAULT 'active',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_session` (`session_id`,`status`),
  KEY `idx_cart_customer` (`customer_id`),
  KEY `idx_cart_expiry` (`expires_at`),
  CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `variant_id` int(11) UNSIGNED DEFAULT NULL,
  `quantity` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `personalisation_json` text DEFAULT NULL,
  `artwork_paths` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_citem_cart` (`cart_id`),
  KEY `idx_citem_product` (`product_id`),
  KEY `idx_citem_variant` (`variant_id`),
  CONSTRAINT `fk_citem_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_citem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_citem_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. CHECKOUT: SHIPPING, DISCOUNTS, ORDERS, PAYMENTS
-- ---------------------------------------------------------------------------

CREATE TABLE `shipping_methods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `per_item_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `free_over` decimal(10,2) DEFAULT NULL,
  `min_days` int(11) UNSIGNED NOT NULL DEFAULT 3,
  `max_days` int(11) UNSIGNED NOT NULL DEFAULT 7,
  `countries` varchar(255) NOT NULL DEFAULT 'GB',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ship_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coupons` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `type` enum('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_subtotal` decimal(10,2) DEFAULT NULL,
  `max_uses` int(11) UNSIGNED DEFAULT NULL,
  `used_count` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `status` enum('pending','awaiting_payment','paid','in_production','ready','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','authorised','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(40) DEFAULT 'manual',
  `currency` char(3) NOT NULL DEFAULT 'GBP',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(40) DEFAULT NULL,
  `shipping_method` varchar(60) DEFAULT NULL,
  `shipping_address_json` text DEFAULT NULL,
  `billing_address_json` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `tracking_reference` varchar(120) DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_order_customer` (`customer_id`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_payment` (`payment_status`),
  KEY `idx_order_placed` (`placed_at`),
  KEY `idx_order_email` (`email`),
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED DEFAULT NULL,
  `variant_id` int(11) UNSIGNED DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `variant_label` varchar(120) DEFAULT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `quantity` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `personalisation_json` text DEFAULT NULL,
  `line_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oitem_order` (`order_id`),
  KEY `idx_oitem_product` (`product_id`),
  KEY `idx_oitem_variant` (`variant_id`),
  CONSTRAINT `fk_oitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_oitem_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer logo / artwork files attached to an order line
CREATE TABLE `order_artwork` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(200) DEFAULT NULL,
  `file_type` varchar(80) DEFAULT NULL,
  `file_size` int(11) UNSIGNED DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_art_item` (`order_item_id`),
  CONSTRAINT `fk_art_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_events` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `actor` enum('customer','admin','system','gateway') NOT NULL DEFAULT 'system',
  `admin_id` int(11) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_oev_order` (`order_id`,`created_at`),
  CONSTRAINT `fk_oev_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `provider` enum('stripe','paypal','manual','bank_transfer') NOT NULL DEFAULT 'manual',
  `provider_payment_id` varchar(120) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'GBP',
  `status` enum('pending','succeeded','failed','refunded','disputed') NOT NULL DEFAULT 'pending',
  `failure_reason` varchar(255) DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_payment` (`provider`,`provider_payment_id`),
  KEY `idx_pay_order` (`order_id`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. GALLERY  (proposal section 8 — separate from the existing portfolio)
-- ---------------------------------------------------------------------------

CREATE TABLE `gallery_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `service_type` enum('embroidery','printing','promotional','other') NOT NULL DEFAULT 'embroidery',
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `additional_images` text DEFAULT NULL,
  `client_name` varchar(160) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gallery_slug` (`slug`),
  KEY `idx_gallery_service` (`service_type`,`status`),
  KEY `idx_gallery_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. PUBLIC REVIEWS (proposal section 9)
--    `testimonials` (existing) stays admin-authored. `reviews` = customer
--    submitted, moderated, optionally tied to a product.
-- ---------------------------------------------------------------------------

CREATE TABLE `reviews` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(11) UNSIGNED DEFAULT NULL,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `author_name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `admin_reply` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review_status` (`status`),
  KEY `idx_review_product` (`product_id`,`status`),
  KEY `idx_review_rating` (`rating`),
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7. QUOTE SYSTEM EXTENSION (multi-file artwork + order conversion)
--    `quote_requests` is untouched; these hang off it.
-- ---------------------------------------------------------------------------

CREATE TABLE `quote_attachments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(200) DEFAULT NULL,
  `file_type` varchar(80) DEFAULT NULL,
  `file_size` int(11) UNSIGNED DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qatt_quote` (`quote_id`),
  CONSTRAINT `fk_qatt_quote` FOREIGN KEY (`quote_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 8. SEO, ADMIN OPS, MISC
-- ---------------------------------------------------------------------------

CREATE TABLE `page_seo` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` varchar(80) NOT NULL,
  `path` varchar(180) NOT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical` varchar(255) DEFAULT NULL,
  `robots` varchar(40) NOT NULL DEFAULT 'index,follow',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seo_page` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity` varchar(60) DEFAULT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_act_admin` (`admin_id`,`created_at`),
  KEY `idx_act_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_login_attempts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempt_lookup` (`email`,`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `template` varchar(60) DEFAULT NULL,
  `related_type` varchar(40) DEFAULT NULL,
  `related_id` int(11) UNSIGNED DEFAULT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_related` (`related_type`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `status` enum('pending','active','unsubscribed') NOT NULL DEFAULT 'active',
  `token` char(32) DEFAULT NULL,
  `subscribed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 9. SEED DATA
-- ---------------------------------------------------------------------------

INSERT INTO `product_categories` (`id`,`name`,`slug`,`description`,`sort_order`) VALUES
(1,'Embroidery','embroidery','Custom embroidered garments, caps, hoodies and teamwear.',1),
(2,'Printing','printing','DTG and screen printing for bold, high-volume designs.',2),
(3,'Promotional','promotional','Branded merchandise, tote bags, caps and corporate gifts.',3),
(4,'Artwork & Digitising','artwork-digitising','Logo digitising, design setup and one-off art commissions.',4);

INSERT INTO `shipping_methods` (`code`,`name`,`description`,`base_price`,`per_item_price`,`free_over`,`min_days`,`max_days`,`sort_order`) VALUES
('uk_standard','UK Standard Delivery','Tracked delivery across the UK.',3.95,0.50,75.00,3,7,1),
('uk_express','UK Express Delivery','Next working day on stocked items.',7.95,0.00,NULL,1,2,2),
('local_pickup','Studio Pickup (Fife)','Collect from the studio — we will confirm when ready.',0.00,0.00,NULL,1,3,3),
('international','International Delivery','Quoted per order — we will confirm before payment.',14.95,1.50,NULL,7,21,4);

INSERT INTO `site_settings` (`setting_key`,`setting_value`,`label`) VALUES
('currency','GBP','Currency'),
('currency_symbol','£','Currency Symbol'),
('vat_rate','20.00','VAT Rate (%)'),
('vat_included','1','Prices Include VAT'),
('free_shipping_threshold','75.00','Free Shipping Threshold'),
('low_stock_threshold','5','Low Stock Alert Threshold'),
('enable_stripe','0','Enable Stripe'),
('stripe_public_key','','Stripe Publishable Key'),
('stripe_secret_key','','Stripe Secret Key'),
('stripe_webhook_secret','','Stripe Webhook Secret'),
('enable_paypal','0','Enable PayPal'),
('enable_bank_transfer','1','Enable Bank Transfer / Invoice'),
('bank_name','','Bank Name'),
('bank_account_name','','Account Name'),
('bank_sort_code','','Sort Code'),
('bank_account_number','','Account Number'),
('smtp_host','','SMTP Host'),
('smtp_port','587','SMTP Port'),
('smtp_user','','SMTP Username'),
('smtp_pass','','SMTP Password'),
('smtp_encryption','tls','SMTP Encryption'),
('order_email_subject','We have received your SmartMade order','Order Email Subject'),
('shop_page_title','Shop','Shop Page Title'),
('allow_guest_checkout','1','Allow Guest Checkout'),
('enable_reviews','1','Enable Public Reviews'),
('reviews_require_approval','1','Reviews Require Approval');

-- ---------------------------------------------------------------------------
-- 10. COLUMNS ON EXISTING TABLES (non-destructive ADD COLUMN only)
-- ---------------------------------------------------------------------------

ALTER TABLE `quote_requests`
  ADD COLUMN `service` varchar(120) DEFAULT NULL AFTER `order_type`,
  ADD COLUMN `converted_order_id` int(11) UNSIGNED DEFAULT NULL AFTER `admin_notes`,
  ADD KEY `idx_quote_converted` (`converted_order_id`);

ALTER TABLE `testimonials`
  ADD COLUMN `source` enum('manual','review','import') NOT NULL DEFAULT 'manual' AFTER `status`,
  ADD COLUMN `updated_at` datetime DEFAULT NULL AFTER `created_at`;

ALTER TABLE `contact_messages`
  ADD COLUMN `replied_at` datetime DEFAULT NULL AFTER `admin_notes`;

ALTER TABLE `blog_posts`
  ADD COLUMN `allow_comments` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

SET FOREIGN_KEY_CHECKS = 1;
