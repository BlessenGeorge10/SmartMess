<?php
session_start();

// Ensure user is logged in as student
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: /smartmess/login.php");
    exit;
}

// Database connection
require_once "../db_config.php";

$user_id = $_SESSION['user_id'] ?? 0;

$success_msg = "";
$error_msg = "";

/* ==========================================
   SUBMIT FEEDBACK
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate form
    if ($category === '' || $subject === '' || $description === '') {

        $error_msg = "Please fill in all the required fields.";

    } else {

        /*
         * Your existing feedback table has only one
         * message column.
         *
         * So subject and description are combined
         * into the message column.
         */

        $message =
            "Subject: " . $subject .
            "\n\nDescription: " . $description;

        // Insert feedback into existing feedback table
        $stmt = $conn->prepare(
            "INSERT INTO feedback
            (user_id, category, message)
            VALUES (?, ?, ?)"
        );

        if ($stmt) {

            $stmt->bind_param(
                "iss",
                $user_id,
                $category,
                $message
            );

            if ($stmt->execute()) {

                $ticket_number = $stmt->insert_id;

                $success_msg =
                    "Your feedback has been submitted successfully. Ticket ID: FB-" .
                    str_pad($ticket_number, 4, '0', STR_PAD_LEFT) .
                    ". The mess warden will review it shortly.";

            } else {

                $error_msg =
                    "Unable to submit your feedback. Please try again.";
            }

            $stmt->close();

        } else {

            $error_msg =
                "Database error. Please try again.";
        }
    }
}

/* ==========================================
   FETCH STUDENT FEEDBACK
   ========================================== */

$feedback_history = [];

