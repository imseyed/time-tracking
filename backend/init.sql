CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(7) NOT NULL DEFAULT '#FC572C'
);

CREATE TABLE IF NOT EXISTS time_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  project_id INT NOT NULL,
  work_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  description TEXT,
  duration_minutes INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_entries_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_entries_project FOREIGN KEY (project_id) REFERENCES projects(id)
);

INSERT INTO users (full_name) VALUES
('علی رضایی'),
('سارا احمدی'),
('مهدی محمدی');

INSERT INTO projects (name, color) VALUES
('وب‌سایت اصلی', '#FC572C'),
('اپ موبایل', '#FF7A45'),
('پشتیبانی مشتریان', '#E84A24');
