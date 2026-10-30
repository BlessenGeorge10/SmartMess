
<?php

require_once "../db_config.php";
require_once __DIR__ . '/../includes/password_validation.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $student_id = trim($_POST["student_id"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $name === "" ||
        $student_id === "" ||
        $email === "" ||
        $username === "" ||
        $password === ""
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (($passwordError = smartmess_password_validation_error($password)) !== null) {

        $error = $passwordError;

    } else {

        // Check whether email, username or student ID already exists
        $check = $conn->prepare(
            "SELECT id FROM users
             WHERE email = ? OR username = ? OR student_id = ?"
        );

        $check->bind_param("sss", $email, $username, $student_id);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "Email, username or student ID already exists.";

        } else {

            // Hash the password before storing it
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // New student accounts wait for Admin approval
            $role = "student";
            $status = "pending";

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, student_id, email, username, password, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssssss",
                $name,
                $student_id,
                $email,
                $username,
                $hashed_password,
                $role,
                $status
            );

            if ($stmt->execute()) {

                $message = "Registration submitted successfully! Please wait for Admin approval.";

            } else {

                $error = "Registration failed. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Registration | SmartMess</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        :root {
            --navy: #2c3e50;
            --deep-navy: #1e2b37;
            --light-bg: #f0f2f8;
            --white: #ffffff;
            --cyan: #38bdf8;
            --text: #1a202c;
            --muted: #718096;
            --border: #dce3ed;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(56, 189, 248, 0.15),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(44, 62, 80, 0.5),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #1e2b37 0%,
                    #2c3e50 55%,
                    #14212d 100%
                );
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 28px 18px;
            overflow-x: hidden;
        }

        /* ===== TOP BRANDING ===== */

        .top-bar {
            width: 100%;
            max-width: 1100px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 26px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--white);
            text-decoration: none;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.6px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(
                135deg,
                var(--cyan),
                #0ea5e9
            );
            color: var(--deep-navy);
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.22);
            font-size: 19px;
        }

        .brand span {
            color: var(--cyan);
        }

        .top-login {
            color: #e2e8f0;
            font-size: 13px;
        }

        .top-login a {
            color: var(--cyan);
            font-weight: 700;
            text-decoration: none;
            margin-left: 5px;
        }

        .top-login a:hover {
            color: var(--white);
        }

        /* ===== MAIN LAYOUT ===== */

        .page-layout {
            width: 100%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            background: var(--white);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 25px 75px rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* ===== LEFT INFORMATION PANEL ===== */

        .welcome-panel {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            padding: 48px 38px;
            background:
                radial-gradient(
                    circle at 10% 90%,
                    rgba(56, 189, 248, 0.18),
                    transparent 35%
                ),
                linear-gradient(
                    150deg,
                    #1e2b37,
                    #2c3e50
                );
            color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 650px;
        }

        .welcome-panel::before {
            content: "";
            position: absolute;
            z-index: -1;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            border: 1px solid rgba(56, 189, 248, 0.15);
            top: 15%;
            right: -130px;
        }

        .welcome-panel::after {
            content: "";
            position: absolute;
            z-index: -1;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 1px solid rgba(56, 189, 248, 0.12);
            bottom: 14%;
            right: -80px;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 30px;
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.24);
            color: #bae6fd;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 26px;
        }

        .eyebrow i {
            color: var(--cyan);
        }

        .welcome-panel h1 {
            font-size: clamp(32px, 3.5vw, 45px);
            line-height: 1.15;
            letter-spacing: -1.5px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .welcome-panel h1 span {
            color: var(--cyan);
        }

        .welcome-description {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.9;
            max-width: 370px;
        }

        /* ===== BENEFITS ===== */

        .benefit-list {
            display: flex;
            flex-direction: column;
            gap: 19px;
            margin-top: 38px;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
        }

        .benefit-icon {
            flex: 0 0 39px;
            width: 39px;
            height: 39px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            color: var(--cyan);
            font-size: 15px;
        }

        .benefit-text h3 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #ffffff;
        }

        .benefit-text p {
            font-size: 12px;
            line-height: 1.65;
            color: #b9c6d5;
        }

        .panel-footer {
            position: relative;
            z-index: 1;
            border-top: 1px solid rgba(255, 255, 255, 0.13);
            padding-top: 20px;
            margin-top: 38px;
            color: #aebdcc;
            font-size: 11px;
            line-height: 1.7;
        }

        .panel-footer i {
            color: var(--cyan);
            margin-right: 5px;
        }

        /* ===== RIGHT REGISTRATION PANEL ===== */

        .register-panel {
            padding: 46px 48px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }

        .form-heading {
            margin-bottom: 26px;
        }

        .form-heading .small-label {
            color: #0284c7;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .form-heading h2 {
            font-size: 29px;
            font-weight: 800;
            color: var(--deep-navy);
            letter-spacing: -0.8px;
            margin-bottom: 9px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        /* ===== ALERTS ===== */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 14px;
            border-radius: 11px;
            margin-bottom: 19px;
            font-size: 12px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .alert i {
            margin-top: 3px;
        }

        .success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
        }

        /* ===== FORM FIELDS ===== */

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrapper input {
            display: block;
            width: 100%;
            height: 47px;
            padding: 0 14px 0 42px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: #f8fafc;
            color: var(--text);
            font-size: 13px;
            outline: none;
            transition:
                border-color 0.2s,
                box-shadow 0.2s,
                background 0.2s;
        }

        .input-wrapper input::placeholder {
            color: #a0aec0;
        }

        .input-wrapper input:hover {
            border-color: #b7c5d8;
        }

        .input-wrapper input:focus {
            background: #ffffff;
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.13);
        }

        .input-wrapper:focus-within > i {
            color: #0284c7;
        }

        /* ===== SUBMIT BUTTON ===== */

        .register-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 49px;
            border: none;
            border-radius: 11px;
            margin-top: 7px;
            padding: 13px 18px;
            background: linear-gradient(
                105deg,
                #2c3e50,
                #1e2b37
            );
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 7px 17px rgba(30, 43, 55, 0.17);
            transition:
                transform 0.2s,
                box-shadow 0.2s,
                background 0.2s;
        }

        .register-button i {
            color: var(--cyan);
        }

        .register-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(30, 43, 55, 0.23);
            background: linear-gradient(
                105deg,
                #354d64,
                #243746
            );
        }

        .register-button:active {
            transform: translateY(0);
        }

        /* ===== APPROVAL NOTE ===== */

        .approval-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #f0f9ff;
            border: 1px solid #d7f0fd;
            border-radius: 11px;
            padding: 13px;
            margin-top: 19px;
        }

        .approval-note > i {
            color: #0284c7;
            font-size: 15px;
            margin-top: 2px;
        }

        .approval-note p {
            color: #475569;
            font-size: 11px;
            line-height: 1.7;
        }

        .approval-note strong {
            color: #1e3a5f;
        }

        /* ===== LOGIN AND HOME LINKS ===== */

        .login-link {
            text-align: center;
            margin-top: 23px;
            color: #718096;
            font-size: 12px;
            line-height: 1.8;
        }

        .login-link a {
            color: #0369a1;
            font-weight: 800;
            text-decoration: none;
        }

        .login-link a:hover {
            color: #0284c7;
            text-decoration: underline;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: #718096;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }

        .back-home i {
            margin-right: 5px;
        }

        .back-home:hover {
            color: #0369a1;
        }

        /* ===== PAGE COPYRIGHT ===== */

        .page-copyright {
            max-width: 1100px;
            width: 100%;
            text-align: center;
            margin-top: 22px;
            color: #b7c4d2;
            font-size: 11px;
            line-height: 1.7;
        }

        /* ===== TABLET ===== */

        @media (max-width: 900px) {
            .page-layout {
                max-width: 650px;
                grid-template-columns: 1fr;
            }

            .welcome-panel {
                min-height: auto;
                padding: 34px;
            }

            .welcome-panel h1 {
                font-size: 35px;
            }

            .benefit-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
                margin-top: 28px;
            }

            .panel-footer {
                margin-top: 28px;
            }

            .register-panel {
                padding: 38px 34px;
            }
        }

        /* ===== MOBILE ===== */

        @media (max-width: 520px) {
            body {
                padding: 16px 11px;
                justify-content: flex-start;
            }

            .top-bar {
                margin: 5px 0 18px;
            }

            .brand {
                font-size: 20px;
            }

            .brand-icon {
                width: 37px;
                height: 37px;
                border-radius: 11px;
                font-size: 16px;
            }

            .top-login {
                font-size: 11px;
            }

            .top-login a {
                margin-left: 2px;
            }

            .page-layout {
                border-radius: 19px;
            }

            .welcome-panel {
                padding: 28px 23px;
            }

            .eyebrow {
                font-size: 10px;
                margin-bottom: 19px;
            }

            .welcome-panel h1 {
                font-size: 31px;
            }

            .welcome-description {
                font-size: 13px;
            }

            .benefit-list {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-top: 25px;
            }

            .benefit-item {
                gap: 11px;
            }

            .benefit-icon {
                width: 35px;
                height: 35px;
                flex-basis: 35px;
            }

            .panel-footer {
                font-size: 10px;
            }

            .register-panel {
                padding: 30px 22px;
            }

            .form-heading {
                margin-bottom: 22px;
            }

            .form-heading h2 {
                font-size: 26px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .input-wrapper input {
                height: 46px;
            }

            .page-copyright {
                font-size: 10px;
                margin-top: 16px;
            }
        }

        /* ===== REDUCED MOTION ===== */

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

    </style>

</head>

<body>

    <!-- Top Branding -->
    <header class="top-bar">

        <a href="../index.php" class="brand">
            <span class="brand-icon">
                <i class="fa-solid fa-utensils"></i>
            </span>

            <span style="color: #ffffff;">
                Smart<span>Mess</span>
            </span>
        </a>

        <div class="top-login">
            Already registered?
            <a href="../login.php">Sign in</a>
        </div>

    </header>


    <!-- Main Registration Layout -->
    <main class="page-layout">

        <!-- Welcome / Information Panel -->
        <section class="welcome-panel">

            <div class="welcome-content">

                <div class="eyebrow">
                    <i class="fa-solid fa-graduation-cap"></i>
                    Student Portal
                </div>

                <h1>
                    A smarter way to manage your
                    <span>mess life.</span>
                </h1>

                <p class="welcome-description">
                    Join SmartMess to stay connected with your mess,
                    explore meal schedules, share feedback, and manage
                    your dining preferences from one place.
                </p>

                <div class="benefit-list">

                    <div class="benefit-item">

                        <div class="benefit-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>

                        <div class="benefit-text">
                            <h3>Meal Information</h3>
                            <p>Keep track of your mess menu and meal schedules.</p>
                        </div>

                    </div>

                    <div class="benefit-item">

                        <div class="benefit-icon">
                            <i class="fa-solid fa-star"></i>
                        </div>

                        <div class="benefit-text">
                            <h3>Share Your Feedback</h3>
                            <p>Rate meals and help improve mess services.</p>
                        </div>

                    </div>

                    <div class="benefit-item">

                        <div class="benefit-icon">
                            <i class="fa-solid fa-calendar-minus"></i>
                        </div>

                        <div class="benefit-text">
                            <h3>Meal Skip Requests</h3>
                            <p>Submit requests when you plan to skip meals.</p>
                        </div>

                    </div>

                    <div class="benefit-item">

                        <div class="benefit-icon">
                            <i class="fa-solid fa-comments"></i>
                        </div>

                        <div class="benefit-text">
                            <h3>Complaints & Updates</h3>
                            <p>Report service issues and stay informed.</p>
                        </div>

                    </div>

                </div>

            </div>

            <div class="panel-footer">
                <i class="fa-solid fa-shield-halved"></i>
                Secure registration with administrator approval.
                <br>
                SmartMess — Better communication, better mess management.
            </div>

        </section>


        <!-- Registration Form -->
        <section class="register-panel">

            <div class="form-heading">

                <div class="small-label">
                    Create your account
                </div>

                <h2>Student Registration</h2>

                <p>
                    Enter your details below to request access
                    to your SmartMess student account.
                </p>

            </div>


            <?php if ($message !== ""): ?>

                <div class="alert success" role="status">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>
                        <?php echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="alert error" role="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <div>
                        <?php echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                </div>

            <?php endif; ?>


            <form method="POST" action="">

                <div class="form-group">

                    <label for="name">Full Name</label>

                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            value="<?php echo htmlspecialchars(
                                $_POST['name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >
                    </div>

                </div>


                <div class="form-group">

                    <label for="student_id">Student ID</label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-id-card"></i>

                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            placeholder="Enter your student ID"
                            value="<?php echo htmlspecialchars(
                                $_POST['student_id'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >
                    </div>

                </div>


                <div class="form-group">

                    <label for="email">Email Address</label>

                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            value="<?php echo htmlspecialchars(
                                $_POST['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >
                    </div>

                </div>


                <div class="form-group">

                    <label for="username">Username</label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-at"></i>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Choose a username"
                            autocomplete="username"
                            value="<?php echo htmlspecialchars(
                                $_POST['username'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >
                    </div>

                </div>


                <div class="form-group">

                    <label for="password">Password</label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            minlength="8"
                            title="Use at least 8 characters with uppercase, lowercase, a number, and a special character."
                            autocomplete="new-password"
                            required
                        >
                    </div>

                </div>


                <button type="submit" class="register-button">
                    Create Student Account
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>


            <div class="approval-note">

                <i class="fa-solid fa-circle-info"></i>

                <p>
                    <strong>Account approval required.</strong>
                    After registering, please wait for an administrator
                    to approve your account before signing in.
                </p>

            </div>


            <div class="login-link">
                Already have an account?
                <a href="../login.php">Sign in here</a>
            </div>

            <a href="../index.php" class="back-home">
                <i class="fa-solid fa-arrow-left"></i>
                Back to SmartMess Home
            </a>

        </section>

    </main>


    <footer class="page-copyright">
        &copy; <?php echo date("Y"); ?> SmartMess.
        Smart Mess Management System.
    </footer>

</body>

</html>
