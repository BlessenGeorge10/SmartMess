<?php
/**
 * Smart Mess Management System
 * Sidebar Navigation Component
 * 
 * Included in all pages after header
 * Contains: Navigation menu based on user role
 */
?>
<aside class="sidebar" id="sidebar">
    <nav class="nav-menu">
        <div class="nav-section">
            <h3 class="nav-section-title">MAIN</h3>
            <a href="dashboard.php" class="nav-link active">
                <span class="nav-icon">🏠</span>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <h3 class="nav-section-title">MESS</h3>
            <a href="meal-schedule.php" class="nav-link">
                <span class="nav-icon">📅</span>
                <span>Meal Schedule</span>
            </a>
            <a href="announcements.php" class="nav-link">
                <span class="nav-icon">📢</span>
                <span>Announcements</span>
            </a>
        </div>

        <div class="nav-section">
            <h3 class="nav-section-title">FEEDBACK</h3>
            <a href="rating.php" class="nav-link">
                <span class="nav-icon">⭐</span>
                <span>Meal Rating</span>
            </a>
            <a href="feedback.php" class="nav-link">
                <span class="nav-icon">💬</span>
                <span>Feedback</span>
            </a>
        </div>

        <div class="nav-section">
            <h3 class="nav-section-title">COMPLAINTS</h3>
            <a href="complaints.php" class="nav-link">
                <span class="nav-icon">⚠️</span>
                <span>Submit Complaint</span>
            </a>
            <a href="complaint-status.php" class="nav-link">
                <span class="nav-icon">📋</span>
                <span>Track Complaints</span>
            </a>
        </div>

        <div class="nav-section">
            <h3 class="nav-section-title">ACCOUNT</h3>
            <a href="profile.php" class="nav-link">
                <span class="nav-icon">👤</span>
                <span>My Profile</span>
            </a>
            <a href="../logout.php" class="nav-link logout-link">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</aside>
