
<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'student'
) {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is unavailable. Check db_config.php.');
}

$conn->set_charset('utf8mb4');

$student_id = (int)($_SESSION['user_id'] ?? 0);

if ($student_id <= 0) {
    die('Invalid student session. Please log in again.');
}

$student_stmt = $conn->prepare(
    "SELECT id, name FROM users
     WHERE id = ? AND LOWER(role) = 'student'
     LIMIT 1"
);

if (!$student_stmt) {
    die('Unable to verify your student account.');
}

$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

if (!$student) {
    die('Student account not found. Please log in again.');
}

$student_id = (int)$student['id'];
$student_name = trim((string)$student['name']);
$today = date('Y-m-d');

$error_message = '';
$success_message = $_SESSION['meal_skip_flash'] ?? '';
unset($_SESSION['meal_skip_flash']);

$requests = [];

if (empty($_SESSION['meal_skip_token'])) {
    $_SESSION['meal_skip_token'] = bin2hex(random_bytes(32));
}

function msEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function msDate($date)
{
    $time = strtotime((string)$date);
    return $time ? date('d M Y', $time) : (string)$date;
}

/* Submit or cancel a request */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['meal_skip_token'] ?? '');

    if (
        $token === '' ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $token)
    ) {
        $error_message =
            'Your form expired. Refresh the page and try again.';

    } elseif (isset($_POST['submit_meal_skip'])) {

        $start_date = trim((string)($_POST['start_date'] ?? ''));
        $end_date = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $breakfast = isset($_POST['breakfast']) ? 1 : 0;
        $lunch = isset($_POST['lunch']) ? 1 : 0;
        $dinner = isset($_POST['dinner']) ? 1 : 0;
        $full_day = isset($_POST['full_day']);

        if ($full_day) {
            $breakfast = 1;
            $lunch = 1;
            $dinner = 1;
        }

        $startObj = DateTime::createFromFormat(
            '!Y-m-d',
            $start_date
        );

        $endObj = DateTime::createFromFormat(
            '!Y-m-d',
            $end_date
        );

        $validDates =
            $startObj !== false &&
            $startObj->format('Y-m-d') === $start_date &&
            $endObj !== false &&
            $endObj->format('Y-m-d') === $end_date;

        if (!$validDates) {
            $error_message = 'Please select valid dates.';

        } elseif ($start_date < $today) {
            $error_message = 'The start date cannot be in the past.';

        } elseif ($end_date < $start_date) {
            $error_message =
                'The end date cannot be before the start date.';

        } elseif (!$breakfast && !$lunch && !$dinner) {
            $error_message = 'Please select at least one meal.';

        } elseif (strlen($reason) > 255) {
            $error_message =
                'The reason cannot exceed 255 characters.';

        } else {

            $status = 'Active';

            $insert = $conn->prepare(
                'INSERT INTO meal_skips
                    (student_id, start_date, end_date,
                     breakfast, lunch, dinner, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$insert) {
                error_log(
                    'Meal skip prepare failed: ' . $conn->error
                );

                $error_message =
                    'Could not prepare your request. Please check the database structure.';

            } else {

                $insert->bind_param(
                    'issiiiss',
                    $student_id,
                    $start_date,
                    $end_date,
                    $breakfast,
                    $lunch,
                    $dinner,
                    $reason,
                    $status
                );

                if ($insert->execute()) {

                    /* Get the ID of the newly saved request. */
                    $meal_skip_id = (int)$conn->insert_id;
                    $insert->close();

                    /*
                     * Create a notification for the manager.
                     * The notification is unread by default because
                     * notifications.is_read has a default of 0.
                     */
                    $notification_title = 'New Meal Skip Request';

                    $notification_message =
                        $student_name .
                        ' submitted a meal skip request from ' .
                        msDate($start_date) .
                        ' to ' .
                        msDate($end_date) .
                        '.';

                    $notification_type = 'meal_skip';
                    $recipient_role = 'manager';

                    $notification = $conn->prepare(
                        'INSERT INTO notifications
                            (recipient_role, recipient_id, title,
                             message, notification_type, related_id)
                         VALUES (?, NULL, ?, ?, ?, ?)'
                    );

                    if ($notification) {

                        $notification->bind_param(
                            'ssssi',
                            $recipient_role,
                            $notification_title,
                            $notification_message,
                            $notification_type,
                            $meal_skip_id
                        );

                        if (!$notification->execute()) {
                            error_log(
                                'Meal skip notification failed: ' .
                                $notification->error
                            );
                        }

                        $notification->close();

                    } else {
                        error_log(
                            'Notification prepare failed: ' .
                            $conn->error
                        );
                    }

                    $_SESSION['meal_skip_flash'] =
                        'Your meal skip request was submitted successfully!';

                    $_SESSION['meal_skip_token'] =
                        bin2hex(random_bytes(32));

                    header(
                        'Location: /smartmess/student/mealskip.php'
                    );
                    exit;

                } else {

                    error_log(
                        'Meal skip insert failed: ' . $insert->error
                    );

                    $error_message =
                        'Your request could not be saved. Please try again.';

                    $insert->close();
                }
            }
        }

    } elseif (isset($_POST['cancel_meal_skip'])) {

        $request_id = (int)($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            $error_message = 'Invalid request selected.';

        } else {

            $cancel = $conn->prepare(
                "UPDATE meal_skips
                 SET status = 'Cancelled'
                 WHERE id = ?
                   AND student_id = ?
                   AND status = 'Active'"
            );

            if (!$cancel) {
                $error_message = 'Unable to prepare cancellation.';

            } else {

                $cancel->bind_param(
                    'ii',
                    $request_id,
                    $student_id
                );

                $cancel->execute();
                $changed = $cancel->affected_rows;
                $cancel->close();

                if ($changed === 1) {

                    $_SESSION['meal_skip_flash'] =
                        'Your meal skip request was cancelled successfully!';

                    $_SESSION['meal_skip_token'] =
                        bin2hex(random_bytes(32));

                    header(
                        'Location: /smartmess/student/mealskip.php'
                    );
                    exit;

                } else {
                    $error_message =
                        'This request could not be cancelled. It may already have been updated.';
                }
            }
        }
    }
}

