<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../db_config.php';

if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'manager'
) {
    header('Location: /smartmess/login.php');
    exit;
}

$today = date('Y-m-d');
$error_message = '';
$requests = [];

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

/* Meal Preparation Overview inputs */
$prep_date = trim($_GET['prep_date'] ?? $today);
$total_students_input = trim($_GET['total_students'] ?? '');
$total_students = null;
$prep_date_valid = true;
$prep_error = '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prep_date)) {
    $prep_date = $today;
    $prep_date_valid = false;
    $prep_error = 'Please select a valid preparation date.';
} else {
    $dateObject = DateTime::createFromFormat('!Y-m-d', $prep_date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $prep_date) {
        $prep_date = $today;
        $prep_date_valid = false;
        $prep_error = 'Please select a valid preparation date.';
    }
}

if ($total_students_input !== '') {
    if (
        filter_var($total_students_input, FILTER_VALIDATE_INT) !== false &&
        (int)$total_students_input >= 0
    ) {
        $total_students = (int)$total_students_input;
    } else {
        $prep_error = 'Enter a valid total number of students.';
    }
}

$allowed_statuses = ['all', 'Active', 'Cancelled'];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

if (
    ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) ||
    ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))
) {
    $start_date = '';
    $end_date = '';
    $error_message = 'Please enter valid dates.';
}

if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) {
    $start_date = '';
    $end_date = '';
    $error_message = 'The start date cannot be after the end date.';
}

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function runMealSkipQuery($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException($message);
    }

    return $stmt;
}

function mealNames($row)
{
    $meals = [];

    if ((int)($row['breakfast'] ?? 0) === 1) {
        $meals[] = 'Breakfast';
    }

    if ((int)($row['lunch'] ?? 0) === 1) {
        $meals[] = 'Lunch';
    }

    if ((int)($row['dinner'] ?? 0) === 1) {
        $meals[] = 'Dinner';
    }

    if (count($meals) === 3) {
        return 'Full Day';
    }

    return $meals ? implode(', ', $meals) : 'No meals selected';
}

$active_requests = 0;
$breakfast_count = 0;
$lunch_count = 0;
$dinner_count = 0;
$total_requests = 0;

/* Meal Preparation Overview values */
$prep_breakfast_skips = 0;
$prep_lunch_skips = 0;
$prep_dinner_skips = 0;
$prep_active_requests = 0;

