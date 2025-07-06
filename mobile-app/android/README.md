# E-LAPKIN Mobile Android App Configuration

## User Agent Configuration
Untuk mengakses aplikasi mobile E-LAPKIN, aplikasi Android WebView harus menggunakan User Agent khusus:

```
User-Agent: E-LAPKIN-MTSN11-Mobile-App/1.0
```

## Required Headers
Aplikasi Android harus mengirim header tambahan:

```
X-Mobile-Token: [MD5 hash dari MTSN11-MOBILE-KEY-2025 + tanggal saat ini (Y-m-d)]
X-App-Package: id.sch.mtsn11majalengka.elapkin
```

## Android WebView Implementation Example

### MainActivity.java
```java
package id.sch.mtsn11majalengka.elapkin;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import java.security.MessageDigest;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;

public class MainActivity extends Activity {
    private WebView webView;
    private static final String BASE_URL = "https://elapkin.mtsn11majalengka.sch.id/mobile-app/";
    private static final String SECRET_KEY = "MTSN11-MOBILE-KEY-2025";
    private static final String PACKAGE_NAME = "id.sch.mtsn11majalengka.elapkin";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        
        // Configure WebView
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        
        // Set custom User Agent
        String userAgent = "E-LAPKIN-MTSN11-Mobile-App/1.0";
        webSettings.setUserAgentString(userAgent);
        
        // Set WebView client with custom headers
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (url.startsWith(BASE_URL)) {
                    Map<String, String> headers = new HashMap<>();
                    headers.put("X-Mobile-Token", generateMobileToken());
                    headers.put("X-App-Package", PACKAGE_NAME);
                    view.loadUrl(url, headers);
                    return true;
                }
                return false;
            }
        });
        
        // Load initial URL with headers
        Map<String, String> headers = new HashMap<>();
        headers.put("X-Mobile-Token", generateMobileToken());
        headers.put("X-App-Package", PACKAGE_NAME);
        
        webView.loadUrl(BASE_URL, headers);
    }
    
    private String generateMobileToken() {
        try {
            SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd");
            String currentDate = sdf.format(new Date());
            String input = SECRET_KEY + currentDate;
            
            MessageDigest md = MessageDigest.getInstance("MD5");
            byte[] messageDigest = md.digest(input.getBytes());
            
            StringBuilder hexString = new StringBuilder();
            for (byte b : messageDigest) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (Exception e) {
            return "";
        }
    }
    
    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
```

### activity_main.xml
```xml
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:orientation="vertical">

    <WebView
        android:id="@+id/webview"
        android:layout_width="match_parent"
        android:layout_height="match_parent" />

</LinearLayout>
```

### AndroidManifest.xml
```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="id.sch.mtsn11majalengka.elapkin">

    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <application
        android:allowBackup="true"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:theme="@style/AppTheme"
        android:usesCleartextTraffic="true">
        
        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:screenOrientation="portrait">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>
    </application>
</manifest>
```

## Development Mode
Untuk testing di localhost, cukup gunakan User Agent yang benar:
```
E-LAPKIN-MTSN11-Mobile-App/1.0
```

## Production Mode
Untuk production, semua validasi harus terpenuhi:
1. User Agent yang benar
2. Mobile Token yang valid (MD5 hash)
3. Package Name yang benar

## Security Features
1. **User Agent Validation**: Memastikan akses hanya dari aplikasi resmi
2. **Token Generation**: Token yang berubah setiap hari berdasarkan tanggal
3. **Package Validation**: Memverifikasi package name aplikasi
4. **Session Management**: Session terpisah dari web versi
5. **Role Restriction**: Hanya user biasa yang bisa akses, admin tidak bisa
6. **Access Logging**: Semua akses tercatat untuk monitoring

## URL Structure
- Base URL: `/mobile-app/`
- Login: `/mobile-app/auth/mobile_login.php`
- Dashboard: `/mobile-app/user/dashboard.php`
- Logout: `/mobile-app/auth/mobile_logout.php`

## Testing
Untuk test di browser desktop (development):
1. Buka Developer Tools (F12)
2. Buka Console
3. Jalankan:
```javascript
// Set user agent untuk development
Object.defineProperty(navigator, 'userAgent', {
  get: function () { return 'E-LAPKIN-MTSN11-Mobile-App/1.0'; }
});
```
4. Akses: `http://localhost/mobile-app/`

## Error Codes
- `MOBILE_ONLY_ACCESS`: Akses ditolak, bukan dari aplikasi mobile
- `INVALID_METHOD`: Method request tidak valid
- `INVALID_APP_REQUEST`: Request bukan dari aplikasi mobile
- `EMPTY_CREDENTIALS`: NIP/Password kosong
- `INVALID_NIP_FORMAT`: Format NIP tidak valid
- `INVALID_PASSWORD_FORMAT`: Format password tidak valid
- `USER_NOT_FOUND`: User tidak ditemukan atau belum disetujui
- `WRONG_PASSWORD`: Password salah
- `SESSION_EXPIRED`: Session timeout
- `INVALID_ROLE`: Role tidak valid untuk mobile app
