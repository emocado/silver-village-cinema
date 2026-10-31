<?php
require __DIR__.'/includes/auth.php'; requireLogin(); header('Location: my_bookings.php',true,303); exit;
