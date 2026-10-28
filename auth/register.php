<?php
session_start();
require_once '../config/db.php';
require_once '../includes/password_policy.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $password        = is_string($password) ? $password : '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid institutional email address.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!isValidPassword($password)) {
        $error = passwordPolicyMessage();
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $error = "An account with this email address already exists. Please sign in.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role) 
                VALUES (:fn, :ln, :email, :pass, 'student')
            ");
            $insert->execute([
                'fn'      => $firstName,
                'ln'      => $lastName,
                'email'   => $email,
                'pass'    => $hashedPassword
            ]);

            header("Location: login.php?registered=success");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up • APEX Learning Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
    <style>
        
        .auth-hero { display: none; }
        .auth-container { grid-template-columns: 1fr; }
        .auth-form-panel { padding: 2rem; }
        
        .password-toggle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 2.5rem;
            margin: auto 0.5rem auto auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1;
        }
        .password-toggle:hover { color: var(--teal); }
        .form-group { position: relative; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        
        <section class="auth-form-panel">
            <div class="auth-form-header">
                <h1>Sign Up</h1>
                <p>Create your account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" id="registerForm" autocomplete="on">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-control" 
                            placeholder="e.g. John" 
                             value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-control" 
                            placeholder="e.g. Doe" 
                             value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="email@apex.edu.ph" 
                             value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="8+ characters, upper/lowercase, number, symbol"
                            minlength="8"
                            maxlength="72"
                            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                            title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                            autocomplete="new-password"
                            required
                        >
                         <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                             
                             <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="24" width="24">
                                 <g id="visibility-off">
                                     <path id="Union" fill="currentColor" d="m21.2256 21.1748 -1.4248 1.4248 -3.7608 -3.7598c-1.1699 0.4077 -2.5109 0.6602 -4.04 0.6602 -4.0759 0 -6.82321 -1.7783 -8.5332 -3.5566 -0.8491 -0.8831 -1.44272 -1.7648 -1.8252 -2.4278 -0.19169 -0.3322 -0.33196 -0.6126 -0.42578 -0.8135 -0.04677 -0.1001 -0.08183 -0.181 -0.10644 -0.2392 -0.01233 -0.0291 -0.02228 -0.0529 -0.0293 -0.0703 -0.00351 -0.0087 -0.00658 -0.0159 -0.00879 -0.0215 -0.00105 -0.0027 -0.00217 -0.0049 -0.00293 -0.0068 -0.00037 -0.001 -0.00068 -0.0022 -0.00098 -0.003v-0.0009C1.06719 12.3598 1.06665 12.359 2 12c-0.93335 -0.359 -0.93281 -0.3598 -0.93262 -0.3604v-0.0009c0.0003 -0.0008 0.00061 -0.002 0.00098 -0.003 0.00076 -0.0019 0.00188 -0.0041 0.00293 -0.0068 0.00221 -0.0056 0.00528 -0.0128 0.00879 -0.0215 0.00702 -0.0174 0.01697 -0.0412 0.0293 -0.0703 0.02461 -0.0582 0.05967 -0.1391 0.10644 -0.2392 0.09382 -0.2009 0.23409 -0.4813 0.42578 -0.8135 0.38248 -0.66296 0.9761 -1.5447 1.8252 -2.42776 0.28 -0.29119 0.5886 -0.58134 0.92578 -0.86426L1.40039 4.2002 2.8252 2.77441zM5.81348 8.61328c-0.33358 0.27009 -0.63527 0.54927 -0.90528 0.83008 -0.71321 0.74174 -1.21356 1.48524 -1.53418 2.04104 -0.11565 0.2005 -0.20627 0.3757 -0.27539 0.5156 0.06912 0.1399 0.15974 0.3151 0.27539 0.5156 0.32062 0.5558 0.82097 1.2993 1.53418 2.041C6.32322 16.0283 8.57605 17.5 12 17.5c0.8861 0 1.6931 -0.1001 2.4277 -0.2725l-0.9707 -0.9716C13 16.4127 12.5104 16.5 12 16.5c-2.48528 0 -4.5 -2.0147 -4.5 -4.5 0 -0.5107 0.08538 -1.0015 0.24219 -1.459zM12 4.5c4.0759 0 6.8232 1.77832 8.5332 3.55664 0.8491 0.88306 1.4427 1.7648 1.8252 2.42776 0.1917 0.3322 0.332 0.6126 0.4258 0.8135 0.0468 0.1001 0.0818 0.181 0.1064 0.2392 0.0124 0.0291 0.0223 0.0529 0.0293 0.0703 0.0035 0.0087 0.0066 0.0159 0.0088 0.0215 0.0011 0.0027 0.0022 0.0049 0.0029 0.0068 0.0004 0.001 0.0007 0.0022 0.001 0.003v0.0009c0.0002 0.0006 0.0007 0.0014 -0.9326 0.3604 0.9333 0.359 0.9328 0.3598 0.9326 0.3604v0.0009c-0.0003 0.0008 -0.0006 0.002 -0.001 0.003 -0.0007 0.0019 -0.0018 0.0041 -0.0029 0.0068 -0.0022 0.0056 -0.0053 0.0128 -0.0088 0.0215 -0.007 0.0174 -0.0169 0.0412 -0.0293 0.0703 -0.0246 0.0582 -0.0596 0.1391 -0.1064 0.2392 -0.0938 0.2009 -0.2341 0.4813 -0.4258 0.8135 -0.3825 0.663 -0.9761 1.5447 -1.8252 2.4278 -0.2647 0.2752 -0.5566 0.5477 -0.8721 0.8164l-1.4179 -1.418c0.311 -0.2564 0.5938 -0.5202 0.8486 -0.7852 0.7132 -0.7417 1.2136 -1.4852 1.5342 -2.041 0.1155 -0.2003 0.2053 -0.3758 0.2744 -0.5156 -0.0691 -0.1398 -0.1589 -0.3153 -0.2744 -0.5156 -0.3206 -0.5558 -0.821 -1.2993 -1.5342 -2.04104C17.6768 7.97174 15.424 6.5 12 6.5c-0.8536 0 -1.6345 0.09085 -2.34766 0.25098L8.0332 5.13184C9.18601 4.74033 10.5033 4.5 12 4.5m0 3c2.4853 0 4.5 2.01472 4.5 4.5 0 0.4819 -0.0775 0.9456 -0.2178 1.3809l-5.664 -5.6641C11.0536 7.57642 11.5179 7.5 12 7.5M2 12l-0.93359 0.3594L0.928711 12l0.137699 -0.3594zm21.0713 0 -0.1377 0.3594L22 12l0.9336 -0.3594z" stroke-width="1"></path>
                                 </g>
                             </svg>
                             
                             <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" id="Eye--Streamline-Solar" height="24" width="24">
  <desc>
    Eye Streamline Icon: https://streamlinehq.com
  </desc>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M2 12c0 1.6394 0.42496 2.1915 1.27489 3.2957C4.97196 17.5004 7.81811 20 12 20c4.1819 0 7.028 -2.4996 8.7251 -4.7043C21.575 14.1915 22 13.6394 22 12s-0.425 -2.19147 -1.2749 -3.29567C19.028 6.49956 16.1819 4 12 4 7.81811 4 4.97196 6.49956 3.27489 8.70433 2.42496 9.80853 2 10.3606 2 12Zm10 -3.75c-2.07107 0 -3.75 1.67893 -3.75 3.75 0 2.0711 1.67893 3.75 3.75 3.75 2.0711 0 3.75 -1.6789 3.75 -3.75 0 -2.07107 -1.6789 -3.75 -3.75 -3.75Z" fill="currentColor" stroke-width="1"></path>
</svg>
                         </button>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            placeholder="Confirm your password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required
                        >
                         <button type="button" class="password-toggle" id="toggleConfirmPassword" aria-label="Show password">
                             
                             <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="24" width="24">
                                 <g id="visibility-off">
                                     <path id="Union" fill="currentColor" d="m21.2256 21.1748 -1.4248 1.4248 -3.7608 -3.7598c-1.1699 0.4077 -2.5109 0.6602 -4.04 0.6602 -4.0759 0 -6.82321 -1.7783 -8.5332 -3.5566 -0.8491 -0.8831 -1.44272 -1.7648 -1.8252 -2.4278 -0.19169 -0.3322 -0.33196 -0.6126 -0.42578 -0.8135 -0.04677 -0.1001 -0.08183 -0.181 -0.10644 -0.2392 -0.01233 -0.0291 -0.02228 -0.0529 -0.0293 -0.0703 -0.00351 -0.0087 -0.00658 -0.0159 -0.00879 -0.0215 -0.00105 -0.0027 -0.00217 -0.0049 -0.00293 -0.0068 -0.00037 -0.001 -0.00068 -0.0022 -0.00098 -0.003v-0.0009C1.06719 12.3598 1.06665 12.359 2 12c-0.93335 -0.359 -0.93281 -0.3598 -0.93262 -0.3604v-0.0009c0.0003 -0.0008 0.00061 -0.002 0.00098 -0.003 0.00076 -0.0019 0.00188 -0.0041 0.00293 -0.0068 0.00221 -0.0056 0.00528 -0.0128 0.00879 -0.0215 0.00702 -0.0174 0.01697 -0.0412 0.0293 -0.0703 0.02461 -0.0582 0.05967 -0.1391 0.10644 -0.2392 0.09382 -0.2009 0.23409 -0.4813 0.42578 -0.8135 0.38248 -0.66296 0.9761 -1.5447 1.8252 -2.42776 0.28 -0.29119 0.5886 -0.58134 0.92578 -0.86426L1.40039 4.2002 2.8252 2.77441zM5.81348 8.61328c-0.33358 0.27009 -0.63527 0.54927 -0.90528 0.83008 -0.71321 0.74174 -1.21356 1.48524 -1.53418 2.04104 -0.11565 0.2005 -0.20627 0.3757 -0.27539 0.5156 0.06912 0.1399 0.15974 0.3151 0.27539 0.5156 0.32062 0.5558 0.82097 1.2993 1.53418 2.041C6.32322 16.0283 8.57605 17.5 12 17.5c0.8861 0 1.6931 -0.1001 2.4277 -0.2725l-0.9707 -0.9716C13 16.4127 12.5104 16.5 12 16.5c-2.48528 0 -4.5 -2.0147 -4.5 -4.5 0 -0.5107 0.08538 -1.0015 0.24219 -1.459zM12 4.5c4.0759 0 6.8232 1.77832 8.5332 3.55664 0.8491 0.88306 1.4427 1.7648 1.8252 2.42776 0.1917 0.3322 0.332 0.6126 0.4258 0.8135 0.0468 0.1001 0.0818 0.181 0.1064 0.2392 0.0124 0.0291 0.0223 0.0529 0.0293 0.0703 0.0035 0.0087 0.0066 0.0159 0.0088 0.0215 0.0011 0.0027 0.0022 0.0049 0.0029 0.0068 0.0004 0.001 0.0007 0.0022 0.001 0.003v0.0009c0.0002 0.0006 0.0007 0.0014 -0.9326 0.3604 0.9333 0.359 0.9328 0.3598 0.9326 0.3604v0.0009c-0.0003 0.0008 -0.0006 0.002 -0.001 0.003 -0.0007 0.0019 -0.0018 0.0041 -0.0029 0.0068 -0.0022 0.0056 -0.0053 0.0128 -0.0088 0.0215 -0.007 0.0174 -0.0169 0.0412 -0.0293 0.0703 -0.0246 0.0582 -0.0596 0.1391 -0.1064 0.2392 -0.0938 0.2009 -0.2341 0.4813 -0.4258 0.8135 -0.3825 0.663 -0.9761 1.5447 -1.8252 2.4278 -0.2647 0.2752 -0.5566 0.5477 -0.8721 0.8164l-1.4179 -1.418c0.311 -0.2564 0.5938 -0.5202 0.8486 -0.7852 0.7132 -0.7417 1.2136 -1.4852 1.5342 -2.041 0.1155 -0.2003 0.2053 -0.3758 0.2744 -0.5156 -0.0691 -0.1398 -0.1589 -0.3153 -0.2744 -0.5156 -0.3206 -0.5558 -0.821 -1.2993 -1.5342 -2.04104C17.6768 7.97174 15.424 6.5 12 6.5c-0.8536 0 -1.6345 0.09085 -2.34766 0.25098L8.0332 5.13184C9.18601 4.74033 10.5033 4.5 12 4.5m0 3c2.4853 0 4.5 2.01472 4.5 4.5 0 0.4819 -0.0775 0.9456 -0.2178 1.3809l-5.664 -5.6641C11.0536 7.57642 11.5179 7.5 12 7.5M2 12l-0.93359 0.3594L0.928711 12l0.137699 -0.3594zm21.0713 0 -0.1377 0.3594L22 12l0.9336 -0.3594z" stroke-width="1"></path>
                                 </g>
                             </svg>
                             
                             <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" id="Eye--Streamline-Solar" height="24" width="24">
  <desc>
    Eye Streamline Icon: https://streamlinehq.com
  </desc>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M2 12c0 1.6394 0.42496 2.1915 1.27489 3.2957C4.97196 17.5004 7.81811 20 12 20c4.1819 0 7.028 -2.4996 8.7251 -4.7043C21.575 14.1915 22 13.6394 22 12s-0.425 -2.19147 -1.2749 -3.29567C19.028 6.49956 16.1819 4 12 4 7.81811 4 4.97196 6.49956 3.27489 8.70433 2.42496 9.80853 2 10.3606 2 12Zm10 -3.75c-2.07107 0 -3.75 1.67893 -3.75 3.75 0 2.0711 1.67893 3.75 3.75 3.75 2.0711 0 3.75 -1.6789 3.75 -3.75 0 -2.07107 -1.6789 -3.75 -3.75 -3.75Z" fill="currentColor" stroke-width="1"></path>
</svg>
                         </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-coral" style="width:100%; padding:0.75rem; margin-top:1rem;">
                    Create Account
                </button>
            </form>

            <div class="auth-switch" style="margin-top:1.5rem; text-align:center; font-size:0.875rem; color:var(--text-muted);">
                <span>Already have an account?</span> <a href="login.php">Sign In</a>
            </div>
        </section>
    </div>

     <script>
          document.getElementById('togglePassword').addEventListener('click', function () {
              const passwordInput = document.getElementById('password');
              const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
              passwordInput.setAttribute('type', type);
              
              const eyeClosed = this.querySelector('.eye-closed');
              const eyeOpen = this.querySelector('.eye-open');
              
              if (type === 'password') {
                  eyeClosed.style.display = 'block';
                  eyeOpen.style.display = 'none';
                  this.style.color = 'var(--text-muted)';
              } else {
                  eyeClosed.style.display = 'none';
                  eyeOpen.style.display = 'block';
                  this.style.color = 'var(--teal)';
              }
          });

          document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
              const confirmInput = document.getElementById('confirm_password');
              const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
              confirmInput.setAttribute('type', type);
              
              const eyeClosed = this.querySelector('.eye-closed');
              const eyeOpen = this.querySelector('.eye-open');
              
              if (type === 'password') {
                  eyeClosed.style.display = 'block';
                  eyeOpen.style.display = 'none';
                  this.style.color = 'var(--text-muted)';
              } else {
                  eyeClosed.style.display = 'none';
                  eyeOpen.style.display = 'block';
                  this.style.color = 'var(--teal)';
              }
          });

        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 400);
                }, 4500);
            });
        });
    </script>
</body>
</html>