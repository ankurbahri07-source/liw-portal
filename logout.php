<?php
require __DIR__ . '/inc/boot.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf(); $u = me(); if ($u) audit((int)$u['id'], 'logout'); sess_start(); $_SESSION = []; session_destroy(); }
redirect(url('login.php'));
