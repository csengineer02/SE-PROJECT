<?php
require_once __DIR__ . '/rider_auth.php';
unset($_SESSION['rider']);
session_regenerate_id(true);
redirect('/delivery_rider/login.php');
