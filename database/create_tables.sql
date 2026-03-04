-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('all', 'specific', 'topic') DEFAULT 'all',
    target_users JSON NULL,
    topic VARCHAR(100) NULL,
    status ENUM('draft', 'sent', 'failed') DEFAULT 'draft',
    sent_at TIMESTAMP NULL,
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    fcm_response JSON NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES pegawai(id_pegawai)
);

-- Create user_fcm_tokens table
CREATE TABLE IF NOT EXISTS user_fcm_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    fcm_token VARCHAR(500) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    device_type VARCHAR(20) DEFAULT 'android',
    app_version VARCHAR(20) NULL,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES pegawai(id_pegawai),
    UNIQUE KEY unique_user_device (user_id, device_id)
);

-- Create hari_libur table untuk menyimpan hari libur nasional dan cuti bersama
CREATE TABLE IF NOT EXISTS hari_libur (
    id_hari_libur INT PRIMARY KEY AUTO_INCREMENT,
    tanggal_libur DATE NOT NULL UNIQUE,
    nama_hari_libur VARCHAR(255) NOT NULL,
    tipe_libur ENUM('nasional', 'cuti_bersama', 'custom') DEFAULT 'nasional',
    sumber ENUM('api', 'admin') DEFAULT 'admin',
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES pegawai(id_pegawai) ON DELETE SET NULL,
    INDEX idx_tanggal_libur (tanggal_libur),
    INDEX idx_tipe_libur (tipe_libur),
    INDEX idx_sumber (sumber)
);
