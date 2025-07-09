<?php
session_start();
session_destroy();

// Clear mobile session data directly without validation
unset($_SESSION['mobile_loggedin']);
unset($_SESSION['mobile_id_pegawai']);
unset($_SESSION['mobile_nip']);
unset($_SESSION['mobile_nama']);
unset($_SESSION['mobile_jabatan']);
unset($_SESSION['mobile_unit_kerja']);
unset($_SESSION['mobile_role']);

// Redirect to login page
header("Location: index.php");
exit();
?>
