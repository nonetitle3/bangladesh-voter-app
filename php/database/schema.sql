CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(190) NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(700) NULL,
  page_count INT NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  ocr_used TINYINT(1) NOT NULL DEFAULT 0,
  error_msg TEXT NULL,
  uploaded_at DATETIME NOT NULL,
  INDEX idx_documents_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voter_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NULL,
  voter_id VARCHAR(80) NULL,
  serial_no VARCHAR(40) NULL,
  name VARCHAR(255) NULL,
  father_name VARCHAR(255) NULL,
  mother_name VARCHAR(255) NULL,
  birth_date VARCHAR(40) NULL,
  gender VARCHAR(30) NULL,
  occupation VARCHAR(255) NULL,
  address TEXT NULL,
  village VARCHAR(255) NULL,
  ward VARCHAR(100) NULL,
  union_name VARCHAR(255) NULL,
  upazila VARCHAR(255) NULL,
  district VARCHAR(255) NULL,
  division VARCHAR(255) NULL,
  post_code VARCHAR(30) NULL,
  pdf_filename VARCHAR(255) NULL,
  page_number INT NULL,
  raw_text MEDIUMTEXT NULL,
  confidence DECIMAL(6,3) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_voter_document FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE SET NULL,
  INDEX idx_voter_id(voter_id),
  INDEX idx_voter_name(name),
  INDEX idx_voter_location(district,upazila),
  INDEX idx_voter_document(document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;