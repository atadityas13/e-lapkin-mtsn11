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
header('Content-Disposition: attachment; filename="e-lapkin-database-export-' . date('Y-m-d-H-i-s') . '.sql"');
header('Cache-Control: no-cache, must-revalidate');

echo "-- ========================================================\n";
echo "-- Database Export: E-Lapkin MTSN 11\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- MySQL Version: " . $conn->server_info . "\n";
echo "-- ========================================================\n\n";

// Get database name
$db_info = $conn->query("SELECT DATABASE() as current_db");
$current_db = $db_info->fetch_assoc()['current_db'];
echo "-- Database: " . $current_db . "\n\n";

// Disable foreign key checks
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n\n";

// Get all tables
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($table = $tables_result->fetch_array()) {
    $tables[] = $table[0];
}

// Export structure and data for each table
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
    
    echo "-- ========================================================\n";
    echo "-- Dumping data for table `$table_name`\n";
    echo "-- ========================================================\n\n";
    
    // Get table data
    $data_result = $conn->query("SELECT * FROM `$table_name`");
    
    if ($data_result->num_rows > 0) {
        // Get column info
        $columns_result = $conn->query("SHOW COLUMNS FROM `$table_name`");
        $columns = [];
        while ($col = $columns_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        $column_list = "`" . implode("`, `", $columns) . "`";
        
        echo "INSERT INTO `$table_name` ($column_list) VALUES\n";
        
        $first_row = true;
        while ($row = $data_result->fetch_assoc()) {
            if (!$first_row) {
                echo ",\n";
            }
            
            $values = [];
            foreach ($row as $value) {
                if (is_null($value)) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $conn->real_escape_string($value) . "'";
                }
            }
            
            echo "(" . implode(", ", $values) . ")";
            $first_row = false;
        }
        echo ";\n\n";
    } else {
        echo "-- No data found in table `$table_name`\n\n";
    }
}

// Re-enable foreign key checks
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "COMMIT;\n\n";

echo "-- ========================================================\n";
echo "-- End of export\n";
echo "-- ========================================================\n";
?>
