<?php
// journal_add.php (legacy) - redirect to new journal folder
header('Location: journal/journal_add.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
