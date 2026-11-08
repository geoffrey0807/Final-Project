<?php
session_start();
require_once '../config/db.php';
require_once '../includes/email_policy.php';
require_once '../includes/portal_toast.php';

$dashboardRoutes = [
    'admin' => '../views/admin/admin_dashboard.php',
    'registrar' => '../views/registrar/registrar_dashboard.php',
    'professor' => '../views/professor/professor_dashboard.php',
    'dean' => '../views/dean/dean_dashboard.php',
    'student' => '../views/student/student_dashboard.php',
];

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($dashboardRoutes[$_SESSION['role'] ?? ''] ?? '../index.php'));
    exit;
}

$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = "Please sign in to access that workspace.";
    setPortalToast($error, 'error');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (empty($email) || empty($password)) {
        $error = "Please provide both your official email and password.";
    } elseif (!isValidApexEmail($email)) {
        $error = apexEmailPolicyMessage();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $validPassword = $user && password_verify($password, $user['password']);
        $legacyRegistrarPassword = $user
            && $user['role'] === 'registrar'
            && !$validPassword
            && hash_equals($user['password'], $password);

        $accountEnabled = $user && (int) $user['is_active'] === 1 && $user['archived_at'] === null;
        if (($validPassword || $legacyRegistrarPassword) && !$accountEnabled) {
            $error = "This account is disabled or archived. Contact an administrator for assistance.";
        } elseif ($validPassword || $legacyRegistrarPassword) {
            if ($legacyRegistrarPassword) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $upgradePassword = $pdo->prepare(
                    'UPDATE users SET password = :password WHERE id = :id AND password = :legacy_password'
                );
                $upgradePassword->execute([
                    'password' => $passwordHash,
                    'id' => $user['id'],
                    'legacy_password' => $user['password'],
                ]);
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_photo'] = (string) ($user['profile_photo'] ?? '');
            setPortalToast('Login successful.');

            header('Location: ' . ($dashboardRoutes[$user['role']] ?? '../index.php'));
            exit;
        } else {
            $error = "Invalid credentials. Please verify your email and password.";
        }
    }
    if ($error !== '') {
        setPortalToast($error, 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In • APEX Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css?v=202611041145">
    <link rel="stylesheet" href="../visual/css/visual.css?v=202611041145">
    <link rel="stylesheet" href="../css/auth.css">
    <link rel="stylesheet" href="../css/auth-portal.css">
    <link rel="stylesheet" href="../css/portal-toast.css">
    <style>
        .form-group {
            position: relative;
            display: block;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin: 0 0 0.45rem;
            color: #d98596;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .form-control {
            height: 48px;
            border-color: rgba(148, 163, 184, 0.55);
            background: rgba(255, 255, 255, 0.82);
            box-shadow: 0 5px 18px rgba(217, 133, 150, 0.035);
        }

        .form-control::placeholder {
            color: #64748b;
        }

        .form-control:focus,
        .form-control:not(:placeholder-shown) {
            background: rgba(255, 255, 255, 0.94);
        }

        .form-control:is(:autofill, :-webkit-autofill) {
            background: rgba(255, 255, 255, 0.94);
        }

        .form-control:focus {
            border-color: rgba(230, 161, 174, 0.58);
            box-shadow: 0 0 0 3px rgba(230, 161, 174, 0.10);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }


        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--teal);
        }


        .password-toggle .eye-open {
            display: none;
        }

    </style>
</head>

