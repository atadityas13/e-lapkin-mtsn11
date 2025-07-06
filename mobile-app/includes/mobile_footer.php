    <!-- Bottom Navigation -->
    <nav class="navbar fixed-bottom navbar-light bg-white border-top shadow-sm">
        <div class="container-fluid">
            <div class="row w-100 text-center">
                <div class="col">
                    <a href="dashboard.php" class="nav-link p-2 <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-home fa-lg d-block"></i>
                        <small>Home</small>
                    </a>
                </div>
                <div class="col">
                    <a href="rhk.php" class="nav-link p-2 <?= basename($_SERVER['PHP_SELF']) === 'rhk.php' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-tasks fa-lg d-block"></i>
                        <small>RHK</small>
                    </a>
                </div>
                <div class="col">
                    <a href="rkb.php" class="nav-link p-2 <?= basename($_SERVER['PHP_SELF']) === 'rkb.php' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-calendar-alt fa-lg d-block"></i>
                        <small>RKB</small>
                    </a>
                </div>
                <div class="col">
                    <a href="lkh.php" class="nav-link p-2 <?= basename($_SERVER['PHP_SELF']) === 'lkh.php' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-book fa-lg d-block"></i>
                        <small>LKH</small>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Add bottom padding to prevent content being hidden behind bottom nav -->
    <div style="height: 80px;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/mobile.js"></script>
</body>
</html>
