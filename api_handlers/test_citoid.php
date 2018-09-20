<?php
$posted = $_POST;

if (isset($posted)) {
  require_once('citoid.php');
  echo citoid_request($posted);
} else {
  echo json_encode(['error' => 'No POSTdata received']);
}

?>