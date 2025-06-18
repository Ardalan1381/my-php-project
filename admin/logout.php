<?php
session_start();
session_destroy();
header("Location: /site/login.php");
exit;
?>