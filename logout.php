<?php
require __DIR__.'/includes/auth.php';
if($_SERVER['REQUEST_METHOD']!=='POST') { header('Location: index.php',true,303); exit; }
$_SESSION=[]; session_destroy(); header('Location: index.php?logged_out=1',true,303); exit;
