<!DOCTYPE html>
<html>
<head>
  <title>This API has moved</title>
</head>
<body>
  <p>
<?php
$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : 'YOUR_USER_ID';
$slow = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : '0';
$cat  = isset($_REQUEST["cat"])  ? $_REQUEST["cat"]  : 'THE_CATEGOY_YOU_WANT';
echo "Access categories API like this  https://tools.wmflabs.org/citations/process_page.php?slow=" . $slow . "&user=" . $user . "&cat=" . $cat;
?>
  </p>
</body>
</html>
