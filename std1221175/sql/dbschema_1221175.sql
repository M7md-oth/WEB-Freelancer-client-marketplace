CREATE DATABASE IF NOT EXISTS freelance_marketplace 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; 
 
USE freelance_marketplace; 

DROP TABLE IF EXISTS `file_attachments`;
DROP TABLE IF EXISTS `revision_requests`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE users ( 
    user_id VARCHAR(10) PRIMARY KEY, 
    first_name VARCHAR(50) NOT NULL, 
    last_name VARCHAR(50) NOT NULL, 
    email VARCHAR(100) NOT NULL UNIQUE, 
    password VARCHAR(255) NOT NULL, 
    phone VARCHAR(10) NOT NULL, 
    country VARCHAR(50) NOT NULL, 
    city VARCHAR(50) NOT NULL, 
    bio TEXT,
    role ENUM('Client', 'Freelancer') NOT NULL, 
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active', 
    profile_photo VARCHAR(255), 
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services ( 
    service_id VARCHAR(10) PRIMARY KEY, 
    freelancer_id VARCHAR(10) NOT NULL, 
    title VARCHAR(200) NOT NULL, 
    category VARCHAR(100) NOT NULL, 
    subcategory VARCHAR(100) NOT NULL, 
    description TEXT NOT NULL, 
    price DECIMAL(10,2) NOT NULL, 
    delivery_time INT NOT NULL, 
    revisions_included INT NOT NULL, 
    image_1 VARCHAR(255) NOT NULL, 
    image_2 VARCHAR(255), 
    image_3 VARCHAR(255), 
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active', 
    featured_status ENUM('Yes', 'No') NOT NULL DEFAULT 'No', 
    views INT NOT NULL DEFAULT 0,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders ( 
    order_id VARCHAR(10) PRIMARY KEY, 
    client_id VARCHAR(10) NOT NULL, 
    freelancer_id VARCHAR(10) NOT NULL, 
    service_id VARCHAR(10) NOT NULL, 
    service_title VARCHAR(200) NOT NULL, 
    price DECIMAL(10,2) NOT NULL, 
    delivery_time INT NOT NULL, 
    revisions_included INT NOT NULL, 
    requirements TEXT NOT NULL, 
    deliverable_notes TEXT, 
    status ENUM('Pending', 'In Progress', 'Delivered', 'Completed', 'Revision Requested', 'Cancelled') NOT NULL DEFAULT 'Pending', 
    payment_method VARCHAR(50) NOT NULL, 
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    expected_delivery DATE NOT NULL, 
    completion_date TIMESTAMP NULL, 
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE, 
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE, 
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE revision_requests ( 
    revision_id INT AUTO_INCREMENT PRIMARY KEY, 
    order_id VARCHAR(10) NOT NULL, 
    revision_notes TEXT NOT NULL, 
    revision_file VARCHAR(255), 
    request_status ENUM('Pending', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending', 
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    response_date TIMESTAMP NULL, 
    freelancer_response TEXT, 
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE file_attachments ( 
    file_id INT AUTO_INCREMENT PRIMARY KEY, 
    order_id VARCHAR(10) NOT NULL, 
    file_path VARCHAR(255) NOT NULL, 
    original_filename VARCHAR(255) NOT NULL, 
    file_size INT NOT NULL, 
    file_type ENUM('requirement', 'deliverable', 'revision') NOT NULL, 
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `services` ADD INDEX `idx_category` (`category`);
ALTER TABLE `services` ADD INDEX `idx_status` (`status`);
ALTER TABLE `services` ADD INDEX `idx_featured` (`featured_status`);
ALTER TABLE `orders` ADD INDEX `idx_order_status` (`status`);
ALTER TABLE `orders` ADD INDEX `idx_order_date` (`order_date`);
ALTER TABLE `revision_requests` ADD INDEX `idx_revision_status` (`request_status`);

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `phone`, `country`, `city`, `bio`, `role`, `status`, `profile_photo`, `registration_date`) VALUES
('1000000001', 'Mohammed', 'Abdallah', 'mOthman@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0591234567', 'Palestine', 'Ramallah', 'Experienced web developer specializing in WordPress and PHP development.', 'Freelancer', 'Active', NULL, '2026-01-11 09:03:58'),
('1000000002', 'Sara', 'Ahmad', 'sara.ahmad@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0591234568', 'Palestine', 'Nablus', 'Digital marketing expert with 5 years of experience.', 'Freelancer', 'Active', NULL, '2026-01-12 10:15:00'),
('1000000003', 'Omar', 'Hassan', 'omar.hassan@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0591234569', 'Jordan', 'Amman', 'Full-stack developer passionate about React and Node.js.', 'Freelancer', 'Active', NULL, '2026-01-13 11:30:00'),
('1000000004', 'Ahmad', 'Khalil', 'ahmad.khalil@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0591234570', 'Palestine', 'Hebron', NULL, 'Client', 'Active', NULL, '2026-01-14 08:45:00'),
('1000000005', 'Layla', 'Mansour', 'layla.mansour@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0591234571', 'UAE', 'Dubai', NULL, 'Client', 'Active', NULL, '2026-01-15 14:20:00');

INSERT INTO `categories` (`category_name`) VALUES
('Web Development'),
('Graphic Design'),
('Digital Marketing'),
('Writing & Content'),
('Video & Animation'),
('Music & Audio');

INSERT INTO `services` (`service_id`, `freelancer_id`, `title`, `category`, `subcategory`, `description`, `price`, `delivery_time`, `revisions_included`, `image_1`, `image_2`, `image_3`, `status`, `featured_status`, `created_date`) VALUES
('2000000001', '1000000001', 'Professional WordPress Website Development', 'Web Development', 'WordPress', 'I will build a professional, responsive WordPress website for your business. Includes custom theme setup, plugin configuration, and SEO optimization. Perfect for small businesses, portfolios, and blogs.', 500.00, 7, 3, 'Images/im1.webp', NULL, NULL, 'Active', 'Yes', '2026-01-11 09:03:58'),
('2000000002', '1000000001', 'Modern Logo Design', 'Graphic Design', 'Logo Design', 'I will create a modern, unique logo design for your brand. Includes multiple concepts, unlimited revisions until you are satisfied, and all source files.', 80.00, 3, 2, 'Images/im2.png', NULL, NULL, 'Active', 'Yes', '2026-01-11 09:03:58'),
('2000000003', '1000000001', 'Complete SEO Audit Report', 'Digital Marketing', 'SEO', 'I will provide a comprehensive SEO audit of your website including technical SEO, on-page optimization, backlink analysis, and actionable recommendations to improve your rankings.', 120.00, 5, 1, 'Images/im3.png', NULL, NULL, 'Active', 'No', '2026-01-11 09:03:58'),
('2000000004', '1000000001', 'Website Speed Optimization', 'Web Development', 'Performance', 'I will optimize your website speed and performance. Includes image optimization, caching setup, code minification, and Core Web Vitals improvement.', 150.00, 3, 1, 'Images/im4.png', NULL, NULL, 'Active', 'No', '2026-01-11 10:14:37'),
('2000000005', '1000000001', 'UI/UX Bug Fixes and Improvements', 'Web Development', 'UI/UX', 'I will fix UI issues and improve the user experience on your website. Includes responsive design fixes, accessibility improvements, and visual enhancements.', 70.00, 2, 1, 'Images/im5.png', NULL, NULL, 'Active', 'No', '2026-01-11 10:14:37'),
('2000000006', '1000000002', 'Social Media Marketing Strategy', 'Digital Marketing', 'Social Media Marketing', 'I will create a comprehensive social media marketing strategy for your business. Includes content calendar, posting schedule, and engagement tactics for all major platforms.', 200.00, 5, 2, 'Images/im1.webp', NULL, NULL, 'Active', 'Yes', '2026-01-12 11:00:00'),
('2000000007', '1000000002', 'Professional Article Writing', 'Writing & Content', 'Article Writing', 'I will write engaging, SEO-optimized articles for your blog or website. Well-researched content with proper formatting and keywords integration.', 50.00, 2, 2, 'Images/im2.png', NULL, NULL, 'Active', 'No', '2026-01-12 12:30:00'),
('2000000008', '1000000002', 'Brand Identity Design Package', 'Graphic Design', 'Brand Identity', 'Complete brand identity design including logo, business cards, letterhead, and brand guidelines document. Everything you need to establish your brand.', 350.00, 10, 3, 'Images/im3.png', NULL, NULL, 'Active', 'No', '2026-01-12 14:00:00'),
('2000000009', '1000000003', 'React.js Web Application Development', 'Web Development', 'React', 'I will build a modern, fast React.js web application. Includes responsive design, state management, API integration, and deployment assistance.', 800.00, 14, 3, 'Images/im4.png', NULL, NULL, 'Active', 'Yes', '2026-01-13 09:00:00'),
('2000000010', '1000000003', 'Full Stack Web Development', 'Web Development', 'Full Stack', 'Complete full-stack web development using modern technologies. Frontend, backend, database design, and deployment all included.', 1200.00, 21, 4, 'Images/im5.png', NULL, NULL, 'Active', 'No', '2026-01-13 10:30:00'),
('2000000011', '1000000003', 'PPC Campaign Management', 'Digital Marketing', 'PPC', 'I will set up and manage your Google Ads or Facebook Ads campaigns. Includes keyword research, ad copy writing, and performance optimization.', 300.00, 7, 2, 'Images/im1.webp', NULL, NULL, 'Active', 'No', '2026-01-13 12:00:00'),
('2000000012', '1000000001', 'Basic HTML Website', 'Web Development', 'PHP', 'Simple static HTML website development. Currently unavailable.', 100.00, 3, 1, 'Images/im2.png', NULL, NULL, 'Inactive', 'No', '2026-01-10 08:00:00');

INSERT INTO `orders` (`order_id`, `client_id`, `freelancer_id`, `service_id`, `service_title`, `price`, `delivery_time`, `revisions_included`, `requirements`, `deliverable_notes`, `status`, `payment_method`, `order_date`, `expected_delivery`, `completion_date`) VALUES
('3000000001', '1000000004', '1000000001', '2000000001', 'Professional WordPress Website Development', 500.00, 7, 3, 'I need a business website for my electronics store. It should have a homepage, about us, products page, and contact form. Please use blue and white colors.', 'Website completed with all requested pages. Login credentials sent via email.', 'Completed', 'Credit Card', '2026-01-16 10:00:00', '2026-01-23', '2026-01-22 15:30:00'),
('3000000002', '1000000004', '1000000001', '2000000002', 'Modern Logo Design', 80.00, 3, 2, 'Need a logo for my tech startup called "TechVision". Modern, minimalist style. Prefer blue/purple colors.', NULL, 'In Progress', 'PayPal', '2026-01-20 14:00:00', '2026-01-23', NULL),
('3000000003', '1000000004', '1000000002', '2000000007', 'Professional Article Writing', 50.00, 2, 2, 'Write a 1500-word article about "Benefits of Cloud Computing for Small Businesses". Include statistics and real examples.', NULL, 'Pending', 'Credit Card', '2026-01-25 09:00:00', '2026-01-27', NULL),
('3000000004', '1000000005', '1000000003', '2000000009', 'React.js Web Application Development', 800.00, 14, 3, 'Build a customer management dashboard with React. Need user authentication, customer list with search/filter, and basic analytics charts.', 'Initial version delivered. Awaiting feedback.', 'Delivered', 'Bank Transfer', '2026-01-10 11:00:00', '2026-01-24', NULL),
('3000000005', '1000000005', '1000000002', '2000000006', 'Social Media Marketing Strategy', 200.00, 5, 2, 'Create a 3-month social media strategy for my fashion boutique. Focus on Instagram and TikTok. Target audience: women 18-35.', NULL, 'Revision Requested', 'PayPal', '2026-01-18 16:00:00', '2026-01-23', NULL),
('3000000006', '1000000005', '1000000001', '2000000003', 'Complete SEO Audit Report', 120.00, 5, 1, 'Full SEO audit for www.example-fashion.com. Focus on improving organic traffic and ranking for fashion-related keywords.', NULL, 'Cancelled', 'Credit Card', '2026-01-05 08:00:00', '2026-01-10', NULL);

INSERT INTO `revision_requests` (`order_id`, `revision_notes`, `revision_file`, `request_status`, `request_date`, `response_date`, `freelancer_response`) VALUES
('3000000005', 'Please add more specific content ideas for TikTok. Also, I would like to see a posting schedule by day of the week.', NULL, 'Pending', '2026-01-24 10:00:00', NULL, NULL),
('3000000001', 'Can you add a newsletter signup form to the homepage?', NULL, 'Accepted', '2026-01-21 09:00:00', '2026-01-21 14:00:00', 'Sure! I have added the newsletter signup form to the homepage footer. Please review.');

INSERT INTO `file_attachments` (`order_id`, `file_path`, `original_filename`, `file_size`, `file_type`, `upload_timestamp`) VALUES
('3000000001', 'uploads/orders/3000000001/req_brand_guidelines.pdf', 'brand_guidelines.pdf', 245000, 'requirement', '2026-01-16 10:05:00'),
('3000000001', 'uploads/orders/3000000001/del_website_files.zip', 'website_files.zip', 15000000, 'deliverable', '2026-01-22 15:00:00'),
('3000000004', 'uploads/orders/3000000004/req_wireframes.png', 'wireframes.png', 520000, 'requirement', '2026-01-10 11:30:00'),
('3000000004', 'uploads/orders/3000000004/del_dashboard_v1.zip', 'dashboard_v1.zip', 8500000, 'deliverable', '2026-01-23 18:00:00');
