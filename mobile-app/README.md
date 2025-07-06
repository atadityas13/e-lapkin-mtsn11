# E-LAPKIN MOBILE APP

Aplikasi Mobile berbasis WebView untuk E-LAPKIN MTsN 11 Majalengka - Sistem Elektronik Laporan Kinerja Harian.

## 🔐 Keamanan Akses

Folder `mobile-app` ini **HANYA** dapat diakses melalui:
- Aplikasi Android E-LAPKIN Mobile dengan User Agent khusus: `E-LAPKIN-MTSN11-Mobile-App/1.0`
- Development environment (localhost/127.0.0.1/192.168.x.x)

Akses dari browser biasa akan **DIBLOKIR** dengan response 403 Forbidden.

## 📁 Struktur Folder

```
mobile-app/
├── index.php                          # Entry point login mobile
├── config/
│   ├── mobile_security.php           # Keamanan & validasi user agent
│   └── mobile_database.php           # Database functions khusus mobile
├── auth/
│   ├── mobile_login.php              # Handler login mobile
│   └── mobile_logout.php             # Handler logout mobile
├── template/
│   ├── session_mobile.php            # Session management mobile
│   ├── header_mobile.php             # Header template mobile
│   └── navigation_mobile.php         # Navigation mobile
├── user/
│   ├── dashboard.php                 # Dashboard mobile
│   ├── rkb.php                       # RKB mobile (akan dibuat)
│   ├── rhk.php                       # RHK mobile (akan dibuat)
│   ├── lkh.php                       # LKH mobile (akan dibuat)
│   ├── laporan.php                   # Laporan mobile (akan dibuat)
│   ├── profil.php                    # Profil mobile (akan dibuat)
│   ├── generate_lkb.php              # Generate LKB mobile (akan dibuat)
│   └── generate_lkh.php              # Generate LKH mobile (akan dibuat)
├── assets/
│   ├── css/
│   │   └── mobile.css                # Styles khusus mobile/WebView
│   └── js/
│       └── mobile.js                 # JavaScript khusus mobile
└── android/                          # Project Android Studio (akan dibuat)
```

## 🛡️ Fitur Keamanan

### 1. User Agent Validation
- Memvalidasi User Agent khusus aplikasi: `E-LAPKIN-MTSN11-Mobile-App/1.0`
- Blokir akses dari browser desktop/mobile biasa
- Izinkan akses development (localhost)

### 2. Session Management
- Session terpisah dari web version (`mobile_loggedin`)
- Session timeout 24 jam
- Device info tracking

### 3. Admin Block
- Admin **TIDAK BISA** login di mobile app
- Hanya user biasa (`role = 'user'`) yang diizinkan

### 4. Logging
- Log semua akses mobile di `logs/mobile_access.log`
- Track login attempts, success, failures
- Monitor device info dan IP address

## 📱 Fitur Mobile

### 1. Responsive Design
- Optimized untuk layar mobile
- Touch-friendly interface
- Bottom navigation untuk mobile
- Sidebar navigation untuk tablet/desktop

### 2. Mobile-First UX
- Pull-to-refresh
- Swipe gestures
- Touch feedback (ripple effects)
- Loading states
- Offline indicators

### 3. Progressive Web App Features
- Add to home screen
- App-like experience
- Caching strategies
- Background sync (future)

## 🔧 Konfigurasi

### Environment Variables
```php
// config/mobile_security.php
define('MOBILE_APP_USER_AGENT', 'E-LAPKIN-MTSN11-Mobile-App/1.0');
define('MOBILE_APP_VERSION', '1.0.0');
define('MOBILE_APP_NAME', 'E-LAPKIN Mobile');
```

### Database Functions
File `config/mobile_database.php` menyediakan:
- `getMobilePegawaiData($nip)` - Data pegawai optimized
- `getMobileLKHSummary($id_pegawai, $month, $year)` - Ringkasan LKH
- `getMobileRKBData($id_pegawai, $year)` - Data RKB
- `sendMobileResponse($data, $status, $message, $http_code)` - API response helper

