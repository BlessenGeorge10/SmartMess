<?php

function smartmess_password_validation_error($password)
{
    if (!is_string($password) || $password === '') {
        return "Please enter a valid password.";
    }

    $characterCount = preg_match_all('/./us', $password, $characters);
    if ($characterCount === false) {
        return "Please enter a valid password.";
    }

    if ($characterCount < 8) {
        return "Password must contain at least 8 characters.";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter (A-Z).";
    }

    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter (a-z).";
    }

    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number (0-9).";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character.";
    }

    return null;
}
