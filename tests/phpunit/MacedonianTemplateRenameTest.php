<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

$previous_post = $_POST ?? null;
$_POST = ['wiki_base' => 'mk'];
require_once dirname(__DIR__) . '/testBaseClass.php';
if ($previous_post === null) {
    unset($_POST);
} else {
    $_POST = $previous_post;
}
unset($previous_post);

final class MacedonianTemplateRenameTest extends testBaseClass {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCyrillicCapitalizationAndWhitespaceSurviveRename(): void {
        $this->assertSame('mk', WIKI_BASE);
        foreach ([
            'Наведена мрежна страница' => 'Наведено списание',
            'наведена мрежна страница' => 'наведено списание',
            ' Наведена мрежна страница ' => ' Наведено списание ',
        ] as $original => $renamed) {
            $template = $this->make_citation('{{' . $original . '|title=Example article|journal=Example Journal}}');
            $template->change_name_to('cite journal');
            $this->assertStringStartsWith('{{' . $renamed . '|', $template->parsed_text());
        }
    }
}
