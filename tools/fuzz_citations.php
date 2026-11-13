<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    return;
}
if (extension_loaded('uopz') && ini_get('uopz.exit') !== '1') {
    throw new RuntimeException('Run the fuzz harness with php -d uopz.exit=1');
}

$seed_input = $argv[1] ?? '';
$mode = $argv[3] ?? '';
$single_case = $mode === '--case';
$random_cases = $mode === '--random';
$replay_seed = null;
if ($single_case && $seed_input !== str_repeat('0', 64) && preg_match('~\A[0-9a-f]{64}\z~', $seed_input) === 1) {
    $replay_seed = hex2bin($seed_input);
}
$seed = match (true) {
    $random_cases, $replay_seed !== null => null,
    $seed_input === '' => random_int(0, 2147483647),
    default => filter_var($seed_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]),
};
$max_iterations = $seed === null ? 400000 : 100000;
$iterations = filter_var(
    $argv[2] ?? '10000',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => $max_iterations]]
);
if ($seed === false || $replay_seed === false || $iterations === false
    || count($argv ?? []) > (($single_case || $random_cases) ? 4 : 3)
    || ($single_case && $seed_input === '') || ($random_cases && $seed_input !== '')) {
    fwrite(STDERR, "Usage: php tools/fuzz_citations.php [seed 0–2147483647] [iterations 1–100000]\n");
    fwrite(STDERR, "Fresh random cases: php tools/fuzz_citations.php '' ITERATIONS --random (1–400000)\n");
    fwrite(STDERR, "Replay one case: php tools/fuzz_citations.php SEED_OR_CASE_SEED CASE_NUMBER --case\n");
    exit(2);
}

// Load the parser without the application bootstrap or local credentials.
putenv('PUBLIC_BASE_URL=https://example.invalid');
define('CI', true);
define('HTML_OUTPUT', false);
define('WIKI_BASE', 'en');

require_once __DIR__ . '/../src/includes/Template.php';

mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) !== 0) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

function fuzz_check_parameter(string $input, string $name, string $value): void {
    $parameter = new Parameter();
    $parameter->parse_text($input);
    $serialized = $parameter->parsed_text();
    $reparsed = new Parameter();
    $reparsed->parse_text($serialized);
    if ($parameter->param !== $name || $parameter->val !== $value
        || $reparsed->param !== $name || $reparsed->val !== $value || $reparsed->parsed_text() !== $serialized) {
        throw new RuntimeException('Parameter round trip changed its name, value or serialization');
    }
}

/**
 * @param string $input
 * @param string $name
 * @param array<string, string|null> $expected_fields
 */
function fuzz_check_template(string $input, string $name, array $expected_fields): void {
    $template = new Template();
    $template->parse_text($input);
    $serialized = $template->parsed_text();
    $reparsed = new Template();
    $reparsed->parse_text($serialized);
    if ($template->wikiname() !== $name || $reparsed->wikiname() !== $name || $reparsed->parsed_text() !== $serialized) {
        throw new RuntimeException('Template round trip changed its name or serialization');
    }
    foreach ($expected_fields as $field => $expected) {
        if ($template->get2($field) !== $expected || $reparsed->get2($field) !== $expected) {
            throw new RuntimeException("Template round trip changed field $field");
        }
    }
}

$parameter_edges = [
    ['title= ', 'title', ''],
    ["title=\n\t", 'title', ''],
    ['pages=0', 'pages', '0'],
    ['url=https://example.invalid/?a=b=c', 'url', 'https://example.invalid/?a=b=c'],
    ['[[Example' . PIPE_PLACEHOLDER . 'label]]', '', '[[Example|label]]'],
    ["\u{00A0}title\u{00A0}=\u{00A0}東京\u{00A0}", 'title', '東京'],
    [" title = a\x00\xC0\xAFz", 'title', "a\x00\xC0\xAFz"],
];
$title_parts = ['A', 'é', "e\u{0301}", '東京', 'مرحبا', '&amp;', '=', '–', "''text''", "x\u{202F}y", '[[Link|label]]', '[[Link]]'];
$template_names = ['cite journal', 'cite book', 'cite web', 'citation', 'Cite_journal'];
$ascii_spacing = ['', ' ', "\n", "\t", "\r\n "];
$template_spacing = [...$ascii_spacing, "\u{00A0}"];
$optional_page_values = [null, '', '0', '9–12'];

$first_iteration = $single_case ? $iterations : 1;
$seed_description = match (true) {
    $random_cases => 'fresh OS seeds',
    $replay_seed !== null => "case_seed=$seed_input",
    default => "seed=$seed",
};
echo 'Citation fuzz PHP ', PHP_VERSION, " $seed_description cases=$first_iteration..$iterations\n";
for ($iteration = $first_iteration; $iteration <= $iterations; $iteration++) {
    // Each case has its own random sequence, so replay can jump straight to it.
    $case_seed = $replay_seed ?? ($random_cases ? random_bytes(32) : hash('sha256', "$seed:$iteration", true));
    $random = new Random\Randomizer(new Random\Engine\Xoshiro256StarStar($case_seed));
    $target = 'Parameter';
    if ($seed !== null && $iteration <= count($parameter_edges)) {
        [$input, $name, $value] = $parameter_edges[$iteration - 1];
    } else {
        // Use ASCII boundaries because invalid UTF-8 triggers the byte-mode parser.
        $value = 'a' . $random->getBytes($random->getInt(1, 256)) . 'z';
        $name = 'title';
        $space = $ascii_spacing[$random->getInt(0, count($ascii_spacing) - 1)];
        $input = $space . $name . $space . '=' . $space . $value . $space;
    }

    try {
        fuzz_check_parameter($input, $name, $value);

        $target = 'Template';
        $parts = ['[[Example|label]]'];
        $part_count = $random->getInt(1, 24);
        for ($part = 0; $part < $part_count; $part++) {
            $parts[] = $title_parts[$random->getInt(0, count($title_parts) - 1)];
        }
        $name = $template_names[$random->getInt(0, count($template_names) - 1)];
        $fields = [
            'title' => implode(' ', $parts),
            'doi' => '10.1234/fuzz.' . $random->getInt(1, 1000000),
            'url' => 'https://example.invalid/?a=b',
            'pages' => $optional_page_values[$random->getInt(0, count($optional_page_values) - 1)],
        ];
        $input = '{{' . $name;
        foreach ($random->shuffleArray(array_keys($fields)) as $field) {
            if ($fields[$field] !== null) {
                $space = $template_spacing[$random->getInt(0, count($template_spacing) - 1)];
                $input .= '|' . $space . $field . $space . '=' . $space . $fields[$field] . $space;
            }
        }
        $input .= '}}';
        fuzz_check_template($input, $name === 'Cite_journal' ? 'cite journal' : $name, $fields);
    } catch (Throwable $error) {
        fwrite(STDERR, "Fuzz failure in $target at iteration $iteration: " . $error->getMessage() . "\n");
        fwrite(STDERR, 'Input (base64): ' . base64_encode($input) . "\n");
        $replay_input = $seed ?? bin2hex($case_seed);
        fwrite(STDERR, "Replay one case: php tools/fuzz_citations.php $replay_input $iteration --case\n");
        if ($seed !== null) {
            fwrite(STDERR, "Replay through failure: php tools/fuzz_citations.php $seed $iteration\n");
        }
        exit(1);
    }
}
$tested = $iterations - $first_iteration + 1;
echo "Passed $tested cases per target\n";
