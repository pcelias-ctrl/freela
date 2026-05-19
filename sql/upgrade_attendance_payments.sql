USE escala_app;

ALTER TABLE shift_applications
  ADD COLUMN checkin_at DATETIME NULL AFTER status,
  ADD COLUMN checkin_lat DECIMAL(10,7) NULL AFTER checkin_at,
  ADD COLUMN checkin_lng DECIMAL(10,7) NULL AFTER checkin_lat,
  ADD COLUMN checkout_at DATETIME NULL AFTER checkin_lng,
  ADD COLUMN checkout_lat DECIMAL(10,7) NULL AFTER checkout_at,
  ADD COLUMN checkout_lng DECIMAL(10,7) NULL AFTER checkout_lat,
  ADD COLUMN payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending' AFTER checkout_lng,
  ADD COLUMN payment_at DATETIME NULL AFTER payment_status;
