-- ==========================================================
-- iCookies Inventory Management - Schema + Seed
-- ==========================================================
CREATE DATABASE IF NOT EXISTS inventory DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE inventory;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, disposal_details, disposal_headers,
 stocktaking_details, stocktaking_headers, inventory_transfer_details,
 inventory_transfers, inventory_outgoing_details, inventory_outgoing_headers,
 inventory_incoming_details, inventory_incoming_headers, inventory_transactions,
 inventory_batches, customers, master_parts, suppliers, warehouses,
 user_warehouses, user_roles, roles, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE user_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  UNIQUE KEY uq_ur (user_id, role_id),
  CONSTRAINT fk_ur_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_r FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  warehouse_code VARCHAR(20) NOT NULL UNIQUE,
  warehouse_name VARCHAR(100) NOT NULL,
  warehouse_address VARCHAR(255) NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE user_warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  UNIQUE KEY uq_uw (user_id, warehouse_id),
  CONSTRAINT fk_uw_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uw_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(120) NOT NULL,
  contact_person VARCHAR(100) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(100) NULL,
  address VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(100) NULL,
  address VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE master_parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  part_code VARCHAR(50) NOT NULL UNIQUE,
  device_type VARCHAR(50) NOT NULL,
  device_model VARCHAR(50) NOT NULL,
  part_category VARCHAR(50) NOT NULL,
  part_variant VARCHAR(50) NULL,
  warranty_month INT NOT NULL DEFAULT 1,
  purch_idr DECIMAL(15,2) NOT NULL DEFAULT 0,
  sell_idr DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_part_dev (device_type, device_model),
  INDEX idx_part_cat (part_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_code VARCHAR(60) NOT NULL,
  part_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  supplier_id INT NULL,
  incoming_id INT NULL,
  incoming_date DATE NOT NULL,
  expiration_date DATE NOT NULL,
  qty_received INT NOT NULL DEFAULT 0,
  qty_remaining INT NOT NULL DEFAULT 0,
  purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_batch_wh (batch_code, warehouse_id),
  INDEX idx_b_part (part_id, warehouse_id),
  INDEX idx_b_exp (expiration_date),
  CONSTRAINT fk_b_part FOREIGN KEY (part_id) REFERENCES master_parts(id),
  CONSTRAINT fk_b_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trx_number VARCHAR(50) NOT NULL,
  trx_date DATETIME NOT NULL,
  trx_type VARCHAR(30) NOT NULL,
  part_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  batch_id INT NULL,
  qty_in INT NOT NULL DEFAULT 0,
  qty_out INT NOT NULL DEFAULT 0,
  balance_after INT NOT NULL DEFAULT 0,
  reference_number VARCHAR(50) NULL,
  user_id INT NULL,
  remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_t_part (part_id),
  INDEX idx_t_wh (warehouse_id),
  INDEX idx_t_type (trx_type),
  INDEX idx_t_date (trx_date),
  INDEX idx_t_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_incoming_headers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incoming_number VARCHAR(50) NOT NULL UNIQUE,
  incoming_date DATE NOT NULL,
  warehouse_id INT NOT NULL,
  supplier_id INT NULL,
  remarks VARCHAR(255) NULL,
  total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_at DATETIME NULL,
  INDEX idx_ih_date (incoming_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_incoming_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incoming_id INT NOT NULL,
  part_id INT NOT NULL,
  batch_id INT NULL,
  qty_received INT NOT NULL,
  purchase_price DECIMAL(15,2) NOT NULL,
  total_cost DECIMAL(15,2) NOT NULL,
  expiration_date DATE NOT NULL,
  INDEX idx_id_in (incoming_id),
  CONSTRAINT fk_id_in FOREIGN KEY (incoming_id) REFERENCES inventory_incoming_headers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_outgoing_headers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  outgoing_number VARCHAR(50) NOT NULL UNIQUE,
  outgoing_date DATE NOT NULL,
  warehouse_id INT NOT NULL,
  outgoing_type ENUM('SALE','ISSUE') NOT NULL DEFAULT 'SALE',
  customer_reference VARCHAR(150) NULL,
  remarks VARCHAR(255) NULL,
  total_purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_selling_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  gross_profit DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_oh_date (outgoing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_outgoing_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  outgoing_id INT NOT NULL,
  part_id INT NOT NULL,
  batch_id INT NULL,
  qty INT NOT NULL,
  purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_selling_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  gross_profit DECIMAL(15,2) NOT NULL DEFAULT 0,
  warranty_end_date DATE NULL,
  INDEX idx_od_out (outgoing_id),
  CONSTRAINT fk_od_out FOREIGN KEY (outgoing_id) REFERENCES inventory_outgoing_headers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_number VARCHAR(50) NOT NULL UNIQUE,
  transfer_date DATE NOT NULL,
  source_warehouse_id INT NOT NULL,
  destination_warehouse_id INT NOT NULL,
  status ENUM('PENDING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_tr_date (transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE inventory_transfer_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  part_id INT NOT NULL,
  source_batch_id INT NULL,
  dest_batch_id INT NULL,
  qty INT NOT NULL,
  purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  expiration_date DATE NULL,
  batch_code VARCHAR(60) NULL,
  INDEX idx_td_tr (transfer_id),
  CONSTRAINT fk_td_tr FOREIGN KEY (transfer_id) REFERENCES inventory_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE stocktaking_headers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stocktaking_number VARCHAR(50) NOT NULL UNIQUE,
  warehouse_id INT NOT NULL,
  stocktaking_date DATE NOT NULL,
  status ENUM('OPEN','COUNTED','APPROVED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  remarks VARCHAR(255) NULL,
  counted_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  INDEX idx_st_date (stocktaking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE stocktaking_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stocktaking_id INT NOT NULL,
  part_id INT NOT NULL,
  batch_id INT NOT NULL,
  system_qty INT NOT NULL DEFAULT 0,
  physical_qty INT NOT NULL DEFAULT 0,
  variance INT NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  INDEX idx_sd_st (stocktaking_id),
  CONSTRAINT fk_sd_st FOREIGN KEY (stocktaking_id) REFERENCES stocktaking_headers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE disposal_headers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  disposal_number VARCHAR(50) NOT NULL UNIQUE,
  disposal_date DATE NOT NULL,
  warehouse_id INT NOT NULL,
  reason ENUM('EXPIRED','DAMAGED','DEFECTIVE','OBSOLETE','LOST','OTHER') NOT NULL,
  remarks VARCHAR(255) NULL,
  total_value DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('PENDING','APPROVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  requested_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  INDEX idx_dh_date (disposal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE disposal_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  disposal_id INT NOT NULL,
  part_id INT NOT NULL,
  batch_id INT NOT NULL,
  qty INT NOT NULL,
  purchase_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_value DECIMAL(15,2) NOT NULL DEFAULT 0,
  INDEX idx_dd_d (disposal_id),
  CONSTRAINT fk_dd_d FOREIGN KEY (disposal_id) REFERENCES disposal_headers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  module VARCHAR(50) NOT NULL,
  reference VARCHAR(100) NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_al_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================ SEED ============================
INSERT INTO roles (role_name, description) VALUES
 ('ADMIN','Full access'),('WAREHOUSE STAFF','Warehouse operations'),
 ('SUPERVISOR','Approvals'),('SALES','Sales only');

INSERT INTO warehouses (warehouse_code, warehouse_name, warehouse_address, description) VALUES
 ('WH-A','Warehouse A','Jl. Contoh No.1','Main warehouse'),
 ('WH-B','Warehouse B','Jl. Contoh No.2','Secondary warehouse');

INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES
 ('PT Sumber Sparepart','Budi','021-5550001','sales@sumber.co.id','Jakarta'),
 ('CV Global Elektronik','Siti','021-5550002','info@globalelektronik.co.id','Bandung');

INSERT INTO customers (customer_name, phone) VALUES
 ('Walk-in Customer','-');

INSERT INTO master_parts (part_code, device_type, device_model, part_category, part_variant, warranty_month, purch_idr, sell_idr, is_active) VALUES
 ('IC-0021','iMac','A1418 (2K)','Power Supply','Standard',1,180000,200000,1),
 ('IC-0022','iMac','A1418 (2K)','LCD','Standard',1,180000,200000,1),
 ('IC-0023','iMac','A1418 (4K)','Power Supply','Standard',1,180000,200000,1),
 ('IC-0024','iMac','A1418 (4K)','LCD','Standard',1,180000,200000,1),
 ('IC-0025','iMac','A1419 (2K)','Power Supply','Standard',1,180000,200000,1),
 ('IC-0026','iMac','A1419 (2K)','LCD','Standard',1,180000,200000,1),
 ('IC-0027','iMac','A1419 (5K)','Power Supply','Standard',1,180000,200000,1);