try {
    /* Existing summary cards: unchanged */
    $summarySql = "
        SELECT
            COUNT(*) AS active_requests,
            COALESCE(SUM(breakfast), 0) AS breakfast_count,
            COALESCE(SUM(lunch), 0) AS lunch_count,
            COALESCE(SUM(dinner), 0) AS dinner_count
        FROM meal_skips
        WHERE LOWER(status) = 'active'
          AND start_date <= ?
          AND end_date >= ?
    ";

    $summaryStmt = runMealSkipQuery(
        $conn,
        $summarySql,
        'ss',
        [$today, $today]
    );

    $summaryResult = $summaryStmt->get_result();
    $summary = $summaryResult->fetch_assoc() ?: [];
    $summaryStmt->close();

    $active_requests = (int)($summary['active_requests'] ?? 0);
    $breakfast_count = (int)($summary['breakfast_count'] ?? 0);
    $lunch_count = (int)($summary['lunch_count'] ?? 0);
    $dinner_count = (int)($summary['dinner_count'] ?? 0);

    /*
     * NEW FEATURE:
     * Count distinct students with active meal-skip requests
     * covering the selected preparation date.
     *
     * This avoids counting the same student more than once
     * for a meal if overlapping active requests exist.
     */
    $prepSql = "
        SELECT
            COUNT(*) AS active_requests,
            COUNT(DISTINCT CASE
                WHEN breakfast = 1 THEN student_id
            END) AS breakfast_skips,
            COUNT(DISTINCT CASE
                WHEN lunch = 1 THEN student_id
            END) AS lunch_skips,
            COUNT(DISTINCT CASE
                WHEN dinner = 1 THEN student_id
            END) AS dinner_skips
        FROM meal_skips
        WHERE LOWER(status) = 'active'
          AND start_date <= ?
          AND end_date >= ?
    ";

    $prepStmt = runMealSkipQuery(
        $conn,
        $prepSql,
        'ss',
        [$prep_date, $prep_date]
    );

    $prepResult = $prepStmt->get_result();
    $prepSummary = $prepResult->fetch_assoc() ?: [];
    $prepStmt->close();

    $prep_active_requests = (int)($prepSummary['active_requests'] ?? 0);
    $prep_breakfast_skips = (int)($prepSummary['breakfast_skips'] ?? 0);
    $prep_lunch_skips = (int)($prepSummary['lunch_skips'] ?? 0);
    $prep_dinner_skips = (int)($prepSummary['dinner_skips'] ?? 0);

    /* Existing requests table query: unchanged */
    $sql = "
        SELECT
            ms.id,
            ms.student_id,
            ms.start_date,
            ms.end_date,
            ms.breakfast,
            ms.lunch,
            ms.dinner,
            ms.reason,
            ms.status,
            ms.created_at,
            u.name AS student_name,
            u.email AS student_email,
            u.student_id AS student_number
        FROM meal_skips ms
        LEFT JOIN users u ON u.id = ms.student_id
        WHERE 1 = 1
    ";

    $types = '';
    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND LOWER(ms.status) = LOWER(?)";
        $types .= 's';
        $params[] = $status_filter;
    }

    if ($start_date !== '') {
        $sql .= " AND ms.end_date >= ?";
        $types .= 's';
        $params[] = $start_date;
    }

    if ($end_date !== '') {
        $sql .= " AND ms.start_date <= ?";
        $types .= 's';
        $params[] = $end_date;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                u.name LIKE ?
                OR u.email LIKE ?
                OR CAST(ms.student_id AS CHAR) LIKE ?
                OR CAST(u.student_id AS CHAR) LIKE ?
            )
        ";

        $like = '%' . $search . '%';
        $types .= 'ssss';

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY ms.created_at DESC, ms.id DESC";

    $stmt = runMealSkipQuery($conn, $sql, $types, $params);
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    $stmt->close();
    $total_requests = count($requests);

} catch (Throwable $e) {
    error_log('SmartMess Meal Skip error: ' . $e->getMessage());

    $error_message =
        'Unable to load meal-skip information. Technical detail: ' .
        $e->getMessage();
}

/* Estimated meals needed for each meal period */
$breakfast_to_prepare = $total_students !== null
    ? max(0, $total_students - $prep_breakfast_skips)
    : null;

$lunch_to_prepare = $total_students !== null
    ? max(0, $total_students - $prep_lunch_skips)
    : null;

