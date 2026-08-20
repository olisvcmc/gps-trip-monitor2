<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdminLogin()
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function formatDurationAdmin($ms)
{
    $totalSec = max(0, (int) floor($ms / 1000));
    $h = floor($totalSec / 3600);
    $m = floor(($totalSec % 3600) / 60);
    $s = $totalSec % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

function formatDateAdmin($isoOrDatetime)
{
    $ts = strtotime($isoOrDatetime);
    if (!$ts) return $isoOrDatetime;
    return date('d M Y, H:i', $ts);
}
