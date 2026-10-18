<?php
session_start();

if (isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'Admin') {
        header('Location: html/admin/dashboard.php');
    } elseif ($role === 'CCMD') {
        header('Location: html/ccmd/dashboard.php');
    } else {
        header('Location: html/examinee/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>PNP Regional Training Center X - Web based Examination System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=3">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">
</head>

<body>
    <div class="container-fluid p-0 login-page">
        <div class="row g-0 h-100 min-vh-100">
            <div class="col-md-5 login-brand-panel d-flex flex-column justify-content-center align-items-center p-5 position-relative">
                <div class="text-center">
                    <div class="mb-4 login-badge-wrap">
                        <div class="login-badge-circle">
                            <img src="assets/images/pnp-badge.png" alt="PNP Badge" class="login-badge-img">
                        </div>
                    </div>
                    <h2 class="fw-bold text-white">PNP Regional Training Center X</h2>
                    <p class="text-white-50 mt-2">Web-Based Examination System</p>
                </div>
            </div>
            <div class="col-md-7 login-form-panel d-flex align-items-center justify-content-center">
                <div class="login-form-container p-5" style="max-width: 380px; width: 100%;">
                    <h5 class="mb-4 text-left text-dark fw-bold">LOGIN</h5>

                    <form id="loginForm">
                        <div class="mb-3">
                            <input type="text" class="form-control border-0 border-bottom" id="loginId" name="login_id" placeholder="Badge Number or Email" autocomplete="username" required style="border-radius: 0; border-bottom: 2px solid #dee2e6;">
                        </div>

                        <div class="mb-4">
                            <div class="input-group" style="border-bottom: 2px solid #dee2e6;">
                                <input type="password" class="form-control border-0" id="password" name="password" placeholder="Enter Password" required style="border-radius: 0;" autocomplete="current-password">
                                <button class="btn btn-outline-none border-0" id="togglePassword" type="button" style="background-color: transparent; color: #6c757d;" aria-label="Show password">
                                    <i class="bi bi-eye-slash-fill"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold" style="background-color: #1a3a52; border-color: #1a3a52;">LOGIN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/axios.min.js?v=1"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function(e) {
            e.preventDefault();
            const passwordInput = document.getElementById('password');
            const toggleIcon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye-slash-fill');
                toggleIcon.classList.add('bi-eye-fill');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-fill');
                toggleIcon.classList.add('bi-eye-slash-fill');
                this.setAttribute('aria-label', 'Show password');
            }
        });
    </script>

    <script src="js/auth/app.js?v=7"></script>
</body>

</html>