/* Load the student's request history. */
$history = $conn->prepare(
    'SELECT id, student_id, start_date, end_date,
            breakfast, lunch, dinner, reason, status, created_at
     FROM meal_skips
     WHERE student_id = ?
     ORDER BY id DESC
     LIMIT 50'
);

if (!$history) {
    error_log(
        'Meal skip history prepare failed: ' . $conn->error
    );

    die(
        'Unable to load meal skip history. Please check the meal_skips table.'
    );
}

$history->bind_param('i', $student_id);

if (!$history->execute()) {
    error_log(
        'Meal skip history query failed: ' . $history->error
    );

    $history->close();
    die('Unable to retrieve your meal skip requests.');
}

$result = $history->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$history->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meal Skip | SmartMess</title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif}
body{background:#dce7f0;color:#142b49;min-height:100vh}
.main-workspace{margin-left:250px;width:calc(100% - 250px);padding:28px 30px;min-height:100vh}
.page-heading{background:#eef5fb;border-radius:22px;padding:24px 27px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:15px}
.page-heading h1{font-size:25px;margin-bottom:5px}
.page-heading p{font-size:13px;color:#627b98}
.heading-actions{display:flex;gap:9px;flex-wrap:wrap}
.info-pill,.back-link{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:11px;font-size:12px;font-weight:700;text-decoration:none}
.info-pill{background:#cce5fa;color:#385fa8}
.back-link{background:#385fa8;color:white}
.content-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:20px;align-items:start}
.panel{background:white;border-radius:22px;padding:24px;min-width:0;box-shadow:0 4px 15px #1f3d5e08}
.panel-heading{display:flex;align-items:center;gap:12px;padding-bottom:14px;margin-bottom:17px;border-bottom:2px solid #dce7f3}
.panel-heading i{color:#385fa8;font-size:19px}
.panel-heading h2{font-size:17px}
.form-intro,.important-info{display:flex;gap:12px;background:#edf6ff;padding:16px;border-radius:13px;margin-bottom:20px}
.form-intro>i,.important-info>i{color:#328fd0;font-size:20px;margin-top:3px}
.form-intro h3,.important-info h3{font-size:14px;margin-bottom:6px}
.form-intro p,.important-info li{font-size:12px;color:#49698e;line-height:1.8}
.date-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:19px;min-width:0}
.field>label,.field-label{display:block;font-size:13px;font-weight:650;margin-bottom:9px;color:#203d64}
.field input[type=date],.field textarea{width:100%;border:1px solid #dce7f3;border-radius:11px;padding:12px;background:#f8fbff;color:#1d3b64;font-size:13px;outline:none}
.field input:focus,.field textarea:focus{border-color:#6aa8ed;box-shadow:0 0 0 3px #3b82f31a}
.field textarea{min-height:105px;resize:vertical;line-height:1.6}
.meal-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.meal-option{display:flex;align-items:center;gap:8px;border:1px solid #e1eaf5;padding:12px 9px;border-radius:11px;font-size:12px;font-weight:650;cursor:pointer}
.meal-option input{width:17px;height:17px;accent-color:#385fa8}
.submit-button{width:100%;padding:14px;border:0;border-radius:11px;background:#263f63;color:white;font-weight:750;font-size:14px;cursor:pointer}
.submit-button:disabled{opacity:.65;cursor:wait}
.history-note{font-size:12px;color:#627b98;line-height:1.7;margin:-4px 0 15px}
.request-list{display:grid;gap:12px}
.request-card{border:1px solid #e1eaf5;border-left:4px solid #35bdf3;border-radius:13px;padding:15px;background:#f8fbff}
.request-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px}
.request-dates{font-size:13px;font-weight:700;color:#385b85;line-height:1.7}
.status{display:inline-block;padding:6px 10px;border-radius:30px;background:#dcfce7;color:#087c54;font-size:11px;font-weight:800;text-transform:capitalize;white-space:nowrap}
.status.cancelled{background:#e5edf6;color:#375271}
.status.pending{background:#ffebc9;color:#c65c00}
.status.approved{background:#d6f5e9;color:#087c54}
.status.rejected{background:#fee2e2;color:#b42318}
.detail-line{display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#49698e;line-height:1.7;margin-top:8px;overflow-wrap:anywhere}
.detail-line>i{width:15px;flex-shrink:0;margin-top:3px;color:#426b9d}
.meal-tags{display:flex;flex-wrap:wrap;gap:6px}
.meal-tag{background:#eaf3fc;color:#355786;padding:5px 9px;border-radius:7px;font-size:11px;font-weight:700}
.request-actions{display:flex;justify-content:flex-end;margin-top:12px;padding-top:10px;border-top:1px solid #e6edf5}
.cancel-button{background:#fff0f0;color:#b42318;border:0;border-radius:9px;padding:9px 12px;font-size:11px;font-weight:750;cursor:pointer}
.empty-state{background:#f8fbff;border:1px dashed #cbd9e8;color:#71869f;text-align:center;padding:32px 14px;border-radius:13px;font-size:13px;line-height:1.8}
.empty-state i{display:block;font-size:29px;color:#9db5d0;margin-bottom:12px}
.important-info{margin:18px 0 0}
.important-info ul{padding-left:17px;margin-top:5px}
.alert{padding:13px 15px;border-radius:11px;margin-bottom:17px;font-size:13px;line-height:1.6}
.alert.error{background:#fee2e2;color:#991b1b}
.popup-overlay{position:fixed;inset:0;background:#0f1f327a;display:flex;align-items:center;justify-content:center;padding:20px;z-index:9999}
.popup-card{width:100%;max-width:390px;background:white;padding:30px 25px;border-radius:22px;text-align:center;box-shadow:0 20px 60px #0003;animation:appear .25s ease-out}
.popup-icon{width:65px;height:65px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 17px}
.popup-card h2{font-size:22px;margin-bottom:9px}
.popup-card p{color:#617994;font-size:13px;line-height:1.7;margin-bottom:20px}
.popup-close{border:0;border-radius:10px;background:#2d4e7b;color:white;padding:12px 25px;font-weight:750;cursor:pointer}
@keyframes appear{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:1200px){.main-workspace{padding:22px 20px}.content-grid{gap:15px}.panel{padding:20px}}
@media(max-width:950px){.content-grid{grid-template-columns:1fr}}
@media(max-width:768px){.main-workspace{margin-left:0;width:100%;padding:16px 13px}.page-heading{padding:19px;align-items:flex-start;flex-direction:column}.panel{padding:18px}}
@media(max-width:480px){.date-grid{grid-template-columns:1fr;gap:0}.request-top{flex-direction:column}}
</style>
</head>

<body>

<?php include __DIR__ . '/../includes/student_sidebar.php'; ?>

<main class="main-workspace">

<header class="page-heading">
    <div>
        <h1>Meal Skip</h1>
        <p>Manage your meal skip requests</p>
    </div>

    <div class="heading-actions">
        <span class="info-pill">
            <i class="fa-solid fa-calendar-check"></i>
            Meal Management
        </span>

        <a class="back-link" href="/smartmess/student/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>
</header>

<?php if ($error_message !== ''): ?>
    <div class="alert error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?php echo msEscape($error_message); ?>
    </div>
<?php endif; ?>

<div class="content-grid">

    <section class="panel">
        <div class="panel-heading">
            <i class="fa-solid fa-utensils"></i>
            <h2>Submit Meal Skip Request</h2>
        </div>

        <div class="form-intro">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <h3>Need to skip a meal?</h3>
                <p>Select the dates and meals you want to skip, and provide a reason if needed.</p>
            </div>
        </div>

        <form method="POST"
              action="/smartmess/student/mealskip.php"
              id="mealSkipForm">

            <input type="hidden" name="csrf_token"
                   value="<?php echo msEscape($_SESSION['meal_skip_token']); ?>">

            <input type="hidden" name="submit_meal_skip" value="1">

            <div class="date-grid">
                <div class="field">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                           min="<?php echo msEscape($today); ?>" required>
                </div>

                <div class="field">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date"
                           min="<?php echo msEscape($today); ?>" required>
                </div>
            </div>

            <div class="field">
                <span class="field-label">Select Meals</span>

                <div class="meal-options">
                    <label class="meal-option">
                        <input type="checkbox" name="breakfast" id="breakfast">
                        Breakfast
                    </label>

                    <label class="meal-option">
                        <input type="checkbox" name="lunch" id="lunch">
                        Lunch
                    </label>

                    <label class="meal-option">
                        <input type="checkbox" name="dinner" id="dinner">
                        Dinner
                    </label>

                    <label class="meal-option">
                        <input type="checkbox" name="full_day" id="full_day">
                        Full Day
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="reason">Reason</label>
                <textarea name="reason" id="reason" maxlength="255"
                    placeholder="Enter the reason for skipping meals..."></textarea>
            </div>

            <button type="submit" class="submit-button" id="submitButton">
                <i class="fa-solid fa-paper-plane"></i> Submit Request
            </button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <i class="fa-solid fa-rectangle-list"></i>
            <h2>My Meal Skip Requests (<?php echo count($requests); ?>)</h2>
        </div>

        <p class="history-note">Your most recent requests appear first.</p>

        <?php if (empty($requests)): ?>

            <div class="empty-state">
                <i class="fa-regular fa-calendar-xmark"></i>
                <strong>No meal skip requests yet</strong>
                <p>Your saved requests will appear here after submission.</p>
            </div>

        <?php else: ?>

            <div class="request-list">

                <?php foreach ($requests as $request): ?>

                    <?php
                    $status = trim((string)$request['status']);
                    $statusKey = strtolower($status);

                    $statusClass = in_array(
                        $statusKey,
                        ['cancelled', 'pending', 'approved', 'rejected'],
                        true
                    ) ? $statusKey : '';
                    ?>

                    <article class="request-card">

                        <div class="request-top">
                            <div class="request-dates">
                                <i class="fa-regular fa-calendar-days"></i>
                                <?php echo msEscape(msDate($request['start_date'])); ?>
                                &ndash;
                                <?php echo msEscape(msDate($request['end_date'])); ?>
                            </div>

                            <span class="status <?php echo msEscape($statusClass); ?>">
                                <?php echo msEscape($status); ?>
                            </span>
                        </div>

                        <div class="detail-line">
                            <i class="fa-solid fa-utensils"></i>

                            <div class="meal-tags">
                                <?php if ((int)$request['breakfast'] === 1): ?>
                                    <span class="meal-tag">Breakfast</span>
                                <?php endif; ?>

                                <?php if ((int)$request['lunch'] === 1): ?>
                                    <span class="meal-tag">Lunch</span>
                                <?php endif; ?>

                                <?php if ((int)$request['dinner'] === 1): ?>
                                    <span class="meal-tag">Dinner</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (trim((string)$request['reason']) !== ''): ?>
                            <div class="detail-line">
                                <i class="fa-regular fa-comment-dots"></i>
                                <span><?php echo msEscape($request['reason']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($statusKey === 'active'): ?>
                            <div class="request-actions">
                                <form method="POST"
                                      action="/smartmess/student/mealskip.php"
                                      onsubmit="return confirm('Are you sure you want to cancel this request?');">

                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo msEscape($_SESSION['meal_skip_token']); ?>">

                                    <input type="hidden" name="request_id"
                                           value="<?php echo (int)$request['id']; ?>">

                                    <button type="submit"
                                            name="cancel_meal_skip"
                                            value="1"
                                            class="cancel-button">
                                        <i class="fa-solid fa-xmark"></i>
                                        Cancel Request
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="important-info">
            <i class="fa-solid fa-circle-info"></i>

            <div>
                <h3>Important Information</h3>
                <ul>
                    <li>Select the correct date range and meals.</li>
                    <li>Cancelled requests remain visible in your history.</li>
                    <li>Your requests are linked to your student account.</li>
                </ul>
            </div>
        </div>
    </section>

</div>
</main>

<?php if ($success_message !== ''): ?>
<div class="popup-overlay" id="successPopup" role="dialog" aria-modal="true">
    <div class="popup-card">
        <div class="popup-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2>Request Submitted!</h2>

        <p>
            <?php echo msEscape($success_message); ?>
            Your request has been added to your history.
        </p>

        <button type="button" class="popup-close" id="closePopup">
            OK, Got it
        </button>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const start = document.getElementById('start_date');
    const end = document.getElementById('end_date');

    const breakfast = document.getElementById('breakfast');
    const lunch = document.getElementById('lunch');
    const dinner = document.getElementById('dinner');
    const fullDay = document.getElementById('full_day');

    const form = document.getElementById('mealSkipForm');
    const submit = document.getElementById('submitButton');

    function updateEndDate() {
        end.min = start.value || start.min;

        if (start.value && end.value && end.value < start.value) {
            end.value = start.value;
        }
    }

    start.addEventListener('change', updateEndDate);

    fullDay.addEventListener('change', function () {
        if (fullDay.checked) {
            breakfast.checked = true;
            lunch.checked = true;
            dinner.checked = true;
        }
    });

    [breakfast, lunch, dinner].forEach(function (meal) {
        meal.addEventListener('change', function () {
            fullDay.checked =
                breakfast.checked &&
                lunch.checked &&
                dinner.checked;
        });
    });

    form.addEventListener('submit', function (event) {

        if (!form.checkValidity()) {
            return;
        }

        if (end.value < start.value) {
            event.preventDefault();
            alert('The end date cannot be before the start date.');
            return;
        }

        if (
            !breakfast.checked &&
            !lunch.checked &&
            !dinner.checked &&
            !fullDay.checked
        ) {
            event.preventDefault();
            alert('Please select at least one meal.');
            return;
        }

        submit.disabled = true;

        submit.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    });

    const popup = document.getElementById('successPopup');
    const close = document.getElementById('closePopup');

    if (popup && close) {

        close.addEventListener('click', function () {
            popup.remove();
        });

        popup.addEventListener('click', function (event) {
            if (event.target === popup) {
                popup.remove();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                popup.remove();
            }
        });
    }

    updateEndDate();
});
</script>

</body>
</html>
