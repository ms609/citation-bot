<?php

require_once __DIR__ . '/../testBaseClass.php';
 
final class wikiFunctionsTest extends testBaseClass {
  
  public function testIsValidUser() {
    $result = is_valid_user('Smith609');
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Not_a_valid_user_at_Dec_2017'); 
    $this->assertEquals(FALSE, $result);
    $result = is_valid_user('ericlewin2001@yahoo.com'); 
    $this->assertEquals(FALSE, $result);
  }
  
}
