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


/* =================================================
   STUDENT NAME
================================================= */

$student_name = $_SESSION['name']
    ?? $_SESSION['student_name']
    ?? $_SESSION['username']
    ?? 'Student';


/* =================================================
   SUMMARY COUNTS
================================================= */

$total_ratings = 0;
$total_skips = 0;
$total_feedback = 0;


/* -------------------------------------------------
   TOTAL MEAL RATINGS
------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM food_item_ratings
    WHERE user_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $total_ratings = (int)($row['total'] ?? 0);

    $stmt->close();
}


/* -------------------------------------------------
   TOTAL MEAL SKIP REQUESTS
------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM meal_skips
    WHERE student_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $total_skips = (int)($row['total'] ?? 0);

    $stmt->close();
}


/* -------------------------------------------------
   TOTAL FEEDBACK
------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE user_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $total_feedback = (int)($row['total'] ?? 0);

    $stmt->close();
}


/* =================================================
   RECENT MEAL RATINGS
================================================= */

$ratings = [];

$stmt = $conn->prepare("
    SELECT serving_date, meal_type, food_item, rating, comment, created_at
    FROM food_item_ratings
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }

    $stmt->close();
}


/* =================================================
   RECENT MEAL SKIP REQUESTS
================================================= */

$meal_skips = [];

$stmt = $conn->prepare("
    SELECT
        id,
        start_date,
        end_date,
        breakfast,
        lunch,
        dinner,
        reason,
        status
    FROM meal_skips
    WHERE student_id = ?
    ORDER BY id DESC
    LIMIT 5
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $meal_skips[] = $row;
    }

    $stmt->close();
}


/* =================================================
   RECENT FEEDBACK
================================================= */

$feedback = [];

$stmt = $conn->prepare("
    SELECT category, message, status, created_at
    FROM feedback
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $feedback[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Activity - Student | SmartMess</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }


        body {
            background: #dce7f0;
            color: #1e2b37;
            min-height: 100vh;
        }


        /* =========================
           MAIN CONTENT
        ========================= */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 30px 35px;
        }


        /* =========================
           TOP BAR
        ========================= */

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }


        .search-box {
            position: relative;
            width: 320px;
        }


        .search-box input {
            width: 100%;
            padding: 11px 16px 11px 42px;
            border: none;
            outline: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.7);
            color: #1e2b37;
            font-size: 13px;
        }


        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 13px;
        }


        .top-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.7);
            color: #3a5ba0;
            display: flex;
            align-items: center;
            justify-content: center;
        }


        .user-pill {
            display: flex;
            align-items: center;
            gap: 9px;
            background: rgba(255, 255, 255, 0.75);
            padding: 6px 15px 6px 7px;
            border-radius: 22px;
            font-size: 13px;
            font-weight: 700;
        }


        .avatar {
            width: 29px;
            height: 29px;
            border-radius: 50%;
            background: #3a5ba0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }


        /* =========================
           PAGE HEADER
        ========================= */

        .page-header {
            background: #eef5fb;
            border-radius: 22px;
            padding: 24px 28px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }


        .page-header h1 {
            font-size: 23px;
            font-weight: 800;
            color: #1e2b37;
        }


        .page-header p {
            margin-top: 5px;
            color: #5a6e85;
            font-size: 12px;
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
        }


        .dashboard-btn:hover {
            background: #2d4880;
        }


        /* =========================
           SUMMARY CARDS
        ========================= */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }


        .summary-card {
            background: #eef5fb;
            border-radius: 18px;
            padding: 21px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }


        .summary-icon {
            width: 47px;
            height: 47px;
            border-radius: 13px;
            background: #cbe3f7;
            color: #3a5ba0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }


        .summary-number {
            font-size: 23px;
            font-weight: 800;
            color: #1e2b37;
        }


        .summary-label {
            margin-top: 3px;
            color: #5a6e85;
            font-size: 11px;
            font-weight: 700;
        }


        /* =========================
           ACTIVITY GRID
        ========================= */

        .activity-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }


        .activity-card {
            background: #eef5fb;
            border-radius: 20px;
            padding: 23px 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }


        .activity-card.full-width {
            grid-column: 1 / -1;
        }


        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 2px solid rgba(58, 91, 160, 0.1);
        }


        .card-title i {
            color: #3a5ba0;
            font-size: 15px;
        }


        .card-title h2 {
            font-size: 16px;
            font-weight: 800;
            color: #1e2b37;
        }


        /* =========================
           ACTIVITY ITEM
        ========================= */

        .activity-item {
            display: flex;
            gap: 13px;
            padding: 16px 0;
            border-bottom: 1px solid #d9e4ed;
        }


        .activity-item:last-child {
            border-bottom: none;
        }


        .activity-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 10px;
            background: #ffffff;
            color: #3a5ba0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }


        .activity-details {
            flex: 1;
        }


        .activity-name {
            font-size: 13px;
            font-weight: 800;
            color: #1e2b37;
        }


        .activity-info {
            margin-top: 4px;
            font-size: 11px;
            color: #5a6e85;
            line-height: 1.5;
        }


        .activity-date {
            margin-top: 5px;
            font-size: 10px;
            color: #8a99a8;
        }


        /* =========================
           STARS
        ========================= */

        .stars {
            margin-top: 6px;
            color: #e5a900;
            font-size: 11px;
        }


        /* =========================
           MEAL TAGS
        ========================= */

        .meal-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 7px;
        }


        .meal-tag {
            background: #cbe3f7;
            color: #3a5ba0;
            border-radius: 10px;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: 800;
        }


        /* =========================
           STATUS
        ========================= */

        .status {
            display: inline-block;
            margin-top: 7px;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
        }


        .status-active {
            background: #dcfce7;
            color: #15803d;
        }


        .status-pending {
            background: #fff4d6;
            color: #a46b00;
        }


        .status-progress {
            background: #dbeafe;
            color: #315c9b;
        }


        .status-resolved {
            background: #dcfce7;
            color: #15803d;
        }


        .status-other {
            background: #e5e7eb;
            color: #4b5563;
        }


        /* =========================
           EMPTY STATE
        ========================= */

        .empty-state {
            text-align: center;
            padding: 30px 10px 20px;
            color: #8a99a8;
        }


        .empty-state i {
            font-size: 25px;
            margin-bottom: 9px;
        }


        .empty-state p {
            font-size: 11px;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .activity-grid {
                grid-template-columns: 1fr;
            }

            .activity-card.full-width {
                grid-column: auto;
            }

            .main-content {
                margin-left: 250px;
                padding: 22px;
            }

        }

    </style>

