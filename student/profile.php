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

$stmt = $conn->prepare(
    "SELECT name, student_id, email, username, role, status
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - Student | SmartMess</title>

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

        .profile-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(30, 43, 55, 0.07);
            max-width: 850px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 1px solid #edf1f5;
            margin-bottom: 25px;
        }

        .profile-icon {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #e5ecf8;
            color: #3a5ba0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .profile-header h2 {
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .profile-header p {
            font-size: 13px;
            color: #718096;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 17px 18px;
            border: 1px solid #edf1f5;
        }

        .detail-label {
            font-size: 11px;
            color: #718096;
            font-weight: 700;
            margin-bottom: 7px;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: #1e2b37;
        }

        .status {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 20px;
            background: #e6f7ed;
            color: #21854d;
            font-size: 11px;
            font-weight: 800;
        }

        @media (max-width: 800px) {

            .page-content {
                margin-left: 250px;
                padding: 25px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>
</head>

<body>

<?php include "../includes/student_sidebar.php"; ?>

<div class="page-content">

    <div class="top-section">

        <div>
            <div class="page-title">My Profile</div>
            <div class="page-subtitle">
                View your SmartMess account information
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Dashboard
        </a>

    </div>


    <div class="profile-card">

        <div class="profile-header">

            <div class="profile-icon">
                <i class="fa-solid fa-user"></i>
            </div>

            <div>
                <h2>
                    <?php echo htmlspecialchars($student['name']); ?>
                </h2>

                <p>Student Account</p>
            </div>

        </div>


        <div class="details-grid">

            <div class="detail-box">

                <div class="detail-label">
                    Full Name
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($student['name']); ?>
                </div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Student ID
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($student['student_id']); ?>
                </div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Username
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($student['username']); ?>
                </div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Email
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($student['email']); ?>
                </div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Role
                </div>

                <div class="detail-value">
                    Student
                </div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Account Status
                </div>

                <div class="detail-value">

                    <span class="status">
                        <?php echo htmlspecialchars($student['status']); ?>
                    </span>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>