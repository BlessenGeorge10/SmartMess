<?php

session_start();


// Ensure user is logged in as manager

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manager') {

    header("Location: ../login.php");

    exit;

}


/* =========================================================
   MEAL SKIP CONNECTION
   ========================================================= */

require_once "../db_config.php";


/*
 * Manager Meal Skip page
 *
 * Actual location:
 * manager/mealskip/mealskip.php
 */
$meal_skip_page = "mealskip/mealskip.php";


/*
 * Get active meal skip requests from the same
 * meal_skips table used by the Student module.
 */
$meal_skip_query = "
    SELECT
        COUNT(*) AS total_requests,
        COALESCE(SUM(breakfast), 0) AS breakfast_count,
        COALESCE(SUM(lunch), 0) AS lunch_count,
        COALESCE(SUM(dinner), 0) AS dinner_count
    FROM meal_skips
    WHERE status = 'Active'
";


$meal_skip_result = $conn->query($meal_skip_query);


if ($meal_skip_result) {

    $meal_skip_data = $meal_skip_result->fetch_assoc();

    $total_skip_requests =
        (int) ($meal_skip_data['total_requests'] ?? 0);

    $breakfast_skip_count =
        (int) ($meal_skip_data['breakfast_count'] ?? 0);

    $lunch_skip_count =
        (int) ($meal_skip_data['lunch_count'] ?? 0);

    $dinner_skip_count =
        (int) ($meal_skip_data['dinner_count'] ?? 0);

} else {

    $total_skip_requests = 0;

    $breakfast_skip_count = 0;

    $lunch_skip_count = 0;

    $dinner_skip_count = 0;

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manager Dashboard | SmartMess</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }

        body {
            background-color: #2c3e50;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .app-window {
            background-color: #f0f2f8;
            width: 100%;
            max-width: 1250px;
            min-height: 750px;
            border-radius: 28px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }



        /* Sidebar Styling */

        .sidebar {
            width: 230px;
            background-color: #1e2b37;
            padding: 30px 0 25px 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
            flex-shrink: 0;
        }

        .brand-logo {
            padding: 0 25px 35px 25px;
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
        }

        .online-dot {
            height: 9px;
            width: 9px;
            background-color: #48bb78;
            border-radius: 50%;
            display: inline-block;
            margin-left: 2px;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 25px;
            color: #b8c7ce;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: #ffffff;
        }

        .nav-link.active {
            background-color: #f0f2f8;
            color: #1e2b37;
            font-weight: 700;
            border-radius: 12px 0 0 12px;
            margin-left: 15px;
            padding-left: 20px;
        }

        .logout-link {
            padding: 0 25px;
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-link:hover {
            color: #c0392b;
        }



        /* Main Workspace */

        .main-workspace {
            flex: 1;
            padding: 35px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a202c;
            font-weight: 700;
        }

        .page-header p {
            font-size: 13px;
            color: #718096;
            margin-top: 2px;
        }



        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .shift-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }



        /* Metric Cards */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-info h3 {
            font-size: 20px;
            color: #1a202c;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-info p {
            font-size: 11px;
            color: #718096;
            font-weight: 600;
        }



        /* Main Workspace Grid */

        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
        }

        .card-panel {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-link {
            font-size: 12px;
            color: #2c3e50;
            font-weight: 700;
            text-decoration: none;
        }

        .view-link:hover {
            text-decoration: underline;
        }



        /* Today's Meal Cards */

        .meal-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .meal-item {
            background: #f8fafc;
            border-left: 4px solid #2c3e50;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meal-item.breakfast {
            border-left-color: #dd6b20;
        }

        .meal-item.lunch {
            border-left-color: #38a169;
        }

        .meal-item.dinner {
            border-left-color: #805ad5;
        }

        .meal-info h4 {
            font-size: 14px;
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .meal-info p {
            font-size: 12px;
            color: #718096;
        }

        .meal-time {
            font-size: 11px;
            font-weight: 700;
            color: #a0aec0;
            background: #ffffff;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid #edf2f7;
        }



        /* Complaints List */

        .complaint-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .complaint-item {
            background: #f8fafc;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #edf2f7;
        }

        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .student-name {
            font-size: 13px;
            font-weight: 700;
            color: #2d3748;
        }

        .badge-status {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending {
            background: #feebc8;
            color: #c05621;
        }

        .status-resolved {
            background: #c6f6d5;
            color: #22543d;
        }

        .complaint-text {
            font-size: 12px;
            color: #718096;
            line-height: 1.4;
        }



        /* Quick Action Buttons */

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 14px;
            border-radius: 12px;
            color: #2d3748;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }

    </style>

</head>


<body>


    <div class="app-window">


        <!-- Sidebar Navigation -->

        <aside class="sidebar">


            <div class="sidebar-top">


                <div class="brand-logo">

                    <i class="fa-solid fa-utensils"></i>
                    Manager

                    <span class="online-dot"
                          title="Active Session"></span>

                </div>


                <nav class="nav-list">


                    <a href="dashboard.php"
                       class="nav-link active">

                        <i class="fa-solid fa-border-all"></i>
                        Dashboard

                    </a>


                    <a href="menu.php"
                       class="nav-link">

                        <i class="fa-solid fa-calendar-days"></i>
                        Meal Schedule

                    </a>


                    <!-- =================================================
                         NEW: MEAL SKIP
                         ================================================= -->

                    <a href="<?php echo htmlspecialchars($meal_skip_page); ?>"
                       class="nav-link">

                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                        Meal Skip

                    </a>


                    <a href="complaints.php"
                       class="nav-link">

                        <i class="fa-solid fa-comment-dots"></i>
                        Complaints

                    </a>


                    <a href="announcements.php"
                       class="nav-link">

                        <i class="fa-solid fa-bullhorn"></i>
                        Announcements

                    </a>


                    <a href="reports.php"
                       class="nav-link">

                        <i class="fa-solid fa-chart-pie"></i>
                        Reports

                    </a>


                </nav>

            </div>


            <a href="../logout.php"
               class="logout-link">

                <i class="fa-solid fa-right-from-bracket"></i>
                Log Out

            </a>


        </aside>



        <!-- Main Workspace -->

        <main class="main-workspace">


            <div class="page-header">


                <div>

                    <h2>
                        Warden Dashboard
                    </h2>

                    <p>
                        Overview of today's mess menu, active complaints, and quick controls.
                    </p>

                </div>


                <div class="header-actions">


                    <span class="shift-badge">

                        <i class="fa-solid fa-clock"
                           style="color: #3182ce;"></i>

                        Active Slot: Lunch

                    </span>


                    <div class="user-profile">

                        <i class="fa-solid fa-user-gear"
                           style="color: #2c3e50;"></i>

                        Mess Manager

                    </div>


                </div>


            </div>



            <!-- Stats Overview -->

            <div class="stats-grid">


                <div class="stat-card">


                    <div class="stat-icon"
                         style="background: #ebf8ff; color: #3182ce;">

                        <i class="fa-solid fa-users"></i>

                    </div>


                    <div class="stat-info">

                        <h3>
                            120
                        </h3>

                        <p>
                            Mess Students
                        </p>

                    </div>


                </div>



                <div class="stat-card">


                    <div class="stat-icon"
                         style="background: #e6fffa; color: #319795;">

                        <i class="fa-solid fa-square-check"></i>

                    </div>


                    <div class="stat-info">

                        <h3>
                            94%
                        </h3>

                        <p>
                            Today's Attendance
                        </p>

                    </div>


                </div>



                <div class="stat-card">


                    <div class="stat-icon"
                         style="background: #feebc8; color: #dd6b20;">

                        <i class="fa-solid fa-clock-rotate-left"></i>

                    </div>


                    <div class="stat-info">

                        <h3>
                            3
                        </h3>

                        <p>
                            Pending Feedback
                        </p>

                    </div>


                </div>



                <div class="stat-card">


                    <div class="stat-icon"
                         style="background: #faf5ff; color: #805ad5;">

                        <i class="fa-solid fa-star"></i>

                    </div>


                    <div class="stat-info">

                        <h3>
                            4.2 / 5
                        </h3>

                        <p>
                            Average Rating
                        </p>

                    </div>


                </div>


            </div>



            <div class="content-grid">


                <!-- Left Column: Today's Menu & Quick Actions -->

                <div>


                    <div class="card-panel">


                        <div class="card-header">


                            <div class="card-title">

                                <i class="fa-solid fa-utensils"
                                   style="color: #2c3e50;"></i>

                                Today's Schedule

                            </div>


                            <a href="menu.php"
                               class="view-link">

                                Edit Menu →

                            </a>


                        </div>



                        <div class="meal-list">


                            <div class="meal-item breakfast">


                                <div class="meal-info">

                                    <h4>
                                        Breakfast
                                    </h4>

                                    <p>
                                        Puri Bhaji, Fresh Bananas, Tea / Coffee
                                    </p>

                                </div>


                                <div class="meal-time">
                                    08:00 AM
                                </div>


                            </div>



                            <div class="meal-item lunch">


                                <div class="meal-info">

                                    <h4>
                                        Lunch
                                    </h4>

                                    <p>
                                        Steam Rice, Dal Tadka, Chapati, Veg Sabzi, Curd
                                    </p>

                                </div>


                                <div class="meal-time">
                                    01:00 PM
                                </div>


                            </div>



                            <div class="meal-item dinner">


                                <div class="meal-info">

                                    <h4>
                                        Dinner
                                    </h4>

                                    <p>
                                        Paneer Butter Masala, Roti, Jeera Rice, Gulab Jamun
                                    </p>

                                </div>


                                <div class="meal-time">
                                    08:00 PM
                                </div>


                            </div>


                        </div>


                    </div>



                    <!-- =================================================
                         NEW: MEAL SKIP REQUESTS
                         Uses the same existing card-panel design.
                         ================================================= -->

                    <div class="card-panel">


                        <div class="card-header">


                            <div class="card-title">

                                <i class="fa-solid fa-person-walking-arrow-right"
                                   style="color: #2c3e50;"></i>

                                Meal Skip Requests

                            </div>


                            <a href="<?php echo htmlspecialchars($meal_skip_page); ?>"
                               class="view-link">

                                View All →

                            </a>


                        </div>



                        <div class="meal-list">


                            <!-- Active Requests -->

                            <div class="meal-item"
                                 style="border-left-color: #3182ce;">


                                <div class="meal-info">

                                    <h4>
                                        Active Requests
                                    </h4>

                                    <p>
                                        Students currently skipping meals
                                    </p>

                                </div>


                                <div class="meal-time">

                                    <?php
                                    echo $total_skip_requests;
                                    ?>

                                </div>


                            </div>



                            <!-- Breakfast -->

                            <div class="meal-item breakfast">


                                <div class="meal-info">

                                    <h4>
                                        Breakfast
                                    </h4>

                                    <p>
                                        Students skipping breakfast
                                    </p>

                                </div>


                                <div class="meal-time">

                                    <?php
                                    echo $breakfast_skip_count;
                                    ?>

                                </div>


                            </div>



                            <!-- Lunch -->

                            <div class="meal-item lunch">


                                <div class="meal-info">

                                    <h4>
                                        Lunch
                                    </h4>

                                    <p>
                                        Students skipping lunch
                                    </p>

                                </div>


                                <div class="meal-time">

                                    <?php
                                    echo $lunch_skip_count;
                                    ?>

                                </div>


                            </div>



                            <!-- Dinner -->

                            <div class="meal-item dinner">


                                <div class="meal-info">

                                    <h4>
                                        Dinner
                                    </h4>

                                    <p>
                                        Students skipping dinner
                                    </p>

                                </div>


                                <div class="meal-time">

                                    <?php
                                    echo $dinner_skip_count;
                                    ?>

                                </div>


                            </div>


                        </div>


                    </div>



                    <div class="card-panel">


                        <div class="card-title"
                             style="margin-bottom: 15px;">

                            <i class="fa-solid fa-bolt"
                               style="color: #2c3e50;"></i>

                            Quick Actions

                        </div>


                        <div class="action-buttons">


                            <a href="menu.php"
                               class="btn-action">

                                <i class="fa-solid fa-pen-to-square"></i>

                                Update Menu

                            </a>


                            <a href="announcements.php"
                               class="btn-action">

                                <i class="fa-solid fa-bullhorn"></i>

                                Post Notice

                            </a>


                        </div>


                    </div>


                </div>



                <!-- Right Column: Recent Complaints -->

                <div>


                    <div class="card-panel">


                        <div class="card-header">


                            <div class="card-title">

                                <i class="fa-solid fa-comment-dots"
                                   style="color: #2c3e50;"></i>

                                Recent Complaints

                            </div>


                            <a href="complaints.php"
                               class="view-link">

                                View All →

                            </a>


                        </div>



                        <div class="complaint-list">


                            <div class="complaint-item">


                                <div class="complaint-header">


                                    <span class="student-name">
                                        Blessen
                                    </span>


                                    <span class="badge-status status-pending">
                                        Pending
                                    </span>


                                </div>


                                <p class="complaint-text">
                                    Salt was low in dinner menu today.
                                </p>


                            </div>



                            <div class="complaint-item">


                                <div class="complaint-header">


                                    <span class="student-name">
                                        Sarah Jenkins
                                    </span>


                                    <span class="badge-status status-resolved">
                                        Resolved
                                    </span>


                                </div>


                                <p class="complaint-text">
                                    Dining tables were cleaned promptly today, thank you!
                                </p>


                            </div>


                        </div>


                    </div>


                </div>


            </div>


        </main>


    </div>


</body>

</html>