<body class="auth-page" data-visual-base="../visual/">
    <?php
    $visualBase = '../visual/';
    include __DIR__ . '/../visual/_partials/navigation.php';
    ?>

    <main class="auth-main">
        <div class="auth-container">

            <section class="auth-form-panel">
                <div class="auth-form-header">
                    <h1>Welcome!</h1>
                    <p>Sign in to continue.</p>
                </div>

                <form action="login.php" method="POST" id="loginForm" autocomplete="off">
                    <div class="form-group">
                        <label for="email">APEX Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                            placeholder="e.g. name@apex.edu.ph" pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph"
                            title="Use an APEX Email ending in @apex.edu.ph." value="" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="********" autocomplete="off" required style="padding-right: 2.75rem;">
                            <button type="button" class="password-toggle" id="togglePassword"
                                aria-label="Show password">

                                <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" height="24" width="24">
                                    <g id="visibility-off">
                                        <path id="Union" fill="currentColor"
                                            d="m21.2256 21.1748 -1.4248 1.4248 -3.7608 -3.7598c-1.1699 0.4077 -2.5109 0.6602 -4.04 0.6602 -4.0759 0 -6.82321 -1.7783 -8.5332 -3.5566 -0.8491 -0.8831 -1.44272 -1.7648 -1.8252 -2.4278 -0.19169 -0.3322 -0.33196 -0.6126 -0.42578 -0.8135 -0.04677 -0.1001 -0.08183 -0.181 -0.10644 -0.2392 -0.01233 -0.0291 -0.02228 -0.0529 -0.0293 -0.0703 -0.00351 -0.0087 -0.00658 -0.0159 -0.00879 -0.0215 -0.00105 -0.0027 -0.00217 -0.0049 -0.00293 -0.0068 -0.00037 -0.001 -0.00068 -0.0022 -0.00098 -0.003v-0.0009C1.06719 12.3598 1.06665 12.359 2 12c-0.93335 -0.359 -0.93281 -0.3598 -0.93262 -0.3604v-0.0009c0.0003 -0.0008 0.00061 -0.002 0.00098 -0.003 0.00076 -0.0019 0.00188 -0.0041 0.00293 -0.0068 0.00221 -0.0056 0.00528 -0.0128 0.00879 -0.0215 0.00702 -0.0174 0.01697 -0.0412 0.0293 -0.0703 0.02461 -0.0582 0.05967 -0.1391 0.10644 -0.2392 0.09382 -0.2009 0.23409 -0.4813 0.42578 -0.8135 0.38248 -0.66296 0.9761 -1.5447 1.8252 -2.42776 0.28 -0.29119 0.5886 -0.58134 0.92578 -0.86426L1.40039 4.2002 2.8252 2.77441zM5.81348 8.61328c-0.33358 0.27009 -0.63527 0.54927 -0.90528 0.83008 -0.71321 0.74174 -1.21356 1.48524 -1.53418 2.04104 -0.11565 0.2005 -0.20627 0.3757 -0.27539 0.5156 0.06912 0.1399 0.15974 0.3151 0.27539 0.5156 0.32062 0.5558 0.82097 1.2993 1.53418 2.041C6.32322 16.0283 8.57605 17.5 12 17.5c0.8861 0 1.6931 -0.1001 2.4277 -0.2725l-0.9707 -0.9716C13 16.4127 12.5104 16.5 12 16.5c-2.48528 0 -4.5 -2.0147 -4.5 -4.5 0 -0.5107 0.08538 -1.0015 0.24219 -1.459zM12 4.5c4.0759 0 6.8232 1.77832 8.5332 3.55664 0.8491 0.88306 1.4427 1.7648 1.8252 2.42776 0.1917 0.3322 0.332 0.6126 0.4258 0.8135 0.0468 0.1001 0.0818 0.181 0.1064 0.2392 0.0124 0.0291 0.0223 0.0529 0.0293 0.0703 0.0035 0.0087 0.0066 0.0159 0.0088 0.0215 0.0011 0.0027 0.0022 0.0049 0.0029 0.0068 0.0004 0.001 0.0007 0.0022 0.001 0.003v0.0009c0.0002 0.0006 0.0007 0.0014 -0.9326 0.3604 0.9333 0.359 0.9328 0.3598 0.9326 0.3604v0.0009c-0.0003 0.0008 -0.0006 0.002 -0.001 0.003 -0.0007 0.0019 -0.0018 0.0041 -0.0029 0.0068 -0.0022 0.0056 -0.0053 0.0128 -0.0088 0.0215 -0.007 0.0174 -0.0169 0.0412 -0.0293 0.0703 -0.0246 0.0582 -0.0596 0.1391 -0.1064 0.2392 -0.0938 0.2009 -0.2341 0.4813 -0.4258 0.8135 -0.3825 0.663 -0.9761 1.5447 -1.8252 2.4278 -0.2647 0.2752 -0.5566 0.5477 -0.8721 0.8164l-1.4179 -1.418c0.311 -0.2564 0.5938 -0.5202 0.8486 -0.7852 0.7132 -0.7417 1.2136 -1.4852 1.5342 -2.041 0.1155 -0.2003 0.2053 -0.3758 0.2744 -0.5156 -0.0691 -0.1398 -0.1589 -0.3153 -0.2744 -0.5156 -0.3206 -0.5558 -0.821 -1.2993 -1.5342 -2.04104C17.6768 7.97174 15.424 6.5 12 6.5c-0.8536 0 -1.6345 0.09085 -2.34766 0.25098L8.0332 5.13184C9.18601 4.74033 10.5033 4.5 12 4.5m0 3c2.4853 0 4.5 2.01472 4.5 4.5 0 0.4819 -0.0775 0.9456 -0.2178 1.3809l-5.664 -5.6641C11.0536 7.57642 11.5179 7.5 12 7.5M2 12l-0.93359 0.3594L0.928711 12l0.137699 -0.3594zm21.0713 0 -0.1377 0.3594L22 12l0.9336 -0.3594z"
                                            stroke-width="1"></path>
                                    </g>
                                </svg>

                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                    id="Eye--Streamline-Solar" height="24" width="24">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M2 12c0 1.6394 0.42496 2.1915 1.27489 3.2957C4.97196 17.5004 7.81811 20 12 20c4.1819 0 7.028 -2.4996 8.7251 -4.7043C21.575 14.1915 22 13.6394 22 12s-0.425 -2.19147 -1.2749 -3.29567C19.028 6.49956 16.1819 4 12 4 7.81811 4 4.97196 6.49956 3.27489 8.70433 2.42496 9.80853 2 10.3606 2 12Zm10 -3.75c-2.07107 0 -3.75 1.67893 -3.75 3.75 0 2.0711 1.67893 3.75 3.75 3.75 2.0711 0 3.75 -1.6789 3.75 -3.75 0 -2.07107 -1.6789 -3.75 -3.75 -3.75Z"
                                        fill="currentColor" stroke-width="1"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
                        Login
                    </button>
                </form>
            </section>
        </div>
    </main>

    <?php renderPortalToast(); ?>
    <script src="../js/portal-toast.js?v=202611041835"></script>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const isPassword = passwordInput.getAttribute('type') === 'password';

            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

            const eyeClosed = this.querySelector('.eye-closed');
            const eyeOpen = this.querySelector('.eye-open');

            if (isPassword) {
                eyeClosed.style.display = 'none';
                eyeOpen.style.display = 'block';
                this.style.color = 'var(--teal)';
                this.setAttribute('aria-label', 'Hide password');
            } else {
                eyeClosed.style.display = 'block';
                eyeOpen.style.display = 'none';
                this.style.color = 'var(--text-muted)';
                this.setAttribute('aria-label', 'Show password');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');

            const clearLoginFields = function () {
                loginForm.reset();
            };

            clearLoginFields();
            window.setTimeout(clearLoginFields, 50);
            window.setTimeout(clearLoginFields, 250);
            window.addEventListener('pageshow', function () {
                clearLoginFields();
                window.setTimeout(clearLoginFields, 50);
            });

        });
    </script>
    <?php include __DIR__ . '/../visual/_partials/foot.php'; ?>