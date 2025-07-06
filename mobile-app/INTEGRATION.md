# 📱 E-LAPKIN Mobile App Integration Guide

## 🔗 Status Integrasi

Mobile app **SUDAH TERINTEGRASI** dengan aplikasi web utama dengan fitur-fitur berikut:

### ✅ Yang Sudah Terintegrasi:

1. **Database yang Sama**
   - Mobile app menggunakan database `mtsnmaja_e-lapkin` yang sama dengan web utama
   - Tabel: `pegawai`, `lkh`, `rkb`, `rhk` semuanya terhubung

2. **Session Management**
   - Session mobile terpisah (`mobile_loggedin`) dari web session (`user_login`)
   - User dapat login di kedua platform secara bersamaan
   - Data user tetap sinkron

3. **Security Integration**
   - User Agent validation untuk memastikan akses hanya dari mobile app
   - Admin **TIDAK BISA** akses mobile app (hanya user biasa)
   - Development mode untuk testing di localhost

4. **Data Synchronization**
   - LKH data real-time dari database
   - RKB data real-time dari database
   - User data real-time dari database

5. **Web-to-Mobile Bridge**
   - Tombol akses mobile di dashboard web user
   - Token-based authentication untuk transisi seamless
   - QR Code generation untuk mobile access

## 🏗️ Struktur Integrasi

```
e-lapkin-mtsn11/
├── config/
│   └── database.php              # Database utama
├── user/
│   └── dashboard.php             # Dashboard web (dengan widget mobile)
└── mobile-app/
    ├── config/
    │   ├── mobile_database.php   # Database mobile (sama dengan utama)
    │   ├── mobile_security.php   # Keamanan mobile
    │   └── shared_session.php    # Session sharing
    ├── auth/
    │   ├── mobile_login.php      # Login mobile
    │   └── mobile_logout.php     # Logout mobile
    ├── user/
    │   └── dashboard.php         # Dashboard mobile
    ├── bridge.php                # Web-to-mobile bridge
    ├── integration_test.php      # Test integrasi
    └── web_integration.php       # Helper integrasi web
```

## 🔧 Konfigurasi Database

Database mobile menggunakan konfigurasi yang sama dengan web utama:

```php
// mobile-app/config/mobile_database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mtsnmaja_e-lapkin');
define('DB_USER', 'mtsnmaja_ataditya');
define('DB_PASS', 'Admin021398');
```

## 🔐 Keamanan Terintegrasi

### User Agent Validation
```
Expected: "E-LAPKIN-MTSN11-Mobile-App/1.0"
```

### Role-based Access
- ✅ User (role = 'user') → Bisa akses mobile
- ❌ Admin (role = 'admin') → Tidak bisa akses mobile

### Development Mode
- Localhost/127.0.0.1/192.168.x.x → Akses langsung diizinkan
- Production → Harus pakai User Agent yang benar

## 📊 Data Integration

### LKH (Laporan Kinerja Harian)
```sql
-- Query yang digunakan mobile app
SELECT 
    COUNT(*) as total_hari,
    SUM(CASE WHEN status_verval = 'disetujui' THEN 1 ELSE 0 END) as hari_approved,
    SUM(CASE WHEN status_verval = 'menunggu' OR status_verval IS NULL THEN 1 ELSE 0 END) as hari_pending,
    SUM(CASE WHEN status_verval = 'ditolak' THEN 1 ELSE 0 END) as hari_rejected
FROM lkh 
WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?
```

### RKB (Rencana Kinerja Bulanan)
```sql
-- Query yang digunakan mobile app
SELECT COUNT(*) as total_kegiatan
FROM rkb 
WHERE id_pegawai = ? AND tahun = ?
```

### Recent Activities
```sql
-- Query yang digunakan mobile app
SELECT 
    'lkh' as type,
    CONCAT('LKH - ', nama_kegiatan_harian) as activity,
    tanggal_lkh as date,
    COALESCE(status_verval, 'menunggu') as status
FROM lkh 
WHERE id_pegawai = ? 
ORDER BY tanggal_lkh DESC 
LIMIT 5
```

## 🌉 Web-to-Mobile Bridge

### Akses dari Dashboard Web
1. User login di web aplikasi
2. Dashboard menampilkan widget "Mobile App"
3. User klik tombol "Buka Mobile App"
4. Token generated untuk secure transition
5. Browser membuka mobile app dengan session active

### Bridge URL Format
```
/mobile-app/bridge.php?token={secure_token}&redirect={mobile_page}
```

## 🧪 Testing Integration

### Manual Test
1. Akses: `http://localhost/e-lapkin-mtsn11/mobile-app/integration_test.php`
2. Check semua status hijau (✅)

### Security Test
1. Akses: `http://localhost/e-lapkin-mtsn11/mobile-app/test.html`
2. Test User Agent validation

### Mobile App Test
1. Login di web aplikasi sebagai user
2. Check widget mobile di dashboard
3. Klik "Buka Mobile App"
4. Verify seamless transition

## 📱 Mobile App Features

### Dashboard Mobile
- Real-time user info
- LKH summary dari database
- RKB statistics dari database
- Recent activities dari database
- Quick action buttons

### Authentication
- Login dengan NIP/Password yang sama dengan web
- Session timeout 30 menit
- Device info tracking
- Access logging

### Data Sync
- LKH input tersimpan ke database utama
- RKB data diambil dari database utama
- Laporan sama dengan web aplikasi

## 🔄 Session Management

### Web Session
```php
$_SESSION['user_login'] = true;
$_SESSION['id_pegawai'] = $user_id;
$_SESSION['role'] = 'user';
// ... other web session data
```

### Mobile Session
```php
$_SESSION['mobile_loggedin'] = true;
$_SESSION['mobile_login_time'] = time();
// ... same user data as web + mobile-specific data
```

### Session Sharing
User bisa login di web dan mobile secara bersamaan tanpa conflict.

## 🚀 Production Deployment

### Requirements
1. Web server dengan PHP 7.4+
2. MySQL database yang sama
3. SSL certificate (HTTPS)
4. Mobile app dengan User Agent yang benar

### Configuration
1. Update base URL di mobile app
2. Set production mode di security config
3. Configure mobile token generation
4. Setup access logging

## 🔍 Monitoring & Logging

### Log Files
- `mobile-app/logs/mobile_access.log` - Semua akses mobile
- Error logs di PHP error log

### Log Format
```json
{
    "timestamp": "2025-07-07 10:30:00",
    "action": "login_success",
    "ip_address": "192.168.1.100",
    "user_agent": "E-LAPKIN-MTSN11-Mobile-App/1.0",
    "is_valid_app": true,
    "user_id": 123
}
```

## 📞 Support

Untuk bantuan teknis hubungi:
- **Website**: https://mtsn11majalengka.sch.id
- **Email**: mtsn11majalengka@gmail.com
- **Phone**: (0233) 8319182

---

**© 2025 MTsN 11 Majalengka. All rights reserved.**
