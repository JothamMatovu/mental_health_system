<?php
// edit_user.php (legacy) - redirect to new folder location
header('Location: users/edit_user.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
