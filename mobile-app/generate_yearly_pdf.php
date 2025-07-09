<?php
session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For TCPDF or similar

// Check mobile login
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

// Get year parameter
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get employee information
$stmt_emp = $conn->prepare("SELECT nip, jabatan, unit_kerja, nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
$stmt_emp->bind_param("i", $id_pegawai_login);
$stmt_emp->execute();
$emp_data = $stmt_emp->get_result()->fetch_assoc();
$stmt_emp->close();

// Start output buffering to capture the yearly report content
ob_start();
$_GET['year'] = $year; // Set year for the include
include 'generate_yearly_report.php';
$yearly_content = ob_get_clean();

// Create PDF using TCPDF
class YearlyReportPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'LAPORAN KINERJA TAHUNAN', 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Create new PDF document
$pdf = new YearlyReportPDF('L', 'mm', 'A4'); // Landscape orientation for better table display
$pdf->SetCreator('E-LAPKIN Mobile');
$pdf->SetAuthor($nama_pegawai_login);
$pdf->SetTitle('Laporan Kinerja Tahunan ' . $year);

// Add a page
$pdf->AddPage();

// Convert HTML content to PDF
$pdf->writeHTML($yearly_content, true, false, true, false, '');

// Generate filename
$nip_pegawai = preg_replace('/[^A-Za-z0-9_\-]/', '_', $emp_data['nip']);
$filename = "Laporan_Tahunan_{$year}_{$nip_pegawai}.pdf";

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output PDF
$pdf->Output($filename, 'D');
exit;
?>
