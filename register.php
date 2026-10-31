<?php
require __DIR__.'/includes/auth.php';
header('Location: account.php'.(isset($_GET['redirect'])?'?redirect='.rawurlencode(safeLocalRedirect($_GET['redirect'])):'').'#register',true,303); exit;
