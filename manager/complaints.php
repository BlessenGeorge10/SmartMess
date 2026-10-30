<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

/* Manager login protection */
if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'manager'
) {
    header("Location: /smartmess/login.php");
    exit;
}

/* Database connection */
require_once __DIR__ . '/../db_config.php';

$message = '';
$message_type = 'success';
$feedback_list = [];
$fetch_error = '';

/* Escape output safely */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Get status badge CSS class */
function getStatusClass($status)
{
    switch (strtolower(trim((string)$status))) {
        case 'in progress':
            return 'status-progress';

        case 'resolved':
            return 'status-resolved';

        default:
            return 'status-pending';
    }
}

/* Extract subject and description from feedback */
function getFeedbackDetails($message)
{
    $subject = 'Student Feedback';
    $description = (string)$message;

    if (preg_match('/Subject:\s*(.*)/i', $message, $matches)) {
        $subject = trim($matches[1]);
    }

    if (preg_match('/Description:\s*(.*)/is', $message, $matches)) {
        $description = trim($matches[1]);
    }

    return [
        'subject' => $subject,
        'description' => $description
    ];
}

/* Update complaint status and manager response */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_status'])
) {
    $feedback_id = filter_input(
        INPUT_POST,
        'feedback_id',
        FILTER_VALIDATE_INT
    );

    $status = trim($_POST['status'] ?? '');
    $remark = trim($_POST['remark'] ?? '');

    $allowed_statuses = [
        'Pending',
        'In Progress',
        'Resolved'
    ];

    if (!$feedback_id || $feedback_id < 1) {
        $message = 'Please enter a valid feedback ID.';
        $message_type = 'error';
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $message = 'Please select a valid status.';
        $message_type = 'error';
    } elseif ($remark === '') {
        $message = 'Please enter a manager response.';
        $message_type = 'error';
    } else {
        $check_stmt = $conn->prepare(
            "SELECT id FROM feedback WHERE id = ?"
        );

        if (!$check_stmt) {
            $message = 'Unable to check the feedback record.';
            $message_type = 'error';
        } else {
            $check_stmt->bind_param('i', $feedback_id);
            $check_stmt->execute();

            $check_result = $check_stmt->get_result();
            $exists = $check_result->num_rows > 0;

            $check_stmt->close();

            if (!$exists) {
                $message = 'Feedback #' . $feedback_id .
                    ' was not found.';
                $message_type = 'error';
            } else {
                $update_stmt = $conn->prepare(
                    "UPDATE feedback
                     SET status = ?, manager_response = ?
                     WHERE id = ?"
                );

                if (!$update_stmt) {
                    $message = 'Unable to prepare the database update.';
                    $message_type = 'error';
                } else {
                    $update_stmt->bind_param(
                        'ssi',
                        $status,
                        $remark,
                        $feedback_id
                    );

                    if ($update_stmt->execute()) {
                        $message = 'Feedback #' . $feedback_id .
                            ' updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Could not update the feedback.';
                        $message_type = 'error';
                    }

                    $update_stmt->close();
                }
            }
        }
    }
}

