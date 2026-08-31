<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class UnpaywallHandleTest extends testBaseClass {
    public function testCanonicalHandleUrlIsNotChangedToDifferentHandle(): void {
        $this->assertSame(
            'https://hdl.handle.net/10125/20269',
            normalize_unpaywall_handle_url('https://hdl.handle.net/10125/20269')
        );
    }

    public function testLegacyCitationBotHandleUrlIsRepaired(): void {
        $this->assertSame(
            'https://hdl.handle.net/10125/20269',
            normalize_unpaywall_handle_url('https://hdl.handle.net/handle/10125/20269')
        );
    }

    public function testUnrelatedUrlIsUnchanged(): void {
        $url = 'https://example.org/handle/10125/20269';
        $this->assertSame($url, normalize_unpaywall_handle_url($url));
    }
}
