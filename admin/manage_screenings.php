<?php
require __DIR__.'/../includes/auth.php'; requireAdmin();
if($_SERVER['REQUEST_METHOD']==='POST') { http_response_code(405); exit('Use the consolidated admin page.'); }
header('Location: index.php#screenings',true,303); exit;
