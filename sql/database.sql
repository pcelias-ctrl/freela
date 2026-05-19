CREATE DATABASE IF NOT EXISTS escala_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE escala_app;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  responsible_name VARCHAR(160),
  phone VARCHAR(40),
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  address TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE job_functions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT,
  default_daily_value DECIMAL(10,2) DEFAULT 0,
  default_hours DECIMAL(4,2) DEFAULT 8.00,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE freelancers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(180) NOT NULL,
  full_address TEXT NOT NULL,
  phone VARCHAR(40) NOT NULL,
  whatsapp VARCHAR(40) NOT NULL,
  chavepix VARCHAR(200),
  password_hash VARCHAR(255) NULL,
  age INT NOT NULL,
  sex ENUM('feminino','masculino','outro','nao_informar') NOT NULL DEFAULT 'nao_informar',
  restaurant_experience TEXT,
  main_function_id INT NULL,
  photo_path VARCHAR(255),
  status ENUM('pending','approved','rejected','blocked') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_freelancer_function FOREIGN KEY (main_function_id) REFERENCES job_functions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  function_id INT NOT NULL,
  shift_date DATE NOT NULL,
  shift_type ENUM('almoco','jantar','evento') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  vacancies INT NOT NULL DEFAULT 1,
  pay_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT,
  status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  created_by_type ENUM('admin','restaurant') NOT NULL DEFAULT 'admin',
  created_by_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_shift_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_function FOREIGN KEY (function_id) REFERENCES job_functions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shift_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_id INT NOT NULL,
  freelancer_id INT NOT NULL,
  status ENUM('pending','confirmed','cancelled','no_show','completed') NOT NULL DEFAULT 'pending',
  checkin_at DATETIME NULL,
  checkin_lat DECIMAL(10,7) NULL,
  checkin_lng DECIMAL(10,7) NULL,
  checkout_at DATETIME NULL,
  checkout_lat DECIMAL(10,7) NULL,
  checkout_lng DECIMAL(10,7) NULL,
  payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  payment_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_shift_freelancer (shift_id, freelancer_id),
  CONSTRAINT fk_application_shift FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_freelancer FOREIGN KEY (freelancer_id) REFERENCES freelancers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login inicial do administrador: admin@admin.com / admin123
INSERT INTO admins (name, email, password_hash) VALUES
('Administrador', 'admin@admin.com', '$2y$10$9zU8AvWZNS8rBGC1VY9ROO1RGF1BZLqCD1zPzDxNqwHXzZcOVJ6py');

INSERT INTO job_functions (name, description, default_daily_value, default_hours) VALUES
('Garçom', 'Atendimento de salão e eventos', 120.00, 8.00),
('Auxiliar de cozinha', 'Apoio na cozinha e mise en place', 120.00, 8.00),
('Cozinheiro', 'Produção e finalização de pratos', 180.00, 8.00),
('Sommelier', 'Serviço e orientação de vinhos', 180.00, 8.00);