/* Load feedback and student details */
$sql = "
    SELECT
        f.id,
        f.user_id,
        f.category,
        f.message,
        f.status,
        f.manager_response,
        f.created_at,
        u.name AS student_name,
        u.student_id AS student_number
    FROM feedback f
    LEFT JOIN users u ON u.id = f.user_id
    ORDER BY f.created_at DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedback_list[] = $row;
    }

    $result->free();
} else {
    $fetch_error =
        'Unable to load feedback. Please check the database query.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Complaints & Feedback | Manager Portal</title>

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
                         Roboto, Arial, sans-serif;
        }

        /* ===== PAGE BACKGROUND - SAME AS REPORT.PHP ===== */
        body {
            background-color: #2c3e50;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #2d3748;
        }

        /* ===== APPLICATION WINDOW - SAME SIZE AS REPORT.PHP ===== */
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

        /* ===== MAIN WORKSPACE - SAME AS REPORT.PHP ===== */
        .main-workspace {
            flex: 1;
            min-width: 0;
            padding: 30px;
            overflow-y: auto;
            background: #f0f2f8;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a202c;
            font-weight: 700;
        }

        .page-header p {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
            line-height: 1.5;
        }

        /* ===== CONTENT LAYOUT ===== */
        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.5fr);
            gap: 25px;
            align-items: start;
        }

        /* ===== CONTENT CARDS ===== */
        .card-panel {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            min-width: 0;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-title i {
            color: #2c3e50;
        }

        .feedback-count {
            margin-left: auto;
            min-width: 27px;
            height: 27px;
            padding: 0 8px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 20px;
            background: #e5edf7;
            color: #2c3e50;
            font-size: 12px;
        }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            outline: none;
            background: #f8fafc;
            color: #2d3748;
            transition: border-color 0.2s, background 0.2s;
        }

        .form-control:focus {
            border-color: #6688bd;
            background: #ffffff;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
            line-height: 1.5;
        }

        .hint-text {
            color: #718096;
            font-size: 11px;
            line-height: 1.5;
            margin-top: 7px;
        }

        .btn-submit {
            width: 100%;
            background-color: #2c3e50;
            color: #ffffff;
            border: none;
            padding: 13px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-submit:hover {
            background-color: #1e2b37;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        /* ===== SUCCESS AND ERROR MESSAGES ===== */
        .alert-box {
            padding: 11px 14px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow-wrap: anywhere;
        }

        .alert-success {
            background: #e6fffa;
            border-left: 4px solid #319795;
            color: #234e52;
        }

        .alert-error {
            background: #fff5f5;
            border-left: 4px solid #e53e3e;
            color: #9b2c2c;
        }

        /* ===== COMPLAINTS TABLE ===== */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            min-width: 570px;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table th,
        .data-table td {
            text-align: left;
            padding: 13px 11px;
            font-size: 12px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .data-table th {
            font-weight: 700;
            color: #718096;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.3px;
        }

        .data-table td {
            color: #4a5568;
            line-height: 1.5;
        }

        .data-table tbody tr:hover {
            background: #fbfcff;
        }

        /* ===== STATUS LABELS ===== */
        .badge-status {
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            white-space: nowrap;
        }

        .status-pending {
            background: #feebc8;
            color: #c05621;
        }

        .status-progress {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .status-resolved {
            background: #c6f6d5;
            color: #22543d;
        }

        /* ===== STUDENT AND COMPLAINT DETAILS ===== */
        .student-name,
        .subject-text {
            color: #2d3748;
            font-weight: 700;
        }

        .subject-text {
            margin-bottom: 5px;
        }

        .small-text {
            display: block;
            color: #8a97a9;
            font-size: 10px;
            margin-top: 5px;
        }

        .description-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .manager-response {
            background: #f8fafc;
            border-radius: 8px;
            padding: 9px;
            margin-top: 9px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 11px;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            background: #f8fafc;
            border: 1px dashed #d8e1ed;
            border-radius: 12px;
            padding: 25px 16px;
            text-align: center;
            color: #718096;
            font-size: 12px;
            line-height: 1.7;
        }

        .empty-state i {
            display: block;
            font-size: 26px;
            color: #a0aec0;
            margin-bottom: 10px;
        }

        /* ===== TABLET RESPONSIVENESS ===== */
        @media (max-width: 1050px) {
            body {
                align-items: flex-start;
            }

            .app-window {
                min-height: calc(100vh - 40px);
            }

            .content-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        /* ===== MOBILE RESPONSIVENESS ===== */
        @media (max-width: 650px) {
            body {
                padding: 8px;
            }

            .app-window {
                flex-direction: column;
                border-radius: 20px;
                min-height: calc(100vh - 16px);
            }

            .main-workspace {
                padding: 20px;
            }

            .content-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 20px;
            }

            .card-panel {
                padding: 18px;
            }

            .page-header h2 {
                font-size: 21px;
            }

            .data-table th,
            .data-table td {
                padding: 11px 9px;
            }
        }
    </style>
</head>

<body>

<div class="app-window">

    <?php
    /* Use the same shared sidebar as report.php */
    $manager_sidebar_theme = 'legacy';
    include __DIR__ . '/../includes/manager_sidebar.php';
    ?>

    <main class="main-workspace">

        <header class="page-header">
            <h2>Student Complaints & Feedback</h2>
            <p>Track, address, and resolve student issues efficiently.</p>
        </header>

        <section class="content-grid">

            <!-- Respond / Update Status Form -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-pen-to-square"></i>
                    Respond / Update Status
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert-box <?php
                        echo $message_type === 'success'
                            ? 'alert-success'
                            : 'alert-error';
                    ?>">
                        <?php echo e($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($fetch_error !== ''): ?>
                    <div class="alert-box alert-error">
                        <?php echo e($fetch_error); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">

                    <div class="form-group">
                        <label for="feedback_id">Feedback ID</label>

                        <input
                            type="number"
                            id="feedback_id"
                            name="feedback_id"
                            class="form-control"
                            min="1"
                            placeholder="Enter feedback ID"
                            required
                        >

                        <p class="hint-text">
                            Use the ID shown in the complaints table,
                            such as 1, 2, or 3.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="status">Update Status</label>

                        <select
                            id="status"
                            name="status"
                            class="form-control"
                            required
                        >
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="remark">
                            Manager Response / Action Taken
                        </label>

                        <textarea
                            id="remark"
                            name="remark"
                            class="form-control"
                            placeholder="Describe the action taken or response to the student..."
                            required
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        name="update_status"
                        value="1"
                        class="btn-submit"
                    >
                        <i class="fa-solid fa-check-double"></i>
                        Save Response
                    </button>

                </form>

            </div>

            <!-- Complaints Retrieved From Database -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-list-check"></i>
                    All Complaints

                    <span class="feedback-count">
                        <?php echo count($feedback_list); ?>
                    </span>
                </div>

                <?php if ($fetch_error === ''): ?>

                    <?php if (!empty($feedback_list)): ?>

                        <div class="table-wrapper">

                            <table class="data-table">

                                <thead>
                                    <tr>
                                        <th>ID / Date</th>
                                        <th>Student</th>
                                        <th>Subject & Details</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                <?php foreach ($feedback_list as $feedback): ?>

                                    <?php
                                    $details = getFeedbackDetails(
                                        $feedback['message']
                                    );

                                    $student_name =
                                        $feedback['student_name']
                                        ?: 'Student #' . $feedback['user_id'];

                                    $student_number =
                                        $feedback['student_number'] ?? '';

                                    $date_display = '';

                                    if (!empty($feedback['created_at'])) {
                                        $timestamp = strtotime(
                                            $feedback['created_at']
                                        );

                                        if ($timestamp !== false) {
                                            $date_display = date(
                                                'd M Y, h:i A',
                                                $timestamp
                                            );
                                        }
                                    }

                                    $status_display =
                                        $feedback['status'] ?: 'Pending';
                                    ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                #<?php
                                                echo (int)$feedback['id'];
                                                ?>
                                            </strong>

                                            <span class="small-text">
                                                <?php echo e($date_display); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="student-name">
                                                <?php
                                                echo e($student_name);
                                                ?>
                                            </span>

                                            <?php if ($student_number !== ''): ?>
                                                <span class="small-text">
                                                    Student ID:
                                                    <?php
                                                    echo e($student_number);
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="subject-text">
                                                <?php
                                                echo e($details['subject']);
                                                ?>
                                            </div>

                                            <div class="description-text">
                                                <?php
                                                echo e(
                                                    $details['description']
                                                );
                                                ?>
                                            </div>

                                            <span class="small-text">
                                                Category:
                                                <?php
                                                echo e($feedback['category']);
                                                ?>
                                            </span>

                                            <?php if (
                                                !empty(
                                                    $feedback['manager_response']
                                                )
                                            ): ?>

                                                <div class="manager-response">
                                                    <strong>
                                                        Manager response:
                                                    </strong>

                                                    <?php
                                                    echo e(
                                                        $feedback[
                                                            'manager_response'
                                                        ]
                                                    );
                                                    ?>
                                                </div>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge-status <?php
                                                echo e(
                                                    getStatusClass(
                                                        $status_display
                                                    )
                                                );
                                            ?>">
                                                <?php
                                                echo e($status_display);
                                                ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            No student complaints or feedback have been
                            submitted yet.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>