## 🚀 Getting Started

### 1. Setup Server
Pastikan folder `mobile-app` sudah di-upload ke server web:
```
http://yourdomain.com/mobile-app/
```

### 2. Test Security
Coba akses dari browser biasa, harusnya dapat response:
```json
{
    "error": "Access Denied",
    "message": "Halaman ini hanya dapat diakses melalui Aplikasi Mobile E-LAPKIN MTSN 11 Majalengka",
    "code": "MOBILE_ONLY_ACCESS"
}
```

### 3. Development Access
Untuk testing di localhost, akses langsung akan diizinkan:
```
http://localhost/e-lapkin-mtsn11/mobile-app/
```

### 4. Create Android App
Buat aplikasi Android WebView dengan User Agent:
```java
webView.getSettings().setUserAgentString("E-LAPKIN-MTSN11-Mobile-App/1.0");
webView.loadUrl("https://yourdomain.com/mobile-app/");
```

## 📝 API Responses

Semua response mobile menggunakan format JSON konsisten:

### Success Response
```json
{
    "status": "success",
    "message": "Login berhasil",
    "data": {
        "user": {...},
        "session": {...}
    },
    "timestamp": "2025-07-07 10:30:00",
    "app_version": "1.0.0"
}
```

### Error Response
```json
{
    "status": "error", 
    "message": "NIP tidak ditemukan",
    "data": null,
    "timestamp": "2025-07-07 10:30:00",
    "app_version": "1.0.0"
}
```

## 🎨 Mobile UI Components

### 1. Navigation
- **Bottom Navigation**: Mobile (< 992px)
- **Sidebar Navigation**: Desktop/Tablet (≥ 992px)

### 2. Cards
- `mobile-card`: Container utama
- `stat-card`: Kartu statistik dengan animasi hover
- `login-card`: Kartu login dengan backdrop blur

### 3. Buttons
- `btn-mobile`: Button dengan animasi dan shadow
- Touch ripple effects
- Loading states

### 4. Alerts & Modals
- SweetAlert2 integration
- Toast notifications
- Confirm dialogs
- Loading overlays

## 📊 Performance

### 1. Optimizations
- Critical CSS inlined
- Font preloading
- Image optimization
- JavaScript lazy loading

### 2. Caching
- Service worker (future)
- LocalStorage untuk preferences
- Session storage untuk temporary data

### 3. Bundle Size
- Minimal dependencies
- CDN untuk libraries
- Compressed assets

## 🔍 Monitoring & Logging

### 1. Access Logs
```
logs/mobile_access.log
```

### 2. Log Format
```json
{
    "timestamp": "2025-07-07 10:30:00",
    "action": "login_success",
    "ip_address": "192.168.1.100", 
    "user_agent": "E-LAPKIN-MTSN11-Mobile-App/1.0",
    "is_valid_app": true,
    "url": "/mobile-app/auth/mobile_login.php"
}
```

### 3. Monitoring Actions
- `index_access` - Akses halaman utama
- `login_attempt` - Percobaan login
- `login_success` - Login berhasil
- `login_failed_*` - Login gagal dengan alasan
- `logout_*` - Logout
- `dashboard_access` - Akses dashboard
- `session_check_*` - Validasi session

## 🔮 Future Development

### 1. Next Features
- [ ] Push notifications
- [ ] Offline mode
- [ ] Background sync
- [ ] File upload optimization
- [ ] Biometric authentication

### 2. Android App Features
- [ ] Fingerprint login
- [ ] Face ID login
- [ ] Device registration
- [ ] Auto-update mechanism

### 3. Performance Improvements
- [ ] Service Worker
- [ ] App Shell architecture
- [ ] Background fetch
- [ ] Image lazy loading

## 📞 Support

Untuk bantuan teknis hubungi:
- **Website**: https://mtsn11majalengka.sch.id
- **Email**: mtsn11majalengka@gmail.com
- **Phone**: (0233) 8319182

---

**© 2025 MTsN 11 Majalengka. All rights reserved.**
