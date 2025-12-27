<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class ieeeTest extends testBaseClass {

    public function testIEEEdoi(): void {
        $url = "https://ieeexplore.ieee.org/document/4242344";
        $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
        if ($template->get('doi') === "") {
            sleep(run_type_mods(10, 10, 10, 5, 5))
            $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
        }
        $this->assertSame('10.1109/ISSCC.2007.373373', $template->get2('doi'));
    }

    public function testIEEEdropBadURL(): void {
        $template = $this->process_citation('{{cite journal | url = https://ieeexplore.ieee.org/document/4242344341324324123412343214 |doi =10.1109/ISSCC.2007.373373 }}');
        $this->assertNull($template->get2('url'));
    }
}
