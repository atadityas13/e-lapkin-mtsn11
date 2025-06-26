<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Main Entry Point
 * Deskripsi: Halaman utama aplikasi - redirect ke login atau dashboard
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2025-01-01
 * @created    2025-06-25
 * @modified   2025-06-25
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * Address: Kp. Sindanghurip Desa Maniis Kec. Cingambul, Majalengka, Jawa Barat
 * 
 * ========================================================
 */
require_once 'database.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
    h3 { color: #007bff; margin-top: 30px; }
    h4 { color: #28a745; margin-top: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f8f9fa; font-weight: bold; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    pre { background-color: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .table-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
</style>";

echo "<h2>📊 Database Structure Analysis - E-Lapkin MTSN 11</h2>";

// Tampilkan informasi database
echo "<h3>🔍 Database Information:</h3>";
$db_info = $conn->query("SELECT DATABASE() as current_db, VERSION() as mysql_version");
$info = $db_info->fetch_assoc();
echo "<p><strong>Current Database:</strong> " . $info['current_db'] . "</p>";
echo "<p><strong>MySQL Version:</strong> " . $info['mysql_version'] . "</p>";

// Tampilkan semua tabel
echo "<h3>📋 All Tables in Database:</h3>";
$tables = $conn->query("SHOW TABLES");
$all_table_names = [];
if ($tables) {
    echo "<table>";
    echo "<tr><th>No</th><th>Table Name</th><th>Engine</th><th>Rows</th><th>Data Length</th></tr>";
    
    $table_info_query = $conn->query("
        SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME
    ");
    
    $no = 1;
    while ($table_info = $table_info_query->fetch_assoc()) {
        $all_table_names[] = $table_info['TABLE_NAME'];
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td><strong>" . $table_info['TABLE_NAME'] . "</strong></td>";
        echo "<td>" . ($table_info['ENGINE'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format($table_info['TABLE_ROWS'] ?? 0) . "</td>";
        echo "<td>" . number_format($table_info['DATA_LENGTH'] ?? 0) . " bytes</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ Tidak dapat mengambil daftar tabel</p>";
}

// Analisis setiap tabel
foreach ($all_table_names as $table_name) {
    echo "<div class='table-section'>";
    echo "<h3>🗂️ Tabel: " . strtoupper($table_name) . "</h3>";
    
    // Struktur tabel
    echo "<h4>📐 Table Structure:</h4>";
    $columns = $conn->query("SHOW COLUMNS FROM `$table_name`");
    if ($columns) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $columns->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . ($col['Null'] == 'YES' ? '✅' : '❌') . "</td>";
            echo "<td>" . ($col['Key'] ? '🔑 ' . $col['Key'] : '') . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . ($col['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Indexes
    echo "<h4>🔗 Indexes:</h4>";
    $indexes = $conn->query("SHOW INDEXES FROM `$table_name`");
    if ($indexes && $indexes->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th><th>Type</th></tr>";
        while ($index = $indexes->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $index['Key_name'] . "</td>";
            echo "<td>" . $index['Column_name'] . "</td>";
            echo "<td>" . ($index['Non_unique'] == 0 ? '✅ Unique' : '❌ Non-unique') . "</td>";
            echo "<td>" . ($index['Index_type'] ?? 'BTREE') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No indexes found</p>";
    }
    
    // Sample data
    echo "<h4>📊 Sample Data (Max 5 rows):</h4>";
    $sample_data = $conn->query("SELECT * FROM `$table_name` LIMIT 5");
    if ($sample_data && $sample_data->num_rows > 0) {
        echo "<table>";
        echo "<tr>";
        $fields = $sample_data->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr>";
        
        $sample_data->data_seek(0);
        while ($row = $sample_data->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                if (is_null($value)) {
                    echo "<td><em>NULL</em></td>";
                } else {
                    echo "<td>" . htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : '') . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><em>🚫 No data in this table</em></p>";
    }
    
    // Record count
    $count_result = $conn->query("SELECT COUNT(*) as total FROM `$table_name`");
    if ($count_result) {
        $count = $count_result->fetch_assoc()['total'];
        echo "<p><strong>📈 Total Records:</strong> " . number_format($count) . "</p>";
    }
    
    echo "</div>";
}

// Cek foreign keys
echo "<h3>🔗 Foreign Key Relationships:</h3>";
$fk_query = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fk_query && $fk_query->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Table</th><th>Column</th><th>References</th><th>Constraint Name</th></tr>";
    while ($fk = $fk_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $fk['TABLE_NAME'] . "</td>";
        echo "<td>" . $fk['COLUMN_NAME'] . "</td>";
        echo "<td>" . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "<td>" . $fk['CONSTRAINT_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><em>No foreign key relationships found</em></p>";
}

// Database size
echo "<h3>💾 Database Size Information:</h3>";
$size_query = $conn->query("
    SELECT 
        TABLE_NAME,
        ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB'
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
");

if ($size_query) {
    echo "<table>";
    echo "<tr><th>Table</th><th>Size (MB)</th></tr>";
    $total_size = 0;
    while ($size = $size_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $size['TABLE_NAME'] . "</td>";
        echo "<td>" . $size['Size_MB'] . "</td>";
        echo "</tr>";
        $total_size += $size['Size_MB'];
    }
    echo "<tr><td><strong>TOTAL</strong></td><td><strong>" . round($total_size, 2) . " MB</strong></td></tr>";
    echo "</table>";
}

echo "<hr>";
echo "<p><em>Generated on: " . date('Y-m-d H:i:s') . "</em></p>";
?>
