<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CaselessCitationAuthorTest extends testBaseClass {
    public function testUnstructuredCaselessNamesKeepTheirTextAndOrder(): void {
        foreach (['علي حسن', 'דן כהן', 'สม ชาย', 'राम शर्मा', '山田 太郎'] as $name) {
            $this->assertSame($name, format_author($name));
            $template = $this->make_citation('{{cite journal|title=Example article}}');
            $template->validate_and_add('author1', $name, '', '', false);
            $this->assertSame($name, $template->get('author1'));
        }
        $this->assertSame('明', clean_up_first_names('明'));
        $this->assertSame('İlhan', format_surname('İlhan'));
        $this->assertTrue(is_initials('ŁŻ'));
        $this->assertTrue(is_initials('ИП'));
        $this->assertSame('Smith, J', format_author('J. Smith'));
    }
}
