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

// Set header untuk download file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="e-lapkin-structure-only-' . date('Y-m-d-H-i-s') . '.sql"');
header('Cache-Control: no-cache, must-revalidate');

echo "-- ========================================================\n";
echo "-- Database Structure Export: E-Lapkin MTSN 11\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- MySQL Version: " . $conn->server_info . "\n";
echo "-- Structure Only (No Data)\n";
echo "-- ========================================================\n\n";

// Get database name
$db_info = $conn->query("SELECT DATABASE() as current_db");
$current_db = $db_info->fetch_assoc()['current_db'];
echo "-- Database: " . $current_db . "\n\n";

// Disable foreign key checks
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

// Get all tables
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($table = $tables_result->fetch_array()) {
    $tables[] = $table[0];
}

// Export structure for each table
foreach ($tables as $table_name) {
    echo "-- ========================================================\n";
    echo "-- Table structure for table `$table_name`\n";
    echo "-- ========================================================\n\n";
    
    // Drop table if exists
    echo "DROP TABLE IF EXISTS `$table_name`;\n";
    
    // Get table structure
    $create_table = $conn->query("SHOW CREATE TABLE `$table_name`");
    $table_structure = $create_table->fetch_assoc();
    echo $table_structure['Create Table'] . ";\n\n";
    
    // Show column details
    echo "-- Column Details:\n";
    $columns_result = $conn->query("SHOW FULL COLUMNS FROM `$table_name`");
    while ($col = $columns_result->fetch_assoc()) {
        echo "-- " . $col['Field'] . " (" . $col['Type'] . ") ";
        echo ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . " ";
        echo ($col['Key'] ? $col['Key'] . " " : "");
        echo ($col['Default'] ? "DEFAULT '" . $col['Default'] . "' " : "");
        echo ($col['Extra'] ? $col['Extra'] . " " : "");
        echo ($col['Comment'] ? "COMMENT '" . $col['Comment'] . "'" : "");
        echo "\n";
    }
    echo "\n";
    
    // Show indexes
    echo "-- Indexes:\n";
    $indexes_result = $conn->query("SHOW INDEXES FROM `$table_name`");
    while ($index = $indexes_result->fetch_assoc()) {
        echo "-- " . $index['Key_name'] . " (" . $index['Column_name'] . ") ";
        echo ($index['Non_unique'] == 0 ? 'UNIQUE' : 'NON-UNIQUE') . " ";
        echo $index['Index_type'] . "\n";
    }
    echo "\n";
}

// Re-enable foreign key checks
echo "SET FOREIGN_KEY_CHECKS = 1;\n\n";

echo "-- ========================================================\n";
echo "-- End of structure export\n";
echo "-- ========================================================\n";
?>
