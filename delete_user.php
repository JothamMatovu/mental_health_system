<?php
// delete_user.php (legacy) - redirect to new users folder
header('Location: users/delete_user.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