$dinner_to_prepare = $total_students !== null
    ? max(0, $total_students - $prep_dinner_skips)
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meal Skip Management | Manager Portal</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #2c3e50;
            color: #263445;
        }

        .app-window {
            display: flex;
            width: 100%;
            max-width: 1250px;
            min-height: 750px;
            background: #f0f2f8;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.20);
        }

        .sidebar {
            width: 230px;
            min-width: 230px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #1e2b37;
            color: #fff;
            padding: 30px 0 25px;
        }

        .sidebar-top {
            width: 100%;
        }

        .brand-logo {
            padding: 0 25px 35px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: 22px;
            font-weight: 800;
        }

        .brand-logo i {
            font-size: 22px;
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
            margin-left: 15px;
            padding: 12px 20px;
            color: #b8c7ce;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 20px 0 0 20px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .nav-link i {
            width: 16px;
            text-align: center;
        }

        .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.07);
        }

        .nav-link.active {
            background: #f0f2f8;
            color: #1e2b37;
            font-weight: 700;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-link:hover {
            color: #ff7668;
        }

        .main-workspace {
            flex: 1;
            min-width: 0;
            padding: 30px;
            background: #f0f2f8;
            overflow-x: hidden;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin-bottom: 6px;
            color: #1a202c;
            font-size: 24px;
            font-weight: 700;
        }

        .subtitle {
            color: #718096;
            font-size: 13px;
            line-height: 1.5;
        }

        .today {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 12px;
            background: #fff;
            color: #263445;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .card {
            min-width: 0;
            min-height: 130px;
            padding: 20px;
            border: none;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .card-label {
            min-height: 36px;
            margin-bottom: 10px;
            color: #718096;
            font-size: 12px;
            line-height: 1.5;
        }

        .card-number {
            color: #1e2b37;
            font-size: 30px;
            font-weight: 800;
        }

        .card-icon {
            float: right;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #f0f2f8;
            color: #2c3e50;
        }

        .panel {
            margin-bottom: 22px;
            padding: 25px;
            border: none;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .panel h2 {
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 16px;
            font-weight: 700;
        }

        .panel h2 i {
            margin-right: 8px;
            color: #2c3e50;
        }

        .filters {
            display: grid;
            grid-template-columns: minmax(180px, 2fr) repeat(3, minmax(110px, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .field {
            min-width: 0;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: #4a5568;
            font-size: 12px;
            font-weight: 600;
        }

        input,
        select {
            width: 100%;
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            outline: none;
            background: #f8fafc;
            color: #263445;
            font-size: 13px;
        }

        input:focus,
        select:focus {
            border-color: #cbd5e0;
            outline: none;
            box-shadow: none;
            background: #fff;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            padding: 11px 15px;
            border: none;
            border-radius: 12px;
            background: #2c3e50;
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .button:hover {
            background: #1e2b37;
        }

        .button.reset {
            background: #edf0f5;
            color: #2d3748;
        }

        .button.reset:hover {
            background: #e2e8f0;
        }

        /* NEW: Meal Preparation Overview styling */
        .prep-description {
            margin-top: -10px;
            margin-bottom: 20px;
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        .prep-form {
            display: grid;
            grid-template-columns: minmax(150px, 1fr) minmax(150px, 1fr) auto;
            gap: 14px;
            align-items: end;
            margin-bottom: 22px;
        }

        .prep-results {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .prep-card {
            min-width: 0;
            padding: 18px;
            border-radius: 16px;
            background: #f8fafc;
        }

        .prep-card-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 15px;
            color: #4a5568;
            font-size: 13px;
            font-weight: 700;
        }

        .prep-card-title i {
            color: #2c3e50;
        }

        .prep-number {
            margin-bottom: 5px;
            color: #1e2b37;
            font-size: 28px;
            font-weight: 800;
        }

        .prep-detail {
            color: #718096;
            font-size: 12px;
            line-height: 1.6;
        }

        .prep-total {
            display: block;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            color: #2d3748;
            font-size: 12px;
            font-weight: 700;
        }

        .prep-note {
            margin-top: 18px;
            padding: 13px 15px;
            border-radius: 12px;
            background: #f0f2f8;
            color: #526174;
            font-size: 12px;
            line-height: 1.7;
        }

        .prep-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff0f0;
            color: #9b1c1c;
            font-size: 12px;
            line-height: 1.6;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #edf2f7;
            font-size: 12px;
            line-height: 1.6;
        }

        th {
            background: #fff;
            color: #718096;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            text-transform: uppercase;
        }

        td {
            color: #263445;
            overflow-wrap: anywhere;
        }

        .student-name {
            margin-bottom: 4px;
            color: #2d3748;
            font-size: 13px;
            font-weight: 700;
        }

        .muted {
            color: #8492a6;
            font-size: 11px;
        }

        .meal-tag {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 8px;
            background: #edf0f8;
            color: #2c3e50;
            font-size: 11px;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 8px;
            background: #e5f7ed;
            color: #217647;
            font-size: 11px;
            font-weight: 700;
        }

        .status.cancelled {
            background: #ffe8e8;
            color: #b42318;
        }

        .empty {
            padding: 30px 12px;
            text-align: center;
            color: #718096;
        }

        .error {
            margin-bottom: 20px;
            padding: 14px;
            border: 1px solid #f2b8b5;
            border-radius: 12px;
            background: #fff0f0;
            color: #9b1c1c;
            overflow-wrap: anywhere;
            font-size: 13px;
        }

        .error small {
            display: block;
            margin-top: 7px;
        }

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-actions {
                flex-wrap: wrap;
            }

            .prep-results {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .app-window {
                min-height: calc(100vh - 24px);
                flex-direction: column;
                border-radius: 22px;
            }

            .app-window > .sidebar {
                width: 100%;
                min-width: 0;
                padding: 18px 0;
            }

            .app-window .brand-logo {
                padding: 0 20px 16px;
            }

            .app-window .nav-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px;
                padding: 0 12px;
            }

            .app-window .nav-link {
                margin-left: 0;
                padding: 10px 12px;
                border-radius: 12px;
            }

            .app-window .nav-link.active {
                border-radius: 12px;
            }

            .app-window .logout-link {
                padding: 15px 20px 0;
            }

            .main-workspace {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0;
            }

            .app-window {
                border-radius: 0;
            }

            .main-workspace {
                padding: 16px;
            }

            .cards,
            .filters,
            .prep-form {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .filter-actions .button {
                flex: 1;
            }
        }
    </style>
</head>

<body>
<div class="app-window">

    <?php
    $manager_sidebar_theme = 'legacy';
    include __DIR__ . '/../includes/manager_sidebar.php';
    ?>

    <main class="main-workspace">

        <header class="page-header">
            <div>
                <h1>Meal Skip Management</h1>
                <p class="subtitle">
                    View and monitor student meal-skip requests.
                </p>
            </div>

            <div class="today">
                <i class="fa-regular fa-calendar"></i>
                Today: <?= h(date('d M Y')) ?>
            </div>
        </header>

        <?php if ($error_message !== ''): ?>
            <div class="error">
                <strong>Unable to load Meal Skip data.</strong>
                <small><?= h($error_message) ?></small>
                <small>
                    Check the database table and column names,
                    and confirm that db_config.php connects successfully.
                </small>
            </div>
        <?php endif; ?>

        <!-- Existing summary cards -->
        <section class="cards">

            <div class="card">
                <span class="card-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
                <div class="card-label">Active Requests Today</div>
                <div class="card-number"><?= $active_requests ?></div>
            </div>

            <div class="card">
                <span class="card-icon">
                    <i class="fa-solid fa-mug-hot"></i>
                </span>
                <div class="card-label">Breakfast Skips Today</div>
                <div class="card-number"><?= $breakfast_count ?></div>
            </div>

            <div class="card">
                <span class="card-icon">
                    <i class="fa-solid fa-bowl-food"></i>
                </span>
                <div class="card-label">Lunch Skips Today</div>
                <div class="card-number"><?= $lunch_count ?></div>
            </div>

            <div class="card">
                <span class="card-icon">
                    <i class="fa-solid fa-utensils"></i>
                </span>
                <div class="card-label">Dinner Skips Today</div>
                <div class="card-number"><?= $dinner_count ?></div>
            </div>

        </section>

        <!-- NEW FEATURE: Meal Preparation Overview -->
        <section class="panel">

            <h2>
                <i class="fa-solid fa-utensils"></i>
                Meal Preparation Overview
            </h2>

            <p class="prep-description">
                Select a date to view active meal skips and estimate
                the number of meals to prepare.
            </p>

            <?php if ($prep_error !== ''): ?>
                <div class="prep-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= h($prep_error) ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="" class="prep-form">

                <!-- Preserve existing search/filter values -->
                <input type="hidden" name="search" value="<?= h($search) ?>">
                <input type="hidden" name="status" value="<?= h($status_filter) ?>">
                <input type="hidden" name="start_date" value="<?= h($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= h($end_date) ?>">

                <div class="field">
                    <label for="prep_date">Meal Preparation Date</label>
                    <input
                        type="date"
                        id="prep_date"
                        name="prep_date"
                        value="<?= h($prep_date) ?>"
                        required>
                </div>

                <div class="field">
                    <label for="total_students">
                        Total Students Expected
                    </label>
                    <input
                        type="number"
                        id="total_students"
                        name="total_students"
                        min="0"
                        step="1"
                        placeholder="Enter student count"
                        value="<?= h($total_students_input) ?>">
                </div>

                <div class="filter-actions">
                    <button class="button" type="submit">
                        <i class="fa-solid fa-calculator"></i>
                        Calculate
                    </button>
                </div>

            </form>

            <div class="prep-results">

                <div class="prep-card">
                    <div class="prep-card-title">
                        <i class="fa-solid fa-mug-hot"></i>
                        Breakfast
                    </div>

                    <div class="prep-number">
                        <?= $prep_breakfast_skips ?>
                    </div>

                    <div class="prep-detail">
                        Student(s) skipping breakfast
                    </div>

                    <?php if ($total_students !== null): ?>
                        <span class="prep-total">
                            Estimated meals to prepare:
                            <?= $breakfast_to_prepare ?>
                        </span>
                    <?php else: ?>
                        <span class="prep-total">
                            Enter total students to estimate meals.
                        </span>
                    <?php endif; ?>
                </div>

                <div class="prep-card">
                    <div class="prep-card-title">
                        <i class="fa-solid fa-bowl-food"></i>
                        Lunch
                    </div>

                    <div class="prep-number">
                        <?= $prep_lunch_skips ?>
                    </div>

                    <div class="prep-detail">
                        Student(s) skipping lunch
                    </div>

                    <?php if ($total_students !== null): ?>
                        <span class="prep-total">
                            Estimated meals to prepare:
                            <?= $lunch_to_prepare ?>
                        </span>
                    <?php else: ?>
                        <span class="prep-total">
                            Enter total students to estimate meals.
                        </span>
                    <?php endif; ?>
                </div>

                <div class="prep-card">
                    <div class="prep-card-title">
                        <i class="fa-solid fa-utensils"></i>
                        Dinner
                    </div>

                    <div class="prep-number">
                        <?= $prep_dinner_skips ?>
                    </div>

                    <div class="prep-detail">
                        Student(s) skipping dinner
                    </div>

                    <?php if ($total_students !== null): ?>
                        <span class="prep-total">
                            Estimated meals to prepare:
                            <?= $dinner_to_prepare ?>
                        </span>
                    <?php else: ?>
                        <span class="prep-total">
                            Enter total students to estimate meals.
                        </span>
                    <?php endif; ?>
                </div>

            </div>

            <div class="prep-note">
                <i class="fa-solid fa-circle-info"></i>
                Counts include students with active meal-skip requests
                covering the selected date. Cancelled requests are excluded.
                The preparation estimate is calculated by subtracting the
                number of students skipping each meal from the total student
                count you enter. Ensure the total represents students expected
                to attend before accounting for these meal skips.
            </div>

        </section>

        <!-- Existing search and filter section -->
        <section class="panel">
            <h2>
                <i class="fa-solid fa-filter"></i>
                Search and Filter Requests
            </h2>

            <form method="GET" action="">
                <div class="filters">

                    <div class="field">
                        <label for="search">Search Student</label>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            placeholder="Name, email or student ID"
                            value="<?= h($search) ?>">
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all"
                                <?= $status_filter === 'all' ? 'selected' : '' ?>>
                                All statuses
                            </option>
                            <option value="Active"
                                <?= $status_filter === 'Active' ? 'selected' : '' ?>>
                                Active
                            </option>
                            <option value="Cancelled"
                                <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>
                                Cancelled
                            </option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="start_date">From Date</label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value="<?= h($start_date) ?>">
                    </div>

                    <div class="field">
                        <label for="end_date">To Date</label>
                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            value="<?= h($end_date) ?>">
                    </div>

                    <div class="filter-actions">
                        <button class="button" type="submit">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Search
                        </button>

                        <a class="button reset"
                           href="/smartmess/manager/mealskip.php">
                            <i class="fa-solid fa-rotate-left"></i>
                            Reset
                        </a>
                    </div>

                </div>
            </form>
        </section>

        <!-- Existing requests table -->
        <section class="panel">
            <h2>
                <i class="fa-solid fa-list-check"></i>
                Student Meal Skip Requests (<?= $total_requests ?>)
            </h2>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Meals</th>
                        <th>Skip Dates</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted On</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (count($requests) === 0): ?>
                        <tr>
                            <td colspan="6" class="empty">
                                No meal-skip requests found.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($requests as $row): ?>
                            <?php
                            $status = (string)($row['status'] ?? '');
                            $isCancelled = strtolower($status) === 'cancelled';
                            ?>

                            <tr>
                                <td>
                                    <div class="student-name">
                                        <?= h($row['student_name'] ?? 'Unknown Student') ?>
                                    </div>

                                    <div class="muted">
                                        Student ID:
                                        <?= h($row['student_number'] ?? $row['student_id']) ?>
                                    </div>

                                    <div class="muted">
                                        <?= h($row['student_email'] ?? '') ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="meal-tag">
                                        <?= h(mealNames($row)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h($row['start_date'] ?? '') ?>
                                    <br>
                                    <span class="muted">to</span>
                                    <br>
                                    <?= h($row['end_date'] ?? '') ?>
                                </td>

                                <td>
                                    <?= h(
                                        ($row['reason'] ?? '') !== ''
                                            ? $row['reason']
                                            : 'No reason provided'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status <?= $isCancelled ? 'cancelled' : '' ?>">
                                        <?= h($status !== '' ? $status : 'Unknown') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h($row['created_at'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>
</body>
</html>