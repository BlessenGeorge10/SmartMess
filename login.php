
<?php

session_start();

$error = "";

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = strtolower(trim($_POST["email"] ?? ""));

    // Do not trim passwords; check them exactly as entered.
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {

        $error = "Please fill in all fields.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | 1. DEMO ADMIN LOGIN
        |--------------------------------------------------------------------------
        */

        if (
            ($login === "admin" || $login === "admin@smartmess.com")
            && $password === "admin123"
        ) {

            session_regenerate_id(true);

            $_SESSION["logged_in"] = true;
            $_SESSION["user_id"] = 3;
            $_SESSION["name"] = "System Admin";
            $_SESSION["username"] = "admin";
            $_SESSION["email"] = "admin@smartmess.com";
            $_SESSION["role"] = "admin";

            header("Location: /smartmess/admin/dashboard.php");
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. DEMO MANAGER LOGIN
        |--------------------------------------------------------------------------
        */

        elseif (
            ($login === "manager" || $login === "manager@smartmess.com")
            && $password === "manager123"
        ) {

            session_regenerate_id(true);

            $_SESSION["logged_in"] = true;
            $_SESSION["user_id"] = 2;
            $_SESSION["name"] = "Mess Warden";
            $_SESSION["username"] = "manager";
            $_SESSION["email"] = "manager@smartmess.com";
            $_SESSION["role"] = "manager";

            header("Location: /smartmess/manager/dashboard.php");
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DEMO STUDENT LOGIN
        |--------------------------------------------------------------------------
        */

        elseif (
            ($login === "student" || $login === "student@smartmess.com")
            && $password === "student123"
        ) {

            session_regenerate_id(true);

            $_SESSION["logged_in"] = true;
            $_SESSION["user_id"] = 1;
            $_SESSION["name"] = "John Student";
            $_SESSION["student_name"] = "John Student";
            $_SESSION["username"] = "student";
            $_SESSION["email"] = "student@smartmess.com";
            $_SESSION["role"] = "student";

            header("Location: /smartmess/student/dashboard.php");
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. DATABASE LOGIN
        |--------------------------------------------------------------------------
        */

        else {

            require_once __DIR__ . "/db_config.php";

            if (!isset($conn) || !$conn) {

                $error = "Database connection failed.";

            } else {

                $stmt = $conn->prepare(
                    "SELECT
                        id,
                        name,
                        student_id,
                        email,
                        username,
                        password,
                        role,
                        status
                     FROM users
                     WHERE LOWER(username) = ?
                        OR LOWER(email) = ?
                     LIMIT 1"
                );

                if (!$stmt) {

                    $error = "Database query error.";

                } else {

                    $stmt->bind_param("ss", $login, $login);
                    $stmt->execute();

                    $result = $stmt->get_result();

                    if ($user = $result->fetch_assoc()) {

                        $role = strtolower(trim($user["role"] ?? ""));
                        $status = strtolower(trim($user["status"] ?? ""));

                        /*
                        |--------------------------------------------------------------------------
                        | STUDENT LOGIN
                        |--------------------------------------------------------------------------
                        */

                        if ($role === "student") {

                            if ($status === "pending") {

                                $error =
                                    "Your account is waiting for admin approval.";

                            } elseif ($status === "rejected") {

                                $error =
                                    "Your registration was rejected. Please contact the administrator.";

                            } elseif ($status === "approved") {

                                if (
                                    password_verify(
                                        $password,
                                        $user["password"]
                                    )
                                ) {

                                    session_regenerate_id(true);

                                    $_SESSION["logged_in"] = true;
                                    $_SESSION["user_id"] = $user["id"];
                                    $_SESSION["name"] = $user["name"];
                                    $_SESSION["student_name"] = $user["name"];
                                    $_SESSION["username"] = $user["username"];
                                    $_SESSION["email"] = $user["email"];
                                    $_SESSION["role"] = "student";

                                    header(
                                        "Location: /smartmess/student/dashboard.php"
                                    );
                                    exit;

                                } else {

                                    $error =
                                        "Invalid username/email or password.";
                                }

                            } else {

                                $error =
                                    "Your account is not active. Please contact the administrator.";
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | MANAGER LOGIN
                        |--------------------------------------------------------------------------
                        */

                        elseif ($role === "manager") {

                            if (
                                password_verify(
                                    $password,
                                    $user["password"]
                                )
                            ) {

                                session_regenerate_id(true);

                                $_SESSION["logged_in"] = true;
                                $_SESSION["user_id"] = $user["id"];
                                $_SESSION["name"] = $user["name"];
                                $_SESSION["username"] = $user["username"];
                                $_SESSION["email"] = $user["email"];
                                $_SESSION["role"] = "manager";

                                header(
                                    "Location: /smartmess/manager/dashboard.php"
                                );
                                exit;

                            } else {

                                $error =
                                    "Invalid username/email or password.";
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN LOGIN
                        |--------------------------------------------------------------------------
                        */

                        elseif ($role === "admin") {

                            if (
                                password_verify(
                                    $password,
                                    $user["password"]
                                )
                            ) {

                                session_regenerate_id(true);

                                $_SESSION["logged_in"] = true;
                                $_SESSION["user_id"] = $user["id"];
                                $_SESSION["name"] = $user["name"];
                                $_SESSION["username"] = $user["username"];
                                $_SESSION["email"] = $user["email"];
                                $_SESSION["role"] = "admin";

                                header(
                                    "Location: /smartmess/admin/dashboard.php"
                                );
                                exit;

                            } else {

                                $error =
                                    "Invalid username/email or password.";
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | UNKNOWN ROLE
                        |--------------------------------------------------------------------------
                        */

                        else {

                            $error =
                                "Invalid account role. Please contact the administrator.";
                        }

                    } else {

                        $error =
                            "Invalid username/email or password.";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | SmartMess</title>

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

        /* TOP BRANDING */

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
            flex-shrink: 0;

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

        .brand-name {
            color: var(--white);
        }

        .brand-name span {
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

        /* MAIN CARD */

        .login-card {
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

        /* LEFT WELCOME PANEL */

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

            min-height: 610px;
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

        /* FEATURES */

        .features-list {
            display: flex;
            flex-direction: column;
            gap: 19px;
            margin-top: 38px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
        }

        .feature-icon {
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

        .feature-info h3 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #ffffff;
        }

        .feature-info p {
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
            line-height: 1.8;
        }

        .panel-footer i {
            color: var(--cyan);
            margin-right: 5px;
        }

        /* RIGHT LOGIN PANEL */

        .form-section {
            padding: 46px 48px;

            background: var(--white);

            display: flex;
            flex-direction: column;
            justify-content: center;

            min-width: 0;
        }

        .form-heading {
            margin-bottom: 28px;
        }

        .small-label {
            color: #0284c7;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .form-heading h2 {
            font-size: 30px;
            font-weight: 800;
            color: var(--deep-navy);
            letter-spacing: -0.8px;
            margin-bottom: 9px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* ERROR MESSAGE */

        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            padding: 13px 14px;
            border-radius: 11px;
            margin-bottom: 20px;

            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;

            font-size: 12px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .alert-error i {
            margin-top: 3px;
        }

        /* FORM FIELDS */

        .form-body {
            display: flex;
            flex-direction: column;
            gap: 19px;
        }

        .form-group {
            margin-bottom: 0;
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

        .form-input {
            display: block;
            width: 100%;
            height: 49px;

            padding: 0 45px 0 42px;

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

        .form-input::placeholder {
            color: #a0aec0;
        }

        .form-input:hover {
            border-color: #b7c5d8;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.13);
        }

        .input-wrapper:focus-within > i {
            color: #0284c7;
        }

        /* PASSWORD VISIBILITY BUTTON */

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);

            width: 30px;
            height: 30px;

            border: none;
            border-radius: 7px;
            background: transparent;

            color: #94a3b8;
            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: #0284c7;
            background: #eaf7ff;
        }

        /* REMEMBER OPTION */

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;

            margin-top: -3px;

            font-size: 12px;
        }

        .remember-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: #64748b;
            cursor: pointer;
        }

        .remember-box input {
            width: 15px;
            height: 15px;
            accent-color: #0284c7;
            cursor: pointer;
        }

        /* LOGIN BUTTON */

        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            width: 100%;
            min-height: 49px;

            border: none;
            border-radius: 11px;

            margin-top: 2px;
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

        .btn-submit i {
            color: var(--cyan);
        }

        .btn-submit:hover {
            transform: translateY(-1px);

            box-shadow: 0 10px 22px rgba(30, 43, 55, 0.23);

            background: linear-gradient(
                105deg,
                #354d64,
                #243746
            );
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* SECURITY NOTE */

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            background: #f0f9ff;
            border: 1px solid #d7f0fd;
            border-radius: 11px;

            padding: 13px;
            margin-top: 22px;
        }

        .security-note > i {
            color: #0284c7;
            font-size: 15px;
            margin-top: 2px;
        }

        .security-note p {
            color: #475569;
            font-size: 11px;
            line-height: 1.7;
        }

        .security-note strong {
            color: #1e3a5f;
        }

        /* BOTTOM LINKS */

        .home-link {
            display: block;
            text-align: center;

            margin-top: 23px;

            color: #718096;
            text-decoration: none;
            font-size: 12px;

            transition: color 0.2s;
        }

        .home-link i {
            margin-right: 5px;
        }

        .home-link:hover {
            color: #0369a1;
        }

        .copyright-text {
            max-width: 1100px;
            width: 100%;

            text-align: center;
            margin-top: 22px;

            color: #b7c4d2;
            font-size: 11px;
            line-height: 1.7;
        }

        /* TABLET */

        @media (max-width: 900px) {

            .login-card {
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

            .features-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
                margin-top: 28px;
            }

            .panel-footer {
                margin-top: 28px;
            }

            .form-section {
                padding: 38px 34px;
            }
        }

        /* MOBILE */

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

            .login-card {
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

            .features-list {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-top: 25px;
            }

            .feature-item {
                gap: 11px;
            }

            .feature-icon {
                width: 35px;
                height: 35px;
                flex-basis: 35px;
            }

            .panel-footer {
                font-size: 10px;
            }

            .form-section {
                padding: 30px 22px;
            }

            .form-heading {
                margin-bottom: 23px;
            }

            .form-heading h2 {
                font-size: 27px;
            }

            .form-body {
                gap: 17px;
            }

            .form-input {
                height: 47px;
            }

            .form-options {
                font-size: 11px;
            }

            .copyright-text {
                font-size: 10px;
                margin-top: 16px;
            }
        }

        /* ACCESSIBILITY */

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

    <!-- TOP BRANDING -->

    <header class="top-bar">

        <a href="/smartmess/index.php" class="brand">

            <span class="brand-icon">
                <i class="fa-solid fa-utensils"></i>
            </span>

            <span class="brand-name">
                Smart<span>Mess</span>
            </span>

        </a>

        <div class="top-login">
            Student portal
        </div>

    </header>


    <!-- MAIN LOGIN CARD -->

    <main class="login-card">

        <!-- LEFT WELCOME PANEL -->

        <section class="welcome-panel">

            <div class="welcome-content">

                <div class="eyebrow">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secure Login Portal
                </div>

                <h1>
                    Welcome back to your
                    <span>SmartMess.</span>
                </h1>

                <p class="welcome-description">
                    Sign in to access your SmartMess account.
                    Stay connected with meal schedules, feedback,
                    announcements, and mess management services
                    from one convenient platform.
                </p>


                <div class="features-list">

                    <div class="feature-item">

                        <div class="feature-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>

                        <div class="feature-info">

                            <h3>Meal Information</h3>

                            <p>
                                Check menus and meal schedules.
                            </p>

                        </div>

                    </div>


                    <div class="feature-item">

                        <div class="feature-icon">
                            <i class="fa-solid fa-star"></i>
                        </div>

                        <div class="feature-info">

                            <h3>Ratings & Feedback</h3>

                            <p>
                                Share feedback and help improve services.
                            </p>

                        </div>

                    </div>


                    <div class="feature-item">

                        <div class="feature-icon">
                            <i class="fa-solid fa-calendar-minus"></i>
                        </div>

                        <div class="feature-info">

                            <h3>Meal Skip Requests</h3>

                            <p>
                                Manage planned meal skips conveniently.
                            </p>

                        </div>

                    </div>


                    <div class="feature-item">

                        <div class="feature-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>

                        <div class="feature-info">

                            <h3>Smart Management</h3>

                            <p>
                                Dedicated access for students, managers and admins.
                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <div class="panel-footer">

                <i class="fa-solid fa-lock"></i>

                Your SmartMess account provides access according
                to your assigned role.

                <br>

                SmartMess — Better communication, better mess management.

            </div>

        </section>


        <!-- RIGHT LOGIN FORM -->

        <section class="form-section">

            <div class="form-heading">

                <div class="small-label">
                    Your account
                </div>

                <h2>Sign In</h2>

                <p>
                    Enter your username or email address and password
                    to continue to your dashboard.
                </p>

            </div>


            <?php if ($error !== ""): ?>

                <div class="alert-error" role="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <div>
                        <?php
                        echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>

                </div>

            <?php endif; ?>


            <form method="POST" action="/smartmess/login.php" class="form-body">

                <!-- USERNAME OR EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Username or Email Address
                    </label>

                    <div class="input-wrapper">

                        <i class="fa-regular fa-user"></i>

                        <input
                            type="text"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="Enter username or email"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>

                    </div>

                </div>


                <!-- REMEMBER OPTION -->

                <div class="form-options">

                    <label class="remember-box">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        Remember me

                    </label>

                </div>


                <!-- SUBMIT -->

                <button type="submit" class="btn-submit">

                    Sign In

                    <i class="fa-solid fa-arrow-right"></i>

                </button>

            </form>


            <!-- SECURITY NOTE -->

            <div class="security-note">

                <i class="fa-solid fa-circle-info"></i>

                <p>
                    <strong>Student account pending?</strong>
                    If you have just registered, please wait for
                    administrator approval before signing in.
                </p>

            </div>


            <a href="/smartmess/index.php" class="home-link">

                <i class="fa-solid fa-arrow-left"></i>

                Back to SmartMess Home

            </a>

        </section>

    </main>


    <!-- COPYRIGHT -->

    <footer class="copyright-text">

        &copy; <?php echo date("Y"); ?> SmartMess.
        Smart Mess Management System.

    </footer>


    <!-- PASSWORD VISIBILITY -->

    <script>

        const passwordInput = document.getElementById("password");
        const togglePassword = document.getElementById("togglePassword");

        togglePassword.addEventListener("click", function () {

            const isPassword = passwordInput.type === "password";

            passwordInput.type = isPassword ? "text" : "password";

            togglePassword.innerHTML = isPassword
                ? '<i class="fa-regular fa-eye-slash"></i>'
                : '<i class="fa-regular fa-eye"></i>';

            togglePassword.setAttribute(
                "aria-label",
                isPassword ? "Hide password" : "Show password"
            );

            togglePassword.setAttribute(
                "aria-pressed",
                isPassword ? "true" : "false"
            );

        });

    </script>

</body>

</html>
