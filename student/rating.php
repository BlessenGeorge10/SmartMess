<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../db_config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check db_config.php.");
}

$conn->set_charset("utf8mb4");

if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: /smartmess/login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    die("Student session is invalid. Please log in again.");
}

$success_msg = $_SESSION['rating_flash'] ?? '';
unset($_SESSION['rating_flash']);

$error_msg = '';

$validMeals = ['Breakfast', 'Lunch', 'Evening Snack', 'Dinner'];

$validDays = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday',
    'Friday', 'Saturday', 'Sunday'
];

$weekStart = date('Y-m-d', strtotime('monday this week'));

function ratingEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getServingDate($day, $weekStart)
{
    $days = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday'
    ];

    $index = array_search($day, $days, true);

    if ($index === false) {
        return $weekStart;
    }

    $date = new DateTime($weekStart);
    $date->modify('+' . $index . ' days');

    return $date->format('Y-m-d');
}

function getMenuItems($conn, $day, $meal)
{
    $items = [];
    $mealTime = '';

    $stmt = $conn->prepare("
        SELECT meal_time, menu_items
        FROM meal_schedule
        WHERE LOWER(TRIM(day_of_week)) = LOWER(TRIM(?))
          AND LOWER(TRIM(meal_type)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    if (!$stmt) {
        error_log('SmartMess menu query error: ' . $conn->error);

        return [
            'items' => [],
            'meal_time' => '',
            'error' => true
        ];
    }

    $stmt->bind_param('ss', $day, $meal);

    if (!$stmt->execute()) {
        error_log('SmartMess menu execution error: ' . $stmt->error);
        $stmt->close();

        return [
            'items' => [],
            'meal_time' => '',
            'error' => true
        ];
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $mealTime = $row['meal_time'] ?? '';
        $menuText = trim((string)($row['menu_items'] ?? ''));

        if ($menuText !== '') {
            foreach (preg_split('/[,;\n]+/', $menuText) as $item) {
                $item = trim($item);

                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }
    }

    $stmt->close();

    return [
        'items' => array_values(array_unique($items)),
        'meal_time' => $mealTime,
        'error' => false
    ];
}

function hasWeeklySubmission($conn, $user_id, $weekStart)
{
    $stmt = $conn->prepare("
        SELECT id
        FROM weekly_rating_submissions
        WHERE user_id = ?
          AND week_start_date = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log('SmartMess weekly submission query error: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('is', $user_id, $weekStart);

    if (!$stmt->execute()) {
        error_log('SmartMess weekly submission execution error: ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;

    $stmt->close();

    return $exists;
}

/* Check whether the weekly submission table exists. */
$weeklySubmissionExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'weekly_rating_submissions'");

if ($tableCheck && $tableCheck->num_rows > 0) {
    $weeklySubmissionExists = true;
}

$alreadySubmitted = false;

if ($weeklySubmissionExists) {
    $alreadySubmitted = hasWeeklySubmission(
        $conn,
        $user_id,
        $weekStart
    );
}

/* Load menu for all days and meals. */
$weeklyMenu = [];

foreach ($validDays as $day) {
    foreach ($validMeals as $meal) {
        $weeklyMenu[$day][$meal] = getMenuItems($conn, $day, $meal);
    }
}

/* Process submitted weekly ratings. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$weeklySubmissionExists) {
        $error_msg = 'The weekly rating system is not configured correctly. Please contact the administrator.';
    } elseif ($alreadySubmitted) {
        $error_msg = 'You have already submitted your ratings for this week.';
    } else {
        $submittedRatings = $_POST['ratings'] ?? [];
        $submittedComments = $_POST['comments'] ?? [];
        $submittedDays = $_POST['days'] ?? [];
        $submittedMeals = $_POST['meals'] ?? [];

        if (
            !is_array($submittedRatings) ||
            empty($submittedRatings) ||
            !is_array($submittedDays) ||
            !is_array($submittedMeals) ||
            !is_array($submittedComments)
        ) {
            $error_msg = 'Please rate at least one food item before submitting.';
        } else {
            $savedCount = 0;
            $conn->begin_transaction();
            $insertStmt = null;
            $submissionStmt = null;

            try {
                $seenMeals = [];
                $insertStmt = $conn->prepare("
                    INSERT INTO food_item_ratings
                        (user_id, serving_date, meal_type, food_item, rating, comment)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if (!$insertStmt) {
                    throw new RuntimeException(
                        'Unable to prepare rating insert: ' . $conn->error
                    );
                }

                foreach ($submittedRatings as $key => $itemRatings) {
                    if (
                        !is_array($itemRatings) ||
                        !array_key_exists($key, $submittedDays) ||
                        !array_key_exists($key, $submittedMeals)
                    ) {
                        throw new RuntimeException('Invalid rating data submitted.');
                    }

                    $day = $submittedDays[$key] ?? '';
                    $meal = $submittedMeals[$key] ?? '';

                    if (
                        !in_array($day, $validDays, true) ||
                        !in_array($meal, $validMeals, true)
                    ) {
                        throw new RuntimeException('Invalid day or meal submitted.');
                    }

                    $mealKey = $day . "\0" . $meal;
                    if (isset($seenMeals[$mealKey])) {
                        throw new RuntimeException('A meal was submitted more than once.');
                    }
                    $seenMeals[$mealKey] = true;

                    $servingDate = getServingDate($day, $weekStart);

                    if ($servingDate > date('Y-m-d')) {
                        throw new RuntimeException(
                            'You cannot rate a meal before its serving date.'
                        );
                    }

                    $menu = getMenuItems($conn, $day, $meal);
                    $validItems = $menu['items'];

                    if (
                        empty($validItems) ||
                        count($itemRatings) !== count($validItems)
                    ) {
                        throw new RuntimeException(
                            'Rate every food item in each selected meal.'
                        );
                    }

                    foreach ($validItems as $foodItem) {
                        if (!array_key_exists($foodItem, $itemRatings)) {
                            throw new RuntimeException(
                                'Rate every food item in each selected meal.'
                            );
                        }

                        $ratingValue = $itemRatings[$foodItem];
                        if (
                            !is_scalar($ratingValue) ||
                            !preg_match('/^[1-5]$/', (string)$ratingValue)
                        ) {
                            throw new RuntimeException(
                                'Please select a rating from 1 to 5.'
                            );
                        }
                        $ratingValue = (int)$ratingValue;

                        $comment = '';

                        if (isset($submittedComments[$key])) {
                            if (!is_array($submittedComments[$key])) {
                                throw new RuntimeException(
                                    'Invalid comment data submitted.'
                                );
                            }

                            if (array_key_exists($foodItem, $submittedComments[$key])) {
                                $commentValue = $submittedComments[$key][$foodItem];

                                if (!is_string($commentValue)) {
                                    throw new RuntimeException(
                                        'Invalid comment data submitted.'
                                    );
                                }

                                $comment = trim($commentValue);
                            }
                        }

                        if (
                            preg_match('//u', $comment) !== 1 ||
                            strlen($comment) > 4000
                        ) {
                            throw new RuntimeException(
                                'A comment is too long or contains invalid text.'
                            );
                        }

                        $insertStmt->bind_param(
                            'isssis',
                            $user_id,
                            $servingDate,
                            $meal,
                            $foodItem,
                            $ratingValue,
                            $comment
                        );

                        if (!$insertStmt->execute()) {
                            throw new RuntimeException(
                                'Unable to save rating: ' . $insertStmt->error
                            );
                        }

                        $savedCount++;
                    }
                }

                $insertStmt->close();
                $insertStmt = null;

                if ($savedCount === 0) {
                    throw new RuntimeException('No valid ratings were submitted.');
                }

                $submissionStmt = $conn->prepare("
                    INSERT INTO weekly_rating_submissions
                        (user_id, week_start_date)
                    VALUES (?, ?)
                ");

                if (!$submissionStmt) {
                    throw new RuntimeException(
                        'Unable to prepare weekly submission: ' . $conn->error
                    );
                }

                $submissionStmt->bind_param('is', $user_id, $weekStart);

                if (!$submissionStmt->execute()) {
                    throw new RuntimeException(
                        'Unable to record weekly submission: ' . $submissionStmt->error
                    );
                }

                $submissionStmt->close();
                $submissionStmt = null;
                $conn->commit();

                $_SESSION['rating_flash'] =
                    'Your food ratings have been submitted successfully.';

                header('Location: /smartmess/student/rating.php');
                exit;

            } catch (Throwable $exception) {
                if ($insertStmt instanceof mysqli_stmt) {
                    $insertStmt->close();
                }
                if ($submissionStmt instanceof mysqli_stmt) {
                    $submissionStmt->close();
                }
                $conn->rollback();

                error_log(
                    'SmartMess weekly rating submission error: ' .
                    $exception->getMessage()
                );

                $error_msg =
                    'Unable to submit your ratings. Please check your selections and try again.';
            }
        }
    }
}

/* Fetch the latest 10 food-item ratings for this student. */
$recentRatings = [];
$recentRatingsError = false;
$recentStmt = $conn->prepare("
    SELECT id, serving_date, meal_type, food_item, rating, comment, created_at
    FROM food_item_ratings
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");

if ($recentStmt) {
    $recentStmt->bind_param('i', $user_id);

    if ($recentStmt->execute()) {
        $recentResult = $recentStmt->get_result();

        while ($row = $recentResult->fetch_assoc()) {
            $recentRatings[] = $row;
        }
    } else {
        error_log('SmartMess recent ratings error: ' . $recentStmt->error);
        $recentRatingsError = true;
    }

    $recentStmt->close();
} else {
    error_log('SmartMess recent ratings query error: ' . $conn->error);
    $recentRatingsError = true;
}

/* Prepare menu data for the dynamic day/meal selection interface. */
$menuForJavascript = [];

foreach ($validDays as $day) {
    foreach ($validMeals as $meal) {
        $menuForJavascript[$day][$meal] = [
            'items' => $weeklyMenu[$day][$meal]['items'],
            'meal_time' => $weeklyMenu[$day][$meal]['meal_time'],
            'error' => $weeklyMenu[$day][$meal]['error'],
            'date' => getServingDate($day, $weekStart)
        ];
    }
}

$menuJson = json_encode(
    $menuForJavascript,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meal Rating | SmartMess</title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* {
    box-sizing: border-box;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
    margin: 0;
    padding: 0;
}

body {
    margin: 0;
    background: #dce7f0;
    color: #193653;
}

.rating-workspace {
    width: calc(100% - 250px);
    margin-left: 250px;
    padding: 28px 35px;
    min-height: 100vh;
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
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.page-title-banner h2 {
    font-size: 22px;
    color: #1e2b37;
    font-weight: 800;
}

.page-title-banner h2 i {
    margin-right: 8px;
    color: #d99a0b;
}

.page-title-banner p {
    font-size: 13px;
    color: #5a6e85;
    margin-top: 4px;
    line-height: 1.6;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.badge-count {
    background: #cbe3f7;
    color: #3a5ba0;
    padding: 8px 16px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.dashboard-btn {
    background: #3a5ba0;
    color: #ffffff;
    text-decoration: none;
    padding: 9px 15px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: background 0.2s ease;
    white-space: nowrap;
}

.dashboard-btn:hover {
    background: #2d4880;
}

.sidebar-toggle {
    display: none;
}

.rating-sidebar-backdrop {
    display: none;
}

.alert {
    padding: 13px 16px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 13px;
}

.alert.success {
    background: #dcfce7;
    color: #166534;
}

.alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.rating-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
    align-items: start;
    gap: 24px;
}

.panel {
    background: #fff;
    border-radius: 16px;
    padding: 23px 24px;
    min-width: 0;
    box-shadow: 0 5px 18px rgba(32, 61, 87, .05);
}

.panel h2 {
    margin: 0 0 6px;
    color: #183a5b;
    font-size: 21px;
}

.panel-description {
    margin: 0 0 20px;
    color: #637d9a;
    font-size: 13px;
    line-height: 1.65;
}

.week-badge {
    padding: 11px 13px;
    margin-bottom: 19px;
    background: #edf5fc;
    border: 1px solid #d3e6f8;
    border-radius: 9px;
    color: #315779;
    font-size: 12px;
}

.week-badge i {
    margin-right: 6px;
}

.meal-builder {
    padding: 17px;
    border: 1px solid #d7e3f0;
    background: #fbfdff;
    border-radius: 13px;
    margin-bottom: 17px;
}

.meal-builder-title {
    font-weight: 750;
    font-size: 15px;
    margin-bottom: 17px;
    color: #284d75;
}

.select-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 15px;
}

.field label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 650;
    color: #284a70;
}

.field select {
    width: 100%;
    min-width: 0;
    height: 43px;
    padding: 0 10px;
    border: 1px solid #cbd9e8;
    background: #fff;
    border-radius: 9px;
    color: #29435e;
    font-size: 13px;
}

.menu-items {
    margin-top: 12px;
}

.select-message {
    padding: 12px;
    background: #eff4f9;
    color: #6d8299;
    font-size: 13px;
    border-radius: 9px;
    line-height: 1.5;
}

.menu-food-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.menu-food-item {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 9px 11px;
    border-radius: 8px;
    background: #f7fafe;
    color: #254564;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.menu-food-item i {
    margin-top: 3px;
    color: #3a5ba0;
}

.food-rating {
    padding: 14px 0;
    border-bottom: 1px solid #e8eef5;
}

.food-rating:last-child {
    border-bottom: 0;
}

.food-rating-name {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 700;
    color: #254564;
}

.star-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
    width: fit-content;
}

.star-input input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
}

.star-input label {
    cursor: pointer;
    color: #cbd5e1;
    font-size: 24px;
    transition: color .12s ease;
    padding: 2px;
}

.star-input label:hover,
.star-input label:hover ~ label,
.star-input input:checked ~ label {
    color: #f3a712;
}

#ratingForm .star-input label:hover .fa-star,
#ratingForm .star-input label:hover ~ label .fa-star,
#ratingForm .star-input label.is-selected,
#ratingForm .star-input label.is-selected .fa-star {
    color: #f3a712;
}

.star-input input:focus-visible + label {
    outline: 2px solid #3478a7;
    outline-offset: 2px;
    border-radius: 3px;
}

.star-input label:focus,
.star-input label:focus-visible {
    outline: 2px solid #3478a7;
    outline-offset: 2px;
}

.item-comment {
    width: 100%;
    min-height: 55px;
    margin-top: 11px;
    padding: 10px 12px;
    resize: vertical;
    border: 1px solid #cfdeec;
    border-radius: 8px;
    font-size: 12px;
    color: #29435e;
}

.item-comment:focus,
.field select:focus {
    outline: 2px solid #b7d8f3;
    border-color: #5a9acb;
}

.add-meal-button,
.submit-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 13px 15px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 750;
}

.add-meal-button {
    margin-top: 15px;
    background: #e7f1fa;
    color: #285b87;
    border: 1px solid #cfe1f1;
}

.submit-button {
    margin-top: 18px;
    background: #284e70;
    color: #fff;
}

.add-meal-button:hover {
    background: #d9eafa;
}

.submit-button:hover {
    background: #1d3d5b;
}

.added-meal {
    padding: 16px;
    margin-bottom: 15px;
    border: 1px solid #d7e3f0;
    border-radius: 12px;
    background: #fff;
}

.added-meal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.added-meal-header strong {
    font-size: 14px;
    color: #294e73;
}

.remove-meal {
    border: 0;
    background: #fee2e2;
    color: #a12626;
    padding: 7px 9px;
    border-radius: 7px;
    cursor: pointer;
}

.recent-list {
    margin-top: 6px;
}

.recent-item {
    padding: 15px 0;
    border-bottom: 1px solid #e3ebf3;
}

.recent-item:last-child {
    border-bottom: 0;
}

.recent-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 7px;
}

.recent-food {
    color: #234562;
    font-size: 14px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.recent-score {
    color: #eaa20c;
    font-size: 14px;
    white-space: nowrap;
}

.recent-score .score-number {
    color: #c47b00;
    font-size: 12px;
    margin-left: 3px;
}

.recent-meta {
    color: #71859a;
    font-size: 12px;
    line-height: 1.7;
}

.recent-meta i {
    margin-right: 4px;
}

.recent-comment {
    margin: 7px 0 0;
    color: #48637e;
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.empty-state {
    padding: 25px 15px;
    text-align: center;
    color: #71859a;
    background: #f6f9fc;
    border: 1px dashed #cbd9e8;
    border-radius: 11px;
    font-size: 13px;
    line-height: 1.7;
}

.empty-state i {
    display: block;
    margin-bottom: 10px;
    font-size: 27px;
    color: #9aafc4;
}

@media (max-width: 1100px) {
    .rating-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .rating-workspace {
        width: 100%;
        margin-left: 0;
        padding: 62px 14px 18px;
    }

    .student-sidebar {
        transform: translateX(-100%);
        transition: transform .2s ease;
    }

    .student-sidebar.rating-sidebar-open {
        transform: translateX(0);
    }

    .rating-sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 999;
        border: 0;
        background: rgba(15, 31, 50, .48);
    }

    .rating-sidebar-backdrop.is-visible {
        display: block;
    }

    .sidebar-toggle {
        position: fixed;
        top: 12px;
        left: 14px;
        z-index: 1001;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 13px;
        border: 0;
        border-radius: 10px;
        background: #2c3e50;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .page-title-banner {
        padding: 22px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .page-header-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .panel {
        padding: 18px;
    }
}

@media (max-width: 600px) {
    .rating-workspace {
        padding: 62px 14px 18px;
    }

    .page-title-banner {
        padding: 20px;
    }
}

@media (max-width: 420px) {
    .select-grid {
        grid-template-columns: 1fr;
    }

    .recent-top {
        flex-direction: column;
    }

    .star-input label {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<?php include __DIR__ . '/../includes/student_sidebar.php'; ?>

<main class="rating-workspace">

    <header class="page-title-banner">
        <div>
            <h2><i class="fa-solid fa-star"></i>Meal Rating</h2>
            <p>
                Share your feedback about the food served in the mess.
                Your ratings help improve meal quality and service.
            </p>
        </div>
        <div class="page-header-actions">
            <button type="button"
                    class="sidebar-toggle"
                    id="ratingSidebarToggle"
                    aria-label="Open student navigation"
                    aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
                Menu
            </button>
            <span class="badge-count">
                <i class="fa-solid fa-star"></i>
                Weekly Food Ratings
            </span>
            <a href="/smartmess/student/dashboard.php" class="dashboard-btn">
                <i class="fa-solid fa-arrow-left"></i>
                Dashboard
            </a>
        </div>
    </header>

    <?php if ($success_msg !== ''): ?>
        <div class="alert success" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <?php echo ratingEscape($success_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="alert error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo ratingEscape($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="rating-layout">

        <!-- LEFT: RATE YOUR MEALS -->
        <section class="panel">
            <h2>Rate Your Meals</h2>
            <p class="panel-description">
                Select a day and meal, rate every food item from 1 to 5 stars,
                and submit your weekly feedback.
            </p>

            <div class="week-badge">
                <i class="fa-regular fa-calendar"></i>
                Rating week:
                <strong><?php echo ratingEscape(date('d M Y', strtotime($weekStart))); ?></strong>
                –
                <strong><?php echo ratingEscape(date('d M Y', strtotime($weekStart . ' +6 days'))); ?></strong>
            </div>

            <?php if ($alreadySubmitted): ?>

                <div class="empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    You have already submitted your ratings for this week.
                </div>

            <?php else: ?>

                <form method="post" action="/smartmess/student/rating.php" id="ratingForm">

                    <div class="meal-builder">
                        <div class="meal-builder-title">
                            <i class="fa-solid fa-utensils"></i>
                            <span id="builderTitle">Meal 1</span>
                        </div>

                        <div class="select-grid">
                            <div class="field">
                                <label for="daySelect">Day</label>
                                <select id="daySelect">
                                    <option value="">Select day</option>
                                    <?php foreach ($validDays as $day): ?>
                                        <?php
                                        $dateForDay = getServingDate($day, $weekStart);
                                        if ($dateForDay > date('Y-m-d')) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo ratingEscape($day); ?>">
                                            <?php echo ratingEscape($day); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="mealSelect">Meal</label>
                                <select id="mealSelect">
                                    <option value="">Select meal</option>
                                    <?php foreach ($validMeals as $meal): ?>
                                        <option value="<?php echo ratingEscape($meal); ?>">
                                            <?php echo ratingEscape($meal); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="menu-items" id="menuItems">
                            <div class="select-message">
                                Select a day and meal to load the food items.
                            </div>
                        </div>

                        <button type="button" class="add-meal-button" id="addMealButton">
                            <i class="fa-solid fa-plus"></i>
                            Add This Meal
                        </button>
                    </div>

                    <div id="addedMeals"></div>

                    <button type="submit" class="submit-button" id="submitButton">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit Weekly Ratings
                    </button>

                </form>

            <?php endif; ?>
        </section>

        <!-- RIGHT: RECENT RATINGS -->
        <section class="panel">
            <h2>Recent Ratings</h2>
            <p class="panel-description">
                Your 10 most recently submitted food-item ratings.
            </p>

            <div class="recent-list">

                <?php if ($recentRatingsError): ?>

                    <div class="empty-state" role="alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Unable to load your latest ratings. Please refresh the page.
                    </div>

                <?php elseif (!empty($recentRatings)): ?>

                    <?php foreach ($recentRatings as $rating): ?>
                        <?php
                        $score = max(0, min(5, (int)$rating['rating']));
                        $servingTimestamp = strtotime($rating['serving_date']);
                        $createdTimestamp = strtotime($rating['created_at']);
                        ?>

                        <article class="recent-item">
                            <div class="recent-top">
                                <strong class="recent-food">
                                    <?php echo ratingEscape($rating['food_item']); ?>
                                </strong>

                                <span class="recent-score">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-<?php echo $i <= $score ? 'solid' : 'regular'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <span class="score-number">
                                        <?php echo $score; ?>/5
                                    </span>
                                </span>
                            </div>

                            <div class="recent-meta">
                                <i class="fa-regular fa-calendar"></i>
                                <?php
                                echo $servingTimestamp
                                    ? ratingEscape(date('d M Y', $servingTimestamp))
                                    : ratingEscape($rating['serving_date']);
                                ?>
                                &nbsp; | &nbsp;
                                <i class="fa-solid fa-utensils"></i>
                                <?php echo ratingEscape($rating['meal_type']); ?>
                                <br>

                                <i class="fa-regular fa-clock"></i>
                                Submitted:
                                <?php
                                echo $createdTimestamp
                                    ? ratingEscape(date('d M Y, h:i A', $createdTimestamp))
                                    : ratingEscape($rating['created_at']);
                                ?>
                            </div>

                            <?php if (trim((string)($rating['comment'] ?? '')) !== ''): ?>
                                <p class="recent-comment">
                                    <i class="fa-regular fa-comment"></i>
                                    <?php echo ratingEscape($rating['comment']); ?>
                                </p>
                            <?php endif; ?>
                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">
                        <i class="fa-regular fa-star"></i>
                        No ratings submitted yet.
                        Your submitted food-item ratings will appear here.
                    </div>

                <?php endif; ?>

            </div>
        </section>

    </div>
</main>

<button type="button"
        class="rating-sidebar-backdrop"
        id="ratingSidebarBackdrop"
        aria-label="Close navigation"
        tabindex="-1"></button>

<script>
const weeklyMenu = <?php echo $menuJson ?: '{}'; ?>;

const daySelect = document.getElementById('daySelect');
const mealSelect = document.getElementById('mealSelect');
const menuItemsContainer = document.getElementById('menuItems');
const addMealButton = document.getElementById('addMealButton');
const addedMealsContainer = document.getElementById('addedMeals');
const builderTitle = document.getElementById('builderTitle');
const ratingForm = document.getElementById('ratingForm');

let nextMealKey = 1;
const addedMealKeys = new Set();

const studentSidebar = document.querySelector('.student-sidebar');
const sidebarToggle = document.getElementById('ratingSidebarToggle');
const sidebarBackdrop = document.getElementById('ratingSidebarBackdrop');

if (studentSidebar && sidebarToggle && sidebarBackdrop) {
    studentSidebar.id = 'student-sidebar';
    sidebarToggle.setAttribute('aria-controls', 'student-sidebar');

    function closeRatingSidebar() {
        studentSidebar.classList.remove('rating-sidebar-open');
        sidebarBackdrop.classList.remove('is-visible');
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.setAttribute('aria-label', 'Open student navigation');
    }

    sidebarToggle.addEventListener('click', function () {
        const isOpen = studentSidebar.classList.toggle('rating-sidebar-open');
        sidebarBackdrop.classList.toggle('is-visible', isOpen);
        sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        sidebarToggle.setAttribute(
            'aria-label',
            isOpen ? 'Close student navigation' : 'Open student navigation'
        );
    });

    sidebarBackdrop.addEventListener('click', closeRatingSidebar);

    studentSidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeRatingSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeRatingSidebar();
        }
    });
}

function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function getSelectedMenu() {
    const day = daySelect.value;
    const meal = mealSelect.value;

    if (!day || !meal || !weeklyMenu[day] || !weeklyMenu[day][meal]) {
        return null;
    }

    return weeklyMenu[day][meal];
}

function renderSelectedMenu() {
    const day = daySelect.value;
    const meal = mealSelect.value;
    const menu = getSelectedMenu();

    if (!day || !meal || !menu) {
        menuItemsContainer.innerHTML =
            '<div class="select-message">Select a day and meal to load the food items.</div>';
        return;
    }

    if (menu.error) {
        menuItemsContainer.innerHTML =
            '<div class="select-message">Unable to load this menu. Please refresh the page and try again.</div>';
        return;
    }

    if (!menu.items || menu.items.length === 0) {
        menuItemsContainer.innerHTML =
            '<div class="select-message">No food items are listed for this meal.</div>';
        return;
    }

    let html = `
        <div class="recent-meta">
            <i class="fa-regular fa-calendar"></i>
            ${escapeHTML(menu.date)}
            ${menu.meal_time
                ? `&nbsp; | &nbsp;<i class="fa-regular fa-clock"></i> ${escapeHTML(menu.meal_time)}`
                : ''}
        </div>
        <div class="menu-food-list" aria-label="Menu items">
    `;

    menu.items.forEach(function (foodItem) {
        html += `<div class="menu-food-item"><i class="fa-solid fa-utensils"></i>${escapeHTML(foodItem)}</div>`;
    });

    html += `
        </div>
        <div class="select-message">Add this served meal below to rate every listed food item.</div>
    `;

    menuItemsContainer.innerHTML = html;
}

if (
    daySelect &&
    mealSelect &&
    menuItemsContainer &&
    addMealButton &&
    addedMealsContainer &&
    builderTitle &&
    ratingForm
) {
    daySelect.addEventListener('change', renderSelectedMenu);
    mealSelect.addEventListener('change', renderSelectedMenu);

    ratingForm.addEventListener('change', function (event) {
        const radio = event.target;

        if (
            !(radio instanceof HTMLInputElement) ||
            radio.type !== 'radio' ||
            !radio.closest('.star-input')
        ) {
            return;
        }

        const selectedRating = Number(radio.value);
        const starGroup = radio.closest('.star-input');

        starGroup.querySelectorAll('input[type="radio"]').forEach(function (star) {
            const label = star.nextElementSibling;

            if (label && label.tagName === 'LABEL') {
                label.classList.toggle(
                    'is-selected',
                    Number(star.value) <= selectedRating
                );
            }
        });
    });

    addMealButton.addEventListener('click', function () {
        const day = daySelect.value;
        const meal = mealSelect.value;
        const menu = getSelectedMenu();

        if (!day || !meal || !menu) {
            alert('Please select both a day and a meal.');
            return;
        }

        if (menu.error) {
            alert('Unable to load this menu. Please refresh the page and try again.');
            return;
        }

        if (!menu.items || menu.items.length === 0) {
            alert('No food items are available for this meal.');
            return;
        }

        const servingDate = menu.date;
        const uniqueKey = day + '|' + meal;

        if (addedMealKeys.has(uniqueKey)) {
            alert('You have already added this day and meal.');
            return;
        }

        const mealKey = String(nextMealKey++);
        addedMealKeys.add(uniqueKey);

        let html = `
            <section class="added-meal" data-unique-key="${escapeHTML(uniqueKey)}">
                <div class="added-meal-header">
                    <strong>
                        <i class="fa-solid fa-utensils"></i>
                        ${escapeHTML(day)} — ${escapeHTML(meal)}
                    </strong>
                    <button type="button" class="remove-meal" title="Remove meal">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>

                <input type="hidden" name="days[${mealKey}]" value="${escapeHTML(day)}">
                <input type="hidden" name="meals[${mealKey}]" value="${escapeHTML(meal)}">
                <div class="recent-meta">
                    <i class="fa-regular fa-calendar"></i>
                    ${escapeHTML(servingDate)}
                    ${menu.meal_time
                        ? `&nbsp; | &nbsp;<i class="fa-regular fa-clock"></i> ${escapeHTML(menu.meal_time)}`
                        : ''}
                </div>
        `;

        menu.items.forEach(function (foodItem, itemIndex) {
            const safeId = 'meal_' + mealKey + '_item_' + itemIndex;
            const escapedItem = escapeHTML(foodItem);

            html += `
                <div class="food-rating">
                    <span class="food-rating-name">${escapedItem}</span>
                    <div class="star-input" role="radiogroup"
                         aria-label="Rate ${escapedItem}">
            `;

            for (let score = 5; score >= 1; score--) {
                html += `
                    <input
                        type="radio"
                        id="${safeId}_${score}"
                        name="ratings[${mealKey}][${escapedItem}]"
                        value="${score}"
                        aria-label="${score} out of 5 stars for ${escapedItem}"
                        required>
                    <label for="${safeId}_${score}"
                           title="${score} out of 5 stars"
                           aria-hidden="true">
                        <i class="fa-solid fa-star"></i>
                    </label>
                `;
            }

            html += `
                    </div>
                    <textarea
                        class="item-comment"
                        name="comments[${mealKey}][${escapedItem}]"
                        maxlength="1000"
                        placeholder="Optional feedback about ${escapedItem}"></textarea>
                </div>
            `;
        });

        html += '</section>';

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        const addedMeal = wrapper.firstElementChild;

        addedMeal.querySelector('.remove-meal').addEventListener('click', function () {
            addedMealKeys.delete(uniqueKey);
            addedMeal.remove();
        });

        addedMealsContainer.appendChild(addedMeal);

        builderTitle.textContent = 'Meal ' + nextMealKey;
        menuItemsContainer.innerHTML =
            '<div class="select-message">Meal added. Select another day and meal, or submit your ratings.</div>';

        daySelect.value = '';
        mealSelect.value = '';
    });

    ratingForm.addEventListener('submit', function (event) {
        if (addedMealsContainer.children.length === 0) {
            event.preventDefault();
            alert('Please add at least one meal before submitting.');
            return;
        }

        const mealSections = addedMealsContainer.querySelectorAll('.added-meal');

        for (const mealSection of mealSections) {
            for (const foodItem of mealSection.querySelectorAll('.food-rating')) {
                if (!foodItem.querySelector('input[type="radio"]:checked')) {
                    event.preventDefault();
                    alert('Please select a star rating for every food item in the meals you added.');
                    const firstUnrated = foodItem.querySelector('input[type="radio"]');
                    if (firstUnrated) {
                        firstUnrated.focus();
                    }
                    return;
                }
            }
        }
    });
}
</script>

</body>
</html>