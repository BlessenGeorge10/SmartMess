```php
<?php
session_start();

// Ensure user is logged in as a student
if (
    !isset($_SESSION['logged_in']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../login.php");
    exit;
}

$student_display_name = $_SESSION['username'] ?? 'John Student';
$student_full_name = $_SESSION['name'] ?? $student_display_name;

require_once "../db_config.php";

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Safely display database text
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================
   AVERAGE MEAL RATING
========================= */

$average_rating = 0;
$rating_count = 0;

if ($user_id > 0) {
    $stmt = $conn->prepare(
        "SELECT AVG(rating) AS average_rating, COUNT(*) AS rating_count
         FROM food_item_ratings
         WHERE user_id = ?"
    );

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $rating_data = $stmt->get_result()->fetch_assoc();

        $average_rating = (float)($rating_data['average_rating'] ?? 0);
        $rating_count = (int)($rating_data['rating_count'] ?? 0);

        $stmt->close();
    }
}

$rounded_rating = (int)round($average_rating);

/* =========================
   FEEDBACK STATUS COUNTS
========================= */

$pending_feedback = 0;
$in_progress_feedback = 0;
$resolved_feedback = 0;

if ($user_id > 0) {
    $feedback_stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total
         FROM feedback
         WHERE user_id = ?
         GROUP BY status"
    );

    if ($feedback_stmt) {
        $feedback_stmt->bind_param("i", $user_id);
        $feedback_stmt->execute();
        $feedback_result = $feedback_stmt->get_result();

        while ($row = $feedback_result->fetch_assoc()) {
            if ($row['status'] === 'Pending') {
                $pending_feedback = (int)$row['total'];
            } elseif ($row['status'] === 'In Progress') {
                $in_progress_feedback = (int)$row['total'];
            } elseif ($row['status'] === 'Resolved') {
                $resolved_feedback = (int)$row['total'];
            }
        }

        $feedback_stmt->close();
    }
}

/* =========================
   LATEST ANNOUNCEMENT
========================= */

$latest_announcement = null;

$announcement_result = $conn->query(
    "SELECT id, title, audience, content, author, created_at
     FROM announcements
     WHERE audience IN ('Students', 'Everyone', 'All Students', 'Hostel A', 'Hostel B')
     ORDER BY created_at DESC
     LIMIT 1"
);

if ($announcement_result && $announcement_result->num_rows > 0) {
    $latest_announcement = $announcement_result->fetch_assoc();
}

/* =========================
   UNREAD NOTIFICATION COUNT
========================= */

$unread_notifications = 0;

if ($user_id > 0) {
    $notification_stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM notifications
         WHERE recipient_role = 'student'
           AND is_read = 0
           AND (recipient_id IS NULL OR recipient_id = ?)"
    );

    if ($notification_stmt) {
        $notification_stmt->bind_param("i", $user_id);
        $notification_stmt->execute();

        $notification_data = $notification_stmt->get_result()->fetch_assoc();
        $unread_notifications = (int)($notification_data['total'] ?? 0);

        $notification_stmt->close();
    }
}

/* =========================
   FORMAT ANNOUNCEMENT DATE
========================= */

$announcement_date = '';

if ($latest_announcement && !empty($latest_announcement['created_at'])) {
    $timestamp = strtotime($latest_announcement['created_at']);

    if ($timestamp !== false) {
        $announcement_date = date('d M Y • h:i A', $timestamp);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Student | SmartMess</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --navy: #2c3e50;
            --deep-navy: #1e2b37;
            --cyan: #38bdf8;
            --cyan-dark: #0284c7;
            --cyan-light: #e0f5ff;
            --white: #ffffff;
            --background: #f0f2f8;
            --light-grey: #f8fafc;
            --border: #e2e8f0;
            --text: #263445;
            --muted: #718096;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc4545;
            --shadow: 0 8px 25px rgba(30, 43, 55, 0.06);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont,
                         Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--background);
            color: var(--text);
            overflow-x: hidden;
        }

        .app-window {
            width: 100%;
            min-height: 100vh;
            display: flex;
            background: var(--background);
        }

        .main-workspace {
            flex: 1;
            min-width: 0;
            margin-left: 250px;
            padding: 28px 32px 35px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            overflow-y: auto;
        }

        /* TOP BAR */

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 10;
        }

        .search-box {
            position: relative;
            width: min(360px, 45%);
        }

        .search-box input {
            width: 100%;
            height: 44px;
            padding: 0 16px 0 44px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
            color: var(--deep-navy);
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-box input:focus {
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, .15);
        }

        .search-box input::placeholder {
            color: #94a3b8;
        }

        .search-box > i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cyan-dark);
            font-size: 14px;
        }

        .top-user-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .icon-btn {
            position: relative;
            width: 42px;
            height: 42px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 16px;
            text-decoration: none;
            transition: background .2s, color .2s, transform .2s;
        }

        .icon-btn:hover {
            background: var(--cyan-light);
            color: var(--cyan-dark);
            transform: translateY(-1px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 19px;
            height: 19px;
            padding: 0 4px;
            border-radius: 20px;
            background: var(--danger);
            color: var(--white);
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--background);
        }

        .notification-wrapper {
            position: relative;
        }

        .notification-preview {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 320px;
            max-width: calc(100vw - 30px);
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(30, 43, 55, .17);
            border: 1px solid var(--border);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-5px);
            transition: all .2s ease;
        }

        .notification-wrapper:hover .notification-preview,
        .notification-wrapper:focus-within .notification-preview,
        .notification-wrapper.open .notification-preview {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-preview::before {
            content: "";
            position: absolute;
            top: -7px;
            right: 15px;
            width: 12px;
            height: 12px;
            background: var(--white);
            transform: rotate(45deg);
            border-left: 1px solid var(--border);
            border-top: 1px solid var(--border);
        }

        .notification-preview-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 17px;
            border-bottom: 1px solid var(--border);
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }

        .notification-preview-header i {
            color: var(--cyan-dark);
        }

        .notification-preview-content {
            padding: 15px 17px 10px;
            overflow-wrap: anywhere;
        }

        .notification-preview-content h4 {
            font-size: 14px;
            font-weight: 800;
            color: var(--deep-navy);
            margin-bottom: 6px;
        }

        .notification-date {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 9px;
        }

        .notification-message {
            font-size: 12px;
            color: #526174;
            line-height: 1.7;
        }

        .notification-view {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 7px;
            padding: 10px 17px 15px;
            color: var(--cyan-dark);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }

        .notification-view:hover {
            color: var(--navy);
        }

        .user-menu-wrapper {
            position: relative;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            padding: 6px 14px 6px 7px;
            border: 1px solid var(--border);
            border-radius: 13px;
            font-size: 13px;
            font-weight: 700;
            color: var(--deep-navy);
            cursor: pointer;
            transition: background .2s;
        }

        .user-pill:hover {
            background: var(--cyan-light);
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            background: var(--navy);
            color: var(--white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 245px;
            background: var(--white);
            border-radius: 15px;
            padding: 8px;
            box-shadow: 0 12px 35px rgba(30, 43, 55, .16);
            border: 1px solid var(--border);
            display: none;
            z-index: 2000;
        }

        .profile-dropdown.show {
            display: block;
            animation: dropdownIn .18s ease;
        }

        @keyframes dropdownIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-header {
            padding: 13px 12px 14px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 5px;
        }

        .profile-header-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--deep-navy);
            overflow-wrap: anywhere;
        }

        .profile-header-role {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 600;
        }

        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 11px 12px;
            border-radius: 9px;
            color: #334155;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background .2s, color .2s;
        }

        .profile-menu-item:hover {
            background: var(--cyan-light);
            color: var(--navy);
        }

        .profile-menu-item i {
            width: 18px;
            text-align: center;
            color: var(--cyan-dark);
            font-size: 14px;
        }

        .profile-menu-divider {
            height: 1px;
            background: var(--border);
            margin: 5px 4px;
        }

        .profile-logout,
        .profile-logout i {
            color: var(--danger);
        }

        .profile-logout:hover {
            background: #fff1f1;
            color: var(--danger);
        }

        /* DASHBOARD GRID */

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(280px, .9fr);
            gap: 24px;
            align-items: start;
        }

        .left-col,
        .right-col {
            display: flex;
            flex-direction: column;
            gap: 22px;
            min-width: 0;
        }

        /* WELCOME BANNER */

        .welcome-banner {
            position: relative;
            overflow: hidden;
            background: linear-gradient(125deg, var(--deep-navy), var(--navy));
            border-radius: var(--radius);
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 12px 28px rgba(30, 43, 55, .13);
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -70px;
            top: -105px;
            border: 35px solid rgba(56, 189, 248, .10);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-text {
            position: relative;
            z-index: 1;
        }

        .welcome-text h1 {
            font-size: clamp(23px, 2.5vw, 30px);
            color: var(--white);
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -.5px;
            overflow-wrap: anywhere;
        }

        .welcome-text p {
            font-size: 13px;
            color: #d4e1ec;
            line-height: 1.7;
            margin-bottom: 18px;
            max-width: 440px;
        }

        .banner-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            color: var(--deep-navy);
            background: var(--cyan);
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 10px;
            transition: background .2s, transform .2s;
        }

        .banner-link:hover {
            background: #7dd3fc;
            transform: translateY(-1px);
        }

        .banner-icon {
            position: relative;
            z-index: 1;
            width: 76px;
            height: 76px;
            flex-shrink: 0;
            background: rgba(56, 189, 248, .15);
            border: 1px solid rgba(56, 189, 248, .35);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cyan);
            font-size: 34px;
        }

        /* GENERAL CARDS */

        .stats-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .panel-card,
        .announcement-box,
        .today-menu-panel,
        .meal-skip-card {
            background: var(--white);
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: var(--radius);
            padding: 23px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .panel-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--deep-navy);
        }

        .panel-header a {
            color: var(--cyan-dark) !important;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .panel-header a:hover {
            color: var(--navy) !important;
            text-decoration: underline;
        }

        /* FEEDBACK AND RATING */

        .rating-summary {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            padding: 8px 0 4px;
        }

        .rating-score {
            font-size: 31px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -1px;
        }

        .rating-stars {
            color: #f5b82e;
            font-size: 18px;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .rating-text {
            font-size: 10px;
            color: var(--muted);
            margin-top: 5px;
            line-height: 1.5;
        }

        /* ANNOUNCEMENTS */

        .announcement-box {
            display: block;
            color: inherit;
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .announcement-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 28px rgba(30, 43, 55, .09);
            border-color: #bae6fd;
        }

        .announcement-item {
            display: flex;
            gap: 13px;
            align-items: flex-start;
            margin-top: 15px;
            padding: 15px;
            background: var(--light-grey);
            border-radius: 13px;
            border: 1px solid #edf2f7;
        }

        .announcement-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            background: var(--cyan-light);
            color: var(--cyan-dark);
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .announcement-content {
            min-width: 0;
        }

        .announcement-content h6 {
            font-size: 13px;
            font-weight: 800;
            color: var(--deep-navy);
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .announcement-content p {
            font-size: 11px;
            color: var(--muted);
            margin-top: 6px;
            line-height: 1.5;
        }

        /* TODAY'S MENU */

        .today-menu-panel {
            background: var(--white);
        }

        .menu-items-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 18px;
        }

        .menu-card {
            position: relative;
            background: var(--light-grey);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 16px;
            text-decoration: none;
            display: block;
            transition: transform .2s ease, box-shadow .2s ease,
                        border-color .2s ease, background .2s ease;
        }

        .menu-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(30, 43, 55, .08);
            border-color: var(--cyan);
        }

        .menu-card.active-meal {
            background: var(--deep-navy);
            border-color: var(--deep-navy);
            box-shadow: 0 7px 17px rgba(30, 43, 55, .12);
        }

        .menu-card.active-meal::before {
            content: "";
            position: absolute;
            top: 15px;
            bottom: 15px;
            left: 0;
            width: 3px;
            border-radius: 0 4px 4px 0;
            background: var(--cyan);
        }

        .menu-card-title {
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 7px;
            color: var(--deep-navy);
        }

        .active-meal .menu-card-title {
            color: var(--white);
        }

        .menu-card-desc {
            font-size: 11px;
            line-height: 1.7;
            color: #64748b;
        }

        .active-meal .menu-card-desc {
            color: #d4e1ec;
        }

        /* MEAL SKIP */

        .meal-skip-card {
            background: var(--white);
        }

        .meal-skip-info {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 18px;
        }

        .meal-skip-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            background: var(--cyan-light);
            border: 1px solid #bae6fd;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cyan-dark);
            font-size: 21px;
        }

        .meal-skip-text p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.7;
        }

        .meal-skip-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            background: var(--navy);
            color: var(--white);
            text-decoration: none;
            padding: 11px 15px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            transition: background .2s, transform .2s;
        }

        .meal-skip-button:hover {
            background: var(--cyan-dark);
            transform: translateY(-1px);
        }

        .empty-notification {
            font-size: 12px;
            line-height: 1.7;
            color: var(--muted);
        }

        /* SCROLLBAR */

        .main-workspace::-webkit-scrollbar {
            width: 7px;
        }

        .main-workspace::-webkit-scrollbar-track {
            background: transparent;
        }

        .main-workspace::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .main-workspace::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* RESPONSIVE */

        @media (max-width: 1200px) {
            .main-workspace {
                padding: 24px;
            }

            .dashboard-grid {
                grid-template-columns: minmax(0, 1.2fr) minmax(270px, .9fr);
                gap: 18px;
            }

            .left-col,
            .right-col {
                gap: 18px;
            }

            .stats-row {
                gap: 15px;
            }

            .panel-card,
            .announcement-box,
            .today-menu-panel,
            .meal-skip-card {
                padding: 19px;
            }
        }

        @media (max-width: 1000px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .main-workspace {
                gap: 20px;
            }

            .right-col {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }
        }

        @media (max-width: 700px) {
            .main-workspace {
                margin-left: 0;
                padding: 18px 15px 25px;
            }

            .top-bar {
                flex-wrap: wrap;
                gap: 14px;
            }

            .search-box {
                width: 100%;
                max-width: none;
            }

            .top-user-area {
                margin-left: auto;
            }

            .dashboard-grid {
                gap: 18px;
            }

            .left-col,
            .right-col {
                gap: 16px;
            }

            .right-col {
                grid-template-columns: 1fr;
            }

            .stats-row {
                gap: 14px;
            }

            .welcome-banner {
                padding: 23px 20px;
                border-radius: 16px;
            }

            .welcome-text h1 {
                font-size: 23px;
            }

            .welcome-text p {
                font-size: 12px;
            }

            .banner-icon {
                width: 55px;
                height: 55px;
                font-size: 24px;
                border-radius: 15px;
            }

            .panel-card,
            .announcement-box,
            .today-menu-panel,
            .meal-skip-card {
                padding: 19px;
                border-radius: 16px;
            }
        }

        @media (max-width: 480px) {
            .main-workspace {
                padding: 14px 12px 22px;
            }

            .top-user-area {
                gap: 9px;
            }

            .user-pill {
                max-width: 190px;
                gap: 7px;
                padding-right: 10px;
                font-size: 12px;
            }

            .user-pill > span {
                max-width: 105px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .notification-preview {
                position: fixed;
                top: 110px;
                right: 12px;
                width: 320px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .welcome-banner {
                padding: 21px 17px;
            }

            .welcome-text h1 {
                font-size: 21px;
            }

            .banner-icon {
                width: 46px;
                height: 46px;
                font-size: 21px;
            }

            .rating-summary {
                justify-content: space-between;
            }

            .panel-card,
            .announcement-box,
            .today-menu-panel,
            .meal-skip-card {
                padding: 17px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
<div class="app-window">

    <?php include "../includes/student_sidebar.php"; ?>

    <main class="main-workspace">

        <!-- TOP SEARCH AND USER BAR -->
        <div class="top-bar">

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       id="dashboardSearch"
                       placeholder="Search menu, feedback..."
                       onkeyup="searchDashboard()">
            </div>

            <div class="top-user-area">

                <!-- NOTIFICATION BELL -->
                <div class="notification-wrapper" id="notificationWrapper">

                    <a href="/smartmess/student/announcements.php"
                       class="icon-btn"
                       id="notificationBell"
                       title="<?php echo $unread_notifications > 0
                           ? e($unread_notifications . ' unread notifications')
                           : 'Announcements'; ?>"
                       aria-label="View announcements">

                        <i class="fa-solid fa-bell"></i>

                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge">
                                <?php echo $unread_notifications > 99
                                    ? '99+'
                                    : $unread_notifications; ?>
                            </span>
                        <?php endif; ?>

                    </a>

                    <!-- LATEST ANNOUNCEMENT PREVIEW -->
                    <div class="notification-preview">

                        <div class="notification-preview-header">
                            <i class="fa-solid fa-bullhorn"></i>
                            <span>Latest Announcement</span>
                        </div>

                        <div class="notification-preview-content">

                            <?php if ($latest_announcement): ?>

                                <h4>
                                    <?php echo e($latest_announcement['title']); ?>
                                </h4>

                                <p class="notification-date">
                                    <?php echo e($announcement_date); ?>
                                </p>

                                <p class="notification-message">
                                    <?php
                                    $announcement_content = trim(
                                        (string)$latest_announcement['content']
                                    );

                                    if (mb_strlen($announcement_content) > 180) {
                                        $announcement_content =
                                            mb_substr($announcement_content, 0, 180) . '...';
                                    }

                                    echo nl2br(e($announcement_content));
                                    ?>
                                </p>

                            <?php else: ?>

                                <p class="empty-notification">
                                    There are no announcements yet.
                                </p>

                            <?php endif; ?>

                            <?php if ($unread_notifications > 0): ?>
                                <p class="notification-date" style="margin-top:10px;">
                                    You have <?php echo $unread_notifications; ?>
                                    unread notification<?php echo $unread_notifications === 1 ? '' : 's'; ?>.
                                </p>
                            <?php endif; ?>

                        </div>

                        <a href="/smartmess/student/announcements.php"
                           class="notification-view">
                            View all announcements
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>
                </div>

                <!-- STUDENT PROFILE -->
                <div class="user-menu-wrapper">

                    <button type="button"
                            class="user-pill"
                            id="profileButton"
                            title="Student Profile"
                            onclick="toggleProfileMenu()">

                        <div class="user-avatar">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>

                        <span><?php echo e($student_display_name); ?></span>
                    </button>

                    <div class="profile-dropdown" id="profileDropdown">

                        <div class="profile-header">
                            <div class="profile-header-name">
                                <?php echo e($student_full_name); ?>
                            </div>
                            <div class="profile-header-role">Student</div>
                        </div>

                        <a href="/smartmess/student/profile.php"
                           class="profile-menu-item">
                            <i class="fa-solid fa-user"></i>
                            <span>My Profile</span>
                        </a>

                        <a href="/smartmess/student/change-password.php"
                           class="profile-menu-item">
                            <i class="fa-solid fa-lock"></i>
                            <span>Change Password</span>
                        </a>

                        <a href="/smartmess/student/myactivity.php"
                           class="profile-menu-item">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>My Activity</span>
                        </a>

                        <div class="profile-menu-divider"></div>

                        <a href="/smartmess/logout.php"
                           class="profile-menu-item profile-logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Log Out</span>
                        </a>

                    </div>
                </div>
            </div>
        </div>

        <!-- DASHBOARD CONTENT -->
        <div class="dashboard-grid">

            <!-- LEFT COLUMN -->
            <div class="left-col">

                <div class="welcome-banner">
                    <div class="welcome-text">
                        <h1>Hello <?php echo e($student_display_name); ?>!</h1>
                        <p>
                            Your feedback helps us improve our mess services.
                            Have a great day!
                        </p>
                        <a href="/smartmess/student/rating.php" class="banner-link">
                            Rate today's meal
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="banner-icon">
                        <i class="fa-solid fa-bowl-rice"></i>
                    </div>
                </div>

                <!-- FEEDBACK AND RATING -->
                <div class="stats-row">

                    <div class="panel-card">
                        <div class="panel-header">
                            <span class="panel-title">Feedback Status</span>
                            <a href="/smartmess/student/complaints.php">View</a>
                        </div>

                        <div style="display:flex;justify-content:space-around;align-items:center;padding:8px 0 2px;">

                            <div style="text-align:center;flex:1;">
                                <div style="font-size:25px;font-weight:800;color:#d97706;">
                                    <?php echo $pending_feedback; ?>
                                </div>
                                <div style="font-size:10px;color:#718096;margin-top:3px;font-weight:700;">
                                    Pending
                                </div>
                            </div>

                            <div style="width:1px;height:35px;background:#e2e8f0;"></div>

                            <div style="text-align:center;flex:1;">
                                <div style="font-size:25px;font-weight:800;color:#16a34a;">
                                    <?php echo $resolved_feedback; ?>
                                </div>
                                <div style="font-size:10px;color:#718096;margin-top:3px;font-weight:700;">
                                    Resolved
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="panel-card">
                        <div class="panel-header">
                            <span class="panel-title">Average Meal Rating</span>
                            <a href="/smartmess/student/rating.php">View</a>
                        </div>

                        <div class="rating-summary">
                            <div>
                                <div class="rating-score">
                                    <?php
                                    echo $rating_count > 0
                                        ? number_format($average_rating, 1) . '/5'
                                        : '—';
                                    ?>
                                </div>

                                <div class="rating-text">
                                    <?php
                                    echo $rating_count > 0
                                        ? 'Based on ' . $rating_count . ' rating' .
                                            ($rating_count === 1 ? '' : 's')
                                        : 'No ratings submitted yet';
                                    ?>
                                </div>
                            </div>

                            <div class="rating-stars">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $rating_count > 0 && $i <= $rounded_rating
                                        ? '★'
                                        : '☆';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ANNOUNCEMENTS -->
                <a href="/smartmess/student/announcements.php"
                   class="announcement-box">

                    <div class="panel-header" style="margin-bottom:0;">
                        <span class="panel-title">Announcements</span>
                        <span style="font-size:11px;font-weight:700;color:#718096;">
                            View all
                        </span>
                    </div>

                    <div class="announcement-item">
                        <i class="fa-solid fa-bullhorn announcement-icon"></i>

                        <div class="announcement-content">

                            <?php if ($latest_announcement): ?>

                                <h6><?php echo e($latest_announcement['title']); ?></h6>

                                <p><?php echo e($announcement_date); ?></p>

                            <?php else: ?>

                                <h6>No announcements yet</h6>
                                <p>New mess announcements will appear here.</p>

                            <?php endif; ?>

                        </div>
                    </div>
                </a>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="right-col">

                <!-- TODAY'S MENU -->
                <div class="today-menu-panel">

                    <div class="panel-header" style="margin-bottom:0;">
                        <span class="panel-title">Today's Menu</span>

                        <a href="/smartmess/student/meal-schedule.php"
                           style="color:#718096 !important;">
                            Today
                        </a>
                    </div>

                    <div class="menu-items-stack">

                        <a href="/smartmess/student/meal-schedule.php"
                           class="menu-card active-meal">
                            <div class="menu-card-title">Breakfast (08:00 AM)</div>
                            <div class="menu-card-desc">
                                Idli, Sambar, Fresh Chutney, Tea/Coffee
                            </div>
                        </a>

                        <a href="/smartmess/student/meal-schedule.php"
                           class="menu-card">
                            <div class="menu-card-title">Lunch (01:00 PM)</div>
                            <div class="menu-card-desc">
                                Rice, Dal Fry, Chapati, Special Veg Curry
                            </div>
                        </a>

                        <a href="/smartmess/student/meal-schedule.php"
                           class="menu-card">
                            <div class="menu-card-title">Dinner (08:00 PM)</div>
                            <div class="menu-card-desc">
                                Paneer Butter Masala, Roti, Salad
                            </div>
                        </a>

                    </div>
                </div>

                <!-- MEAL SKIP -->
                <div class="meal-skip-card">

                    <div class="panel-header" style="margin-bottom:0;">
                        <span class="panel-title">Meal Skip</span>
                        <span style="font-size:11px;font-weight:700;color:#718096;">
                            Food Wastage
                        </span>
                    </div>

                    <div class="meal-skip-info">
                        <div class="meal-skip-icon">
                            <i class="fa-solid fa-person-walking-arrow-right"></i>
                        </div>

                        <div class="meal-skip-text">
                            <p>
                                Going away for a few days?
                                Inform the mess about the meals you will skip.
                            </p>
                        </div>
                    </div>

                    <a href="/smartmess/student/mealskip.php" class="meal-skip-button">
                        <i class="fa-solid fa-calendar-check"></i>
                        Manage Meal Skip
                    </a>

                </div>

            </div>
        </div>
    </main>
</div>

<script>
function searchDashboard() {
    const searchInput = document.getElementById("dashboardSearch");

    if (!searchInput) {
        return;
    }

    const searchText = searchInput.value.toLowerCase().trim();

    const searchableItems = document.querySelectorAll(
        ".welcome-banner, .panel-card, .today-menu-panel, " +
        ".meal-skip-card, .announcement-box"
    );

    searchableItems.forEach(function(item) {
        item.style.display =
            searchText === "" ||
            item.innerText.toLowerCase().includes(searchText)
                ? ""
                : "none";
    });
}

function toggleProfileMenu() {
    const dropdown = document.getElementById("profileDropdown");

    if (dropdown) {
        dropdown.classList.toggle("show");
    }
}

document.addEventListener("click", function(event) {
    const profileWrapper = document.querySelector(".user-menu-wrapper");
    const dropdown = document.getElementById("profileDropdown");

    if (
        profileWrapper &&
        dropdown &&
        !profileWrapper.contains(event.target)
    ) {
        dropdown.classList.remove("show");
    }
});

// On touch devices, tap the bell to open its preview.
// The bell still navigates to Announcements when clicked normally.
const notificationWrapper = document.getElementById("notificationWrapper");
const notificationBell = document.getElementById("notificationBell");

if (notificationWrapper && notificationBell) {
    notificationBell.addEventListener("click", function(event) {
        if (window.matchMedia("(hover: none)").matches) {
            if (!notificationWrapper.classList.contains("open")) {
                event.preventDefault();
                notificationWrapper.classList.add("open");
            }
        }
    });

    document.addEventListener("click", function(event) {
        if (!notificationWrapper.contains(event.target)) {
            notificationWrapper.classList.remove("open");
        }
    });
}
</script>

</body>
</html>