$stmt = $conn->prepare(
    "SELECT
        id,
        category,
        message,
        status,
        manager_response,
        created_at
     FROM feedback
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

if ($stmt) {

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $feedback_history[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Feedback - Student | SmartMess</title>

    <!-- FontAwesome Icons -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI',
                -apple-system,
                BlinkMacSystemFont,
                Roboto,
                sans-serif;
        }

        body {
            background-color: #dce7f0;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Main Application */

        .app-window {
            background-color: #dce7f0;
            width: 100vw;
            min-height: 100vh;
            display: block;
            overflow: hidden;
        }

        /* Main content beside fixed sidebar */

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

        /* Page Title */

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

        .page-title-banner p {
            font-size: 13px;
            color: #5a6e85;
            margin-top: 4px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
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
        }

        .dashboard-btn {
            background: #3a5ba0;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .dashboard-btn:hover {
            background: #2d4880;
        }

        /* Feedback Grid */

        .complaints-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 22px;
        }

        .card-panel {
            background: #eef5fb;
            border-radius: 22px;
            padding: 24px 28px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .panel-title {
            font-size: 17px;
            color: #1e2b37;
            font-weight: 800;
            border-bottom: 2px solid rgba(58, 91, 160, 0.1);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form */

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 800;
            color: #5a6e85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0e0ff;
            border-radius: 14px;
            font-size: 13px;
            color: #1e2b37;
            outline: none;
            background: #ffffff;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: #3a5ba0;
            box-shadow: 0 0 0 3px rgba(58, 91, 160, 0.1);
        }

        /* Submit Button */

        .submit-btn {
            background: #3a5ba0;
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
        }

        .submit-btn:hover {
            background: #2d4880;
        }

        /* Success Message */

        .alert-success {
            background: #dcfce7;
            color: #15803d;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Error Message */

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* History */

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 600px;
            overflow-y: auto;
        }

        .history-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px;
            border-left: 4px solid #3a5ba0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ticket-id {
            font-size: 11px;
            font-weight: 800;
            color: #5a6e85;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 8px;
            text-transform: uppercase;
        }

        .complaint-subject {
            font-size: 14px;
            font-weight: 800;
            color: #1e2b37;
        }

        .complaint-category {
            font-size: 11px;
            color: #3a5ba0;
            font-weight: 700;
        }

        .complaint-description {
            font-size: 12px;
            color: #4b5f73;
            line-height: 1.5;
            white-space: normal;
        }

        .admin-response-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            border: 1px dashed #cbd5e1;
            font-size: 12px;
            color: #2c3e50;
            margin-top: 4px;
        }

        .admin-response-box strong {
            color: #1e2b37;
            display: block;
            margin-bottom: 4px;
        }

        .ticket-date {
            font-size: 10px;
            color: #8a94a6;
            text-align: right;
            font-weight: 600;
        }

        /* No Feedback */

        .no-feedback {
            text-align: center;
            padding: 40px 20px;
            color: #7b8ca1;
            font-size: 13px;
        }

        .no-feedback i {
            font-size: 30px;
            margin-bottom: 10px;
            color: #9db2c8;
        }

        /* Responsive */

        @media (max-width: 900px) {

            .main-workspace {
                margin-left: 250px;
                width: calc(100% - 250px);
                padding: 20px;
            }

            .complaints-grid {
                grid-template-columns: 1fr;
            }

            .page-title-banner {
                align-items: flex-start;
                gap: 15px;
            }

            .page-header-actions {
                flex-wrap: wrap;
                justify-content: flex-end;
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

                    <h2>Feedback</h2>

                    <p>
                        Submit feedback and track the status of your requests.
                    </p>

                </div>

                <div class="page-header-actions">

                    <span class="badge-count">

                        <i class="fa-solid fa-headset"></i>

                        Support Desk

                    </span>

                    <a href="dashboard.php" class="dashboard-btn">

                        <i class="fa-solid fa-arrow-left"></i>

                        Dashboard

                    </a>

                </div>

            </div>

            <!-- Feedback Grid -->

            <div class="complaints-grid">

                <!-- Submit Form Panel -->

                <div class="card-panel">

                    <h3 class="panel-title">

                        <i class="fa-solid fa-paper-plane"></i>

                        Submit Ticket / Feedback

                    </h3>

                    <!-- Success Message -->

                    <?php if (!empty($success_msg)): ?>

                        <div class="alert-success">

                            <i class="fa-solid fa-circle-check"></i>

                            <?php
                            echo htmlspecialchars($success_msg);
                            ?>

                        </div>

                    <?php endif; ?>

                    <!-- Error Message -->

                    <?php if (!empty($error_msg)): ?>

                        <div class="alert-error">

                            <i class="fa-solid fa-circle-exclamation"></i>

                            <?php
                            echo htmlspecialchars($error_msg);
                            ?>

                        </div>

                    <?php endif; ?>

                    <form method="POST" action="">

                        <!-- Category -->

                        <div class="form-group">

                            <label>Category</label>

                            <select
                                name="category"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Select Category --
                                </option>

                                <option value="Food Quality">
                                    Food Quality & Taste
                                </option>

                                <option value="Hygiene & Cleanliness">
                                    Hygiene & Cleanliness
                                </option>

                                <option value="Staff Behavior">
                                    Mess Staff Behavior
                                </option>

                                <option value="Timing & Delay">
                                    Meal Delay / Timing Issue
                                </option>

                                <option value="General Feedback">
                                    General Suggestion
                                </option>

                            </select>

                        </div>

                        <!-- Subject -->

                        <div
                            class="form-group"
                            style="margin-top: 10px;"
                        >

                            <label>
                                Subject / Brief Summary
                            </label>

                            <input
                                type="text"
                                name="subject"
                                class="form-input"
                                placeholder="e.g. Cold food served during lunch"
                                required
                            >

                        </div>

                        <!-- Description -->

                        <div
                            class="form-group"
                            style="margin-top: 10px;"
                        >

                            <label>
                                Detailed Description
                            </label>

                            <textarea
                                name="description"
                                rows="4"
                                class="form-textarea"
                                placeholder="Provide specific details (date, meal time, issue background)..."
                                required
                            ></textarea>

                        </div>

                        <!-- Submit -->

                        <button
                            type="submit"
                            class="submit-btn"
                        >

                            <i class="fa-solid fa-circle-plus"></i>

                            Submit Issue Ticket

                        </button>

                    </form>

                </div>

                <!-- Submitted Tickets Panel -->

                <div class="card-panel">

                    <h3 class="panel-title">

                        <i class="fa-solid fa-list-check"></i>

                        Submitted Tickets & Status

                    </h3>

                    <div class="history-list">

                        <?php if (empty($feedback_history)): ?>

                            <div class="no-feedback">

                                <i class="fa-regular fa-comment-dots"></i>

                                <div>
                                    You have not submitted any feedback yet.
                                </div>

                            </div>

                        <?php endif; ?>

                        <?php foreach ($feedback_history as $ticket): ?>

                            <?php

                            /* Set status colours */

                            if ($ticket['status'] === 'Resolved') {

                                $status_color = '#16a34a';
                                $status_bg = '#dcfce7';

                            } elseif ($ticket['status'] === 'In Progress') {

                                $status_color = '#2563eb';
                                $status_bg = '#dbeafe';

                            } else {

                                $status_color = '#d97706';
                                $status_bg = '#fef3c7';
                            }

                            /* Create ticket number */

                            $ticket_id =
                                'FB-' .
                                str_pad(
                                    $ticket['id'],
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                );

                            /* Format date */

                            $ticket_date =
                                date(
                                    'M d, Y',
                                    strtotime($ticket['created_at'])
                                );

                            /*
                             * Separate subject and description
                             * from the existing message column.
                             */

                            $message = $ticket['message'];

                            $subject = $message;

                            $description = '';

                            if (
                                strpos(
                                    $message,
                                    "Subject: "
                                ) === 0
                            ) {

                                $parts = explode(
                                    "\n\nDescription: ",
                                    $message,
                                    2
                                );

                                $subject = str_replace(
                                    "Subject: ",
                                    "",
                                    $parts[0]
                                );

                                if (isset($parts[1])) {

                                    $description = $parts[1];
                                }
                            }

                            ?>

                            <!-- Individual Ticket -->

                            <div class="history-card">

                                <div class="card-top">

                                    <span class="ticket-id">

                                        <?php
                                        echo htmlspecialchars(
                                            $ticket_id
                                        );
                                        ?>

                                    </span>

                                    <span
                                        class="status-badge"
                                        style="
                                            background-color:
                                            <?php echo $status_bg; ?>;

                                            color:
                                            <?php echo $status_color; ?>;
                                        "
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $ticket['status']
                                        );
                                        ?>

                                    </span>

                                </div>

                                <!-- Category -->

                                <span class="complaint-category">

                                    <i class="fa-solid fa-tag"></i>

                                    <?php
                                    echo htmlspecialchars(
                                        $ticket['category']
                                    );
                                    ?>

                                </span>

                                <!-- Subject -->

                                <h4 class="complaint-subject">

                                    <?php
                                    echo htmlspecialchars(
                                        $subject
                                    );
                                    ?>

                                </h4>

                                <!-- Description -->

                                <?php if (!empty($description)): ?>

                                    <div class="complaint-description">

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $description
                                            )
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>

                                <!-- Manager Response -->

                                <div class="admin-response-box">

                                    <strong>

                                        <i class="fa-solid fa-reply"></i>

                                        Warden Response:

                                    </strong>

                                    <?php if (
                                        !empty(
                                            $ticket['manager_response']
                                        )
                                    ): ?>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $ticket[
                                                    'manager_response'
                                                ]
                                            )
                                        );
                                        ?>

                                    <?php else: ?>

                                        Pending review by Mess Warden.

                                    <?php endif; ?>

                                </div>

                                <!-- Date -->

                                <span class="ticket-date">

                                    <?php
                                    echo htmlspecialchars(
                                        $ticket_date
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </main>

    </div>

</body>

</html>