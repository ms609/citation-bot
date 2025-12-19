<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class archiveTest extends testBaseClass {
    public function testUseArchive1(): void {
        $text = '{{cite journal|archive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        expand_templates_from_archives($tmp_array);
        for ($x = 0; $x <= 10; $x++) {
            if ($template->get2('title') == null) {
                sleep(4); // Sometimes fails for no good reason
                expand_templates_from_archives($tmp_array);
            }
        }
        $this->assertSame('Goethe-Schiller-Denkmal - Weimarpedia', $template->get2('title'));
    }

    public function testUseArchive2(): void {
        $text = '{{cite journal|series=Xarchive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        expand_templates_from_archives($tmp_array);
        $this->assertNull($template->get2('title'));
    }
}
