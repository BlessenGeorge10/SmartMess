<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

require_once "../db_config.php";

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password = $_POST['current_password'] ?? "";
    $new_password = $_POST['new_password'] ?? "";
    $confirm_password = $_POST['confirm_password'] ?? "";

    if ($current_password === "" || $new_password === "" || $confirm_password === "") {

        $message = "Please fill in all fields.";
        $message_type = "error";

    } elseif ($new_password !== $confirm_password) {

        $message = "New password and confirm password do not match.";
        $message_type = "error";

    } elseif (strlen($new_password) < 6) {

        $message = "New password must contain at least 6 characters.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "SELECT password FROM users WHERE id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user || !password_verify($current_password, $user['password'])) {

            $message = "Current password is incorrect.";
            $message_type = "error";

        } else {

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $update_stmt = $conn->prepare(
                "UPDATE users SET password = ? WHERE id = ?"
            );

            $update_stmt->bind_param(
                "si",
                $hashed_password,
                $user_id
            );

            if ($update_stmt->execute()) {

                $message = "Password changed successfully.";
                $message_type = "success";

            } else {

                $message = "Unable to change password. Please try again.";
                $message_type = "error";
            }

            $update_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Change Password - Student | SmartMess</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f7fb;
            color: #1e2b37;
        }

        .page-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 35px 45px;
        }

        .top-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: #1e2b37;
        }

        .page-subtitle {
            margin-top: 6px;
            font-size: 13px;
            color: #718096;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            background: #3a5ba0;
            color: #ffffff;
            padding: 11px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .back-btn:hover {
            background: #304d8a;
        }

        .password-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            max-width: 650px;
            box-shadow: 0 8px 25px rgba(30, 43, 55, 0.07);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 22px;
            border-bottom: 1px solid #edf1f5;
            margin-bottom: 25px;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #e5ecf8;
            color: #3a5ba0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 800;
        }

        .card-header p {
            margin-top: 4px;
            font-size: 12px;
            color: #718096;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #44566c;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8a98a9;
            font-size: 14px;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 42px;
            border: 1px solid #dfe5ec;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            color: #1e2b37;
            background: #ffffff;
        }

        .input-wrapper input:focus {
            border-color: #3a5ba0;
            box-shadow: 0 0 0 3px rgba(58, 91, 160, 0.08);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #8a98a9;
        }

        .toggle-password:hover {
            color: #3a5ba0;
        }

        .submit-btn {
            width: 100%;
            border: none;
            background: #3a5ba0;
            color: #ffffff;
            padding: 13px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 5px;
        }

        .submit-btn:hover {
            background: #304d8a;
        }

        .message {
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .message.success {
            background: #e7f7ed;
            color: #21854d;
            border: 1px solid #ccebd8;
        }

        .message.error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f3cccc;
        }

        .password-note {
            margin-top: 18px;
            font-size: 11px;
            color: #718096;
            line-height: 1.6;
        }

    </style>

</head>

<body>

<?php include "../includes/student_sidebar.php"; ?>

<div class="page-content">

    <div class="top-section">

        <div>
            <div class="page-title">Change Password</div>

            <div class="page-subtitle">
                Update your SmartMess account password
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Dashboard
        </a>

    </div>


    <div class="password-card">

        <div class="card-header">

            <div class="card-icon">
                <i class="fa-solid fa-lock"></i>
            </div>

            <div>
                <h2>Update Password</h2>

                <p>
                    Enter your current password and choose a new one.
                </p>
            </div>

        </div>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-lock"></i>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Enter current password"
                        required
                    >

                    <span
                        class="toggle-password"
                        onclick="togglePassword('current_password', this)"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </span>

                </div>

            </div>


            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-key"></i>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter new password"
                        required
                    >

                    <span
                        class="toggle-password"
                        onclick="togglePassword('new_password', this)"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </span>

                </div>

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-shield-halved"></i>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter new password"
                        required
                    >

                    <span
                        class="toggle-password"
                        onclick="togglePassword('confirm_password', this)"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </span>

                </div>

            </div>


            <button type="submit" class="submit-btn">
                <i class="fa-solid fa-check"></i>
                &nbsp; Change Password
            </button>

        </form>


        <div class="password-note">
            <i class="fa-solid fa-circle-info"></i>
            Your new password must contain at least 6 characters.
        </div>

    </div>

</div>


<script>

function togglePassword(inputId, iconContainer) {

    const input = document.getElementById(inputId);
    const icon = iconContainer.querySelector("i");

    if (input.type === "password") {

        input.type = "text";

        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");

    } else {

        input.type = "password";

        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");

    }
}

</script>

</body>
</html>