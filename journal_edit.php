<?php
// journal_edit.php (legacy) - redirect to new journal folder
header('Location: journal/journal_edit.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