</head>


<body>


<?php include "../includes/student_sidebar.php"; ?>


<main class="main-content">


    <!-- =========================
         TOP BAR
    ========================= -->

    <div class="top-bar">

        <div></div>

    </div>



    <!-- =========================
         PAGE HEADER
    ========================= -->

    <div class="page-header">

        <div>

            <h1>My Activity</h1>

            <p>
                View your recent activity and interactions in SmartMess
            </p>

        </div>


        <a href="dashboard.php" class="dashboard-btn">

            <i class="fa-solid fa-arrow-left"></i>

            Dashboard

        </a>

    </div>



    <!-- =========================
         SUMMARY
    ========================= -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-icon">

                <i class="fa-solid fa-star"></i>

            </div>

            <div>

                <div class="summary-number">

                    <?php echo $total_ratings; ?>

                </div>

                <div class="summary-label">

                    Meal Ratings

                </div>

            </div>

        </div>



        <div class="summary-card">

            <div class="summary-icon">

                <i class="fa-solid fa-calendar-xmark"></i>

            </div>

            <div>

                <div class="summary-number">

                    <?php echo $total_skips; ?>

                </div>

                <div class="summary-label">

                    Meal Skip Requests

                </div>

            </div>

        </div>



        <div class="summary-card">

            <div class="summary-icon">

                <i class="fa-solid fa-comment-dots"></i>

            </div>

            <div>

                <div class="summary-number">

                    <?php echo $total_feedback; ?>

                </div>

                <div class="summary-label">

                    Feedback Submitted

                </div>

            </div>

        </div>


    </div>



    <!-- =========================
         ACTIVITY SECTIONS
    ========================= -->

    <div class="activity-grid">


        <!-- =================================
             RECENT RATINGS
        ================================== -->

        <div class="activity-card">

            <div class="card-title">

                <i class="fa-solid fa-star"></i>

                <h2>Recent Food Ratings</h2>

            </div>


            <?php if (!empty($ratings)): ?>

                <?php foreach ($ratings as $rating): ?>

                    <div class="activity-item">


                        <div class="activity-icon">

                            <i class="fa-solid fa-star"></i>

                        </div>


                        <div class="activity-details">

                            <div class="activity-name">

                                <?php
                                echo htmlspecialchars(
                                    $rating['food_item'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </div>


                            <div class="stars">

                                <?php

                                $rating_value =
                                    (int)$rating['rating'];

                                for ($i = 1; $i <= 5; $i++) {

                                    if ($i <= $rating_value) {

                                        echo '<i class="fa-solid fa-star"></i>';

                                    } else {

                                        echo '<i class="fa-regular fa-star"></i>';

                                    }

                                }

                                ?>

                            </div>

                            <div class="activity-info">
                                <?= htmlspecialchars($rating['meal_type'], ENT_QUOTES, 'UTF-8') ?>
                                | Serving date:
                                <?= htmlspecialchars(date('d M Y', strtotime($rating['serving_date'])), ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <?php if (!empty($rating['comment'])): ?>

                                <div class="activity-info">

                                    <?php
                                    echo htmlspecialchars(
                                        $rating['comment'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                            <div class="activity-date">

                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $rating['created_at']
                                    )
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-regular fa-star"></i>

                    <p>No food ratings submitted yet.</p>

                </div>

            <?php endif; ?>

        </div>



        <!-- =================================
             MEAL SKIPS
        ================================== -->

        <div class="activity-card">

            <div class="card-title">

                <i class="fa-solid fa-calendar-xmark"></i>

                <h2>Recent Meal Skips</h2>

            </div>


            <?php if (!empty($meal_skips)): ?>

                <?php foreach ($meal_skips as $skip): ?>

                    <div class="activity-item">


                        <div class="activity-icon">

                            <i class="fa-solid fa-calendar-minus"></i>

                        </div>


                        <div class="activity-details">

                            <div class="activity-name">

                                Meal Skip Request

                            </div>


                            <div class="activity-info">

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $skip['start_date']
                                    )
                                );

                                ?>

                                →

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $skip['end_date']
                                    )
                                );

                                ?>

                            </div>


                            <!-- Selected Meals -->

                            <div class="meal-tags">

                                <?php if ((int)$skip['breakfast'] === 1): ?>

                                    <span class="meal-tag">
                                        Breakfast
                                    </span>

                                <?php endif; ?>


                                <?php if ((int)$skip['lunch'] === 1): ?>

                                    <span class="meal-tag">
                                        Lunch
                                    </span>

                                <?php endif; ?>


                                <?php if ((int)$skip['dinner'] === 1): ?>

                                    <span class="meal-tag">
                                        Dinner
                                    </span>

                                <?php endif; ?>

                            </div>


                            <!-- Reason -->

                            <?php if (!empty($skip['reason'])): ?>

                                <div class="activity-info">

                                    <?php
                                    echo htmlspecialchars(
                                        $skip['reason']
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                            <!-- Status -->

                            <?php

                            $skip_status =
                                strtolower(
                                    trim(
                                        $skip['status'] ?? ''
                                    )
                                );

                            if ($skip_status === 'active') {

                                $skip_class =
                                    'status-active';

                            } elseif ($skip_status === 'pending') {

                                $skip_class =
                                    'status-pending';

                            } else {

                                $skip_class =
                                    'status-other';

                            }

                            ?>


                            <span class="status <?php echo $skip_class; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $skip['status']
                                );
                                ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-calendar-check"></i>

                    <p>No meal skip requests yet.</p>

                </div>

            <?php endif; ?>

        </div>



        <!-- =================================
             RECENT FEEDBACK
        ================================== -->

        <div class="activity-card full-width">

            <div class="card-title">

                <i class="fa-solid fa-comment-dots"></i>

                <h2>Recent Feedback</h2>

            </div>


            <?php if (!empty($feedback)): ?>

                <?php foreach ($feedback as $item): ?>

                    <div class="activity-item">


                        <div class="activity-icon">

                            <i class="fa-solid fa-comment"></i>

                        </div>


                        <div class="activity-details">

                            <div class="activity-name">

                                <?php
                                echo htmlspecialchars(
                                    $item['category']
                                );
                                ?>

                            </div>


                            <div class="activity-info">

                                <?php
                                echo htmlspecialchars(
                                    $item['message']
                                );
                                ?>

                            </div>


                            <?php

                            $feedback_status =
                                strtolower(
                                    trim(
                                        $item['status'] ?? ''
                                    )
                                );


                            if ($feedback_status === 'pending') {

                                $feedback_class =
                                    'status-pending';

                            } elseif (
                                $feedback_status === 'in progress'
                            ) {

                                $feedback_class =
                                    'status-progress';

                            } elseif (
                                $feedback_status === 'resolved'
                            ) {

                                $feedback_class =
                                    'status-resolved';

                            } else {

                                $feedback_class =
                                    'status-other';

                            }

                            ?>


                            <span
                                class="status
                                <?php echo $feedback_class; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $item['status']
                                );
                                ?>

                            </span>


                            <div class="activity-date">

                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $item['created_at']
                                    )
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-regular fa-comment-dots"></i>

                    <p>No feedback submitted yet.</p>

                </div>

            <?php endif; ?>

        </div>


    </div>

</main>


</body>

</html>