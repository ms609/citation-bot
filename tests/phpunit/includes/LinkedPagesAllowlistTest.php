<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class LinkedPagesAllowlistTest extends testBaseClass {
    public function testOnlyNamedOperatorsMayUseNonUserSourcePages(): void {
        foreach (['Headbomb', 'AManWithNoPlan', 'Redalert2fan'] as $name) {
            $this->assertTrue(linked_pages_may_reference_non_user_page($name));
        }
        foreach (['OtherEditor', 'headbomb', '', ' Headbomb'] as $name) {
            $this->assertFalse(linked_pages_may_reference_non_user_page($name));
        }
    }

    public function testAuthorizationDoesNotIncreaseBulkPageLimit(): void {
        $this->assertTrue(linked_pages_may_reference_non_user_page('Headbomb'));
        $this->assertFalse(linked_pages_should_override_limit('Headbomb'));
        foreach (DEV_USERS as $name) {
            $this->assertTrue(linked_pages_may_reference_non_user_page($name));
            $this->assertTrue(linked_pages_should_override_limit($name));
        }
    }
}
