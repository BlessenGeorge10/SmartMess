
<?php
session_start();

// Ensure user is logged in as a student
if (
    !isset($_SESSION['logged_in']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: /smartmess/login.php");
    exit;
}

// Connect to SmartMess database
require_once "../db_config.php";

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check db_config.php.");
}

// Fetch meal schedule from the database
$schedule_query = "
    SELECT *
    FROM meal_schedule
    ORDER BY
        FIELD(
            day_of_week,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ),
        FIELD(
            meal_type,
            'Breakfast',
            'Lunch',
            'Evening Snack',
            'Special Lunch',
            'Dinner'
        ),
        id ASC
";

$schedule_result = $conn->query($schedule_query);

if (!$schedule_result) {
    die("Failed to load meal schedule: " . htmlspecialchars($conn->error));
}

// Organize meals by day
$schedule = [];

while ($row = $schedule_result->fetch_assoc()) {
    $day = trim($row['day_of_week']);

    if (!isset($schedule[$day])) {
        $schedule[$day] = [];
    }

    $schedule[$day][] = $row;
}

$days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meal Schedule - Student | SmartMess</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont,
                         Roboto, sans-serif;
        }

        body {
            background-color: #dce7f0;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .app-window {
            background-color: #dce7f0;
            width: 100%;
            min-height: 100vh;
            display: block;
            overflow: hidden;
        }

        .main-workspace {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 28px 35px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
        }

        .page-title-banner {
            background: #eef5fb;
            padding: 24px 28px;
            border-radius: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .page-title-banner h2 {
            font-size: 22px;
            color: #1e2b37;
            font-weight: 800;
        }

        .page-title-banner p {
            font-size: 13px;
            color: #5a6e85;
            margin-top: 4px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dashboard-btn {
            text-decoration: none;
            background: #3a5ba0;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s ease;
        }

        .dashboard-btn:hover {
            background: #2d4880;
        }

        .semester-tag {
            background: #cbe3f7;
            color: #3a5ba0;
            padding: 8px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 22px;
        }

        .day-card {
            background: #eef5fb;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }

        .day-title {
            font-size: 16px;
            color: #1e2b37;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid rgba(58, 91, 160, 0.1);
            padding-bottom: 12px;
        }

        .meal-block {
            background: rgba(255, 255, 255, 0.65);
            border-radius: 16px;
            padding: 14px 16px;
            border-left: 4px solid #3a5ba0;
        }

        .meal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .meal-name {
            font-size: 13px;
            font-weight: 800;
            color: #1e2b37;
        }

        .meal-time {
            font-size: 10px;
            font-weight: 700;
            color: #3a5ba0;
            background: #cbe3f7;
            padding: 3px 10px;
            border-radius: 10px;
        }

        .meal-items-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .item-tag {
            background: #ffffff;
            border: 1px solid #d0e0ff;
            color: #5a6e85;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .empty-message {
            color: #5a6e85;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 900px) {
            .main-workspace {
                margin-left: 230px;
                width: calc(100% - 230px);
                padding: 22px;
            }

            .schedule-grid {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            }
        }

        @media (max-width: 600px) {
            .main-workspace {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }

            .page-title-banner {
                padding: 20px;
            }

            .schedule-grid {
                grid-template-columns: 1fr;
            }

            .day-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="app-window">

    <!-- Common Student Sidebar -->
    <?php include "../includes/student_sidebar.php"; ?>

    <!-- Main Workspace -->
    <main class="main-workspace">

        <!-- Page Title -->
        <div class="page-title-banner">

            <div>
                <h2>Meal Schedule</h2>
                <p>View your upcoming meals and daily menu.</p>
            </div>

            <div class="page-header-actions">

                <span class="semester-tag">
                    <i class="fa-solid fa-calendar-days"></i>
                    Semester 3
                </span>

                <a href="dashboard.php" class="dashboard-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Dashboard
                </a>

            </div>

        </div>

        <!-- Weekly Schedule: Loaded from Database -->
        <div class="schedule-grid">

            <?php foreach ($days as $day): ?>

                <div class="day-card">

                    <h3 class="day-title">
                        <i class="fa-regular fa-calendar"></i>
                        <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <?php if (!empty($schedule[$day])): ?>

                        <?php foreach ($schedule[$day] as $meal): ?>

                            <div class="meal-block">

                                <div class="meal-header">

                                    <span class="meal-name">
                                        <?= htmlspecialchars(
                                            $meal['meal_type'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <span class="meal-time">
                                        <?= htmlspecialchars(
                                            $meal['meal_time'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <div class="meal-items-tags">

                                    <?php
                                    $items = explode(
                                        ',',
                                        $meal['menu_items'] ?? ''
                                    );

                                    $has_items = false;

                                    foreach ($items as $item):
                                        $item = trim($item);

                                        if ($item === '') {
                                            continue;
                                        }

                                        $has_items = true;
                                    ?>

                                        <span class="item-tag">
                                            <?= htmlspecialchars(
                                                $item,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    <?php endforeach; ?>

                                    <?php if (!$has_items): ?>
                                        <span class="item-tag">
                                            Menu not updated
                                        </span>
                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="empty-message">
                            No meals scheduled for this day.
                        </p>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    </main>

</div>

</body>
</html>
