RUBBISH to KILLS TESTS

error_reporting(E_ALL); // All tests run this way

 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}


class testBaseClass extends PHPUnit\Framework\TestCase {

  public function setUp() {
  }

  public function tearDown() {
  }
}
