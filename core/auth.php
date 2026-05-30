<?php

function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdminUser(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
