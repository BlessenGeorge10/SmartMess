<?php
/**
 * Smart Mess Management System
 * Header Component
 * 
 * Included in all pages
 * Contains: Top navigation bar, logo, user info, notifications
 */
?>
<header class="header">
    <div class="header-container">
        <div class="header-left">
            <button class="hamburger-menu" id="hamburger" onclick="toggleSidebar()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="logo">
                <span class="logo-icon">🍽️</span>
                <h1>Smart Mess</h1>
            </div>
        </div>

        <div class="header-right">
            <div class="notification-area">
                <button class="notification-btn" title="Notifications">
                    <span class="notification-icon">🔔</span>
                    <span class="notification-badge">3</span>
                </button>
            </div>

            <div class="user-profile-area">
                <div class="user-info">
                    <div class="user-details">
                        <span class="username">Rajesh Kumar</span>
                        <span class="user-role">Student</span>
                    </div>
                    <div class="user-avatar">👤</div>
                </div>
            </div>
        </div>
    </div>
</header>
