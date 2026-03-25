<?php
declare(strict_types=1);
session_start();

// If already logged in, route by role.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/admin_dashboard.php');
        exit();
    }

    header('Location: user/user_dashboard.php');
    exit();
}

// Otherwise go to login.
header('Location: auth/login.php');
exit();

