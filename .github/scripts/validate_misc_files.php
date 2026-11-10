<?php
/**
 * Validate formats and deployment metadata not covered by existing linters.
 * Run: php .github/scripts/validate_misc_files.php [--self-test]
 */
declare(strict_types=1);

const REPO_ROOT = __DIR__ . '/../..';

function requireValid(bool $valid, string $message): void {
    if (!$valid) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $settings */
function validateIniSettings(array $settings): void {
    foreach (['session.cookie_httponly', 'session.use_only_cookies',
        'session.cookie_secure', 'session.use_strict_mode'] as $setting) {
        requireValid(($settings[$setting] ?? null) === '1', "$setting must be enabled");
    }
    requireValid(
        in_array($settings['session.cookie_samesite'] ?? null, ['Lax', 'Strict'], true),
        'session.cookie_samesite must be Lax or Strict'
    );
}

function validateRobotsText(string $text): void {
    $hasUserAgent = false;
    $forAllAgents = false;
    $allowHome = false;
    $disallowOthers = false;

    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim(explode('#', $line, 2)[0]);
        if ($line === '') {
            continue;
        }
        requireValid((bool) preg_match('/^([a-z-]+)\s*:\s*(.*)$/iD', $line, $parts),
            "Invalid robots.txt directive: $line");
        $directive = strtolower($parts[1]);
        $value = trim($parts[2]);
        if ($directive === 'user-agent') {
            requireValid($value !== '', 'Empty robots.txt user-agent');
            $hasUserAgent = true;
            $forAllAgents = $value === '*';
        } elseif ($directive === 'allow' || $directive === 'disallow') {
            requireValid($hasUserAgent, 'robots.txt rules must follow a user-agent');
            requireValid($value === '' || str_starts_with($value, '/'),
                "Invalid robots.txt path: $value");
            if ($forAllAgents) {
                $allowHome = $allowHome || ($directive === 'allow' && $value === '/$');
                $disallowOthers = $disallowOthers || ($directive === 'disallow' && $value === '/');
            }
        } elseif ($directive === 'sitemap') {
            requireValid((bool) filter_var($value, FILTER_VALIDATE_URL), 'Invalid sitemap URL');
        } elseif ($directive === 'crawl-delay') {
            requireValid(is_numeric($value) && (float) $value >= 0, 'Invalid crawl-delay');
        } else {
            throw new RuntimeException("Unknown robots.txt directive: $directive");
        }
    }
    requireValid($allowHome && $disallowOthers,
        'robots.txt must allow only the root URL and disallow other paths for User-agent: *');
}

/** @param mixed $jobs */
function validateToolforgeJobs(mixed $jobs): void {
    requireValid(is_array($jobs) && array_is_list($jobs) && count($jobs) > 0,
        'toolforge.yaml must be a nonempty list of jobs');
    $names = [];
    foreach ($jobs as $job) {
        requireValid(is_array($job), 'Toolforge job must be a mapping');
        foreach (['name', 'image', 'command', 'schedule'] as $key) {
            requireValid(isset($job[$key]) && is_string($job[$key]) && trim($job[$key]) !== '',
                "Toolforge job is missing a nonempty $key");
        }
        requireValid((bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $job['name']),
            'Toolforge job name must be lowercase and hyphenated');
        requireValid(!isset($names[$job['name']]), "Duplicate Toolforge job: {$job['name']}");
        $names[$job['name']] = true;
        $parts = preg_split('/\s+/', trim($job['schedule']));
        requireValid(count($parts) === 5, "Invalid five-field cron schedule: {$job['schedule']}");
        foreach ($parts as $part) {
            requireValid((bool) preg_match('/^(?:\*|\d+)(?:[-\/,]\d+)*$/D', $part),
                "Invalid cron field: $part");
        }
        if (array_key_exists('retry', $job)) {
            requireValid(is_int($job['retry']) && $job['retry'] >= 0,
                'Toolforge retry must be a nonnegative integer');
        }
    }
}

/** @param mixed $catalog */
function validateBackstageCatalog(mixed $catalog): void {
    requireValid(is_array($catalog), 'catalog-info.yaml must be a mapping');
    requireValid(($catalog['apiVersion'] ?? null) === 'backstage.io/v1alpha1',
        'Unexpected Backstage apiVersion');
    requireValid(($catalog['kind'] ?? null) === 'Component', 'Backstage kind must be Component');
    foreach (['metadata' => ['name'], 'spec' => ['type', 'lifecycle', 'owner']] as $section => $keys) {
        requireValid(isset($catalog[$section]) && is_array($catalog[$section]),
            "Backstage $section must be a mapping");
        foreach ($keys as $key) {
            requireValid(isset($catalog[$section][$key]) && is_string($catalog[$section][$key])
                && trim($catalog[$section][$key]) !== '', "Backstage $section.$key is required");
        }
    }
    if (isset($catalog['spec']['providesApis'])) {
        requireValid(is_array($catalog['spec']['providesApis'])
            && array_is_list($catalog['spec']['providesApis']),
            'Backstage providesApis must be a list');
        foreach ($catalog['spec']['providesApis'] as $api) {
            requireValid(is_string($api) && $api !== '', 'Backstage API references must be strings');
        }
    }
}

function validateRisText(string $text): void {
    $started = false;
    $ended = false;
    foreach (preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', $text)) as $line) {
        if (trim($line) === '') {
            continue;
        }
        if (!$started && preg_match('/^(Provider|Database|Content):\s*/', $line)) {
            continue; // Archived JSTOR response headers precede the RIS record.
        }
        requireValid(!$ended, 'RIS data after the ER record terminator');
        requireValid((bool) preg_match('/^([A-Z][A-Z0-9]) {1,2}-\s*(.*)$/D', $line, $parts),
            "Malformed RIS record line: $line");
        if (!$started) {
            requireValid($parts[1] === 'TY' && $parts[2] !== '', 'RIS must begin with TY');
            $started = true;
        } elseif ($parts[1] === 'ER') {
            $ended = true;
        }
    }
    requireValid($started && $ended, 'RIS must contain TY and ER records');
}

function validateImageFiles(string $directory): void {
    requireValid(extension_loaded('gd'), 'GD extension is required to decode image assets');
    $count = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $directory, FilesystemIterator::SKIP_DOTS
    ));
    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'gif'], true)) {
            continue;
        }
        ++$count;
        $path = $file->getPathname();
        $info = @getimagesize($path);
        $expectedType = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_GIF;
        requireValid(is_array($info) && $info[2] === $expectedType
            && $info[0] > 0 && $info[1] > 0, "Invalid image header: $path");
        $decoded = $extension === 'png' ? @imagecreatefrompng($path) : @imagecreatefromgif($path);
        requireValid($decoded instanceof GdImage, "Unable to decode image: $path");
        echo "$path: decoded {$info[0]}x{$info[1]}\n";
    }
    requireValid($count > 0, 'No PNG or GIF images found in src/assets');
}

function expectInvalid(callable $operation, string $name): void {
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException("Self-test did not reject invalid $name");
}

function runSelfTests(): void {
    $settings = [
        'session.cookie_httponly' => '1', 'session.use_only_cookies' => '1',
        'session.cookie_secure' => '1', 'session.use_strict_mode' => '1',
        'session.cookie_samesite' => 'Lax',
    ];
    validateIniSettings($settings);
    expectInvalid(static fn () => validateIniSettings(array_replace($settings,
        ['session.cookie_secure' => '0'])), 'INI security');
    $robots = "User-agent: *\nAllow: /$\nDisallow: /\n";
    validateRobotsText($robots);
    expectInvalid(static fn () => validateRobotsText("User-agent: *\nAllow: /\n"), 'robots policy');
    expectInvalid(static fn () => validateRobotsText("Allow: /$\nDisallow: /\n"), 'robots structure');
    $jobs = [['name' => 'example-job', 'image' => 'php8.4', 'command' => 'php worker.php',
        'schedule' => '0 */8 * * *', 'retry' => 0]];
    validateToolforgeJobs($jobs);
    expectInvalid(static fn () => validateToolforgeJobs([$jobs[0], $jobs[0]]), 'duplicate job');
    expectInvalid(static fn () => validateToolforgeJobs([array_replace($jobs[0],
        ['schedule' => 'invalid'])]), 'cron schedule');
    $catalog = ['apiVersion' => 'backstage.io/v1alpha1', 'kind' => 'Component',
        'metadata' => ['name' => 'citation-bot'],
        'spec' => ['type' => 'website', 'lifecycle' => 'active', 'owner' => 'user:ms609']];
    validateBackstageCatalog($catalog);
    expectInvalid(static fn () => validateBackstageCatalog(array_replace($catalog,
        ['spec' => ['type' => 'website']])), 'Backstage metadata');
    validateRisText("Provider: JSTOR\n\nTY  - JOUR\nTI  - Example\nER  -\n");
    expectInvalid(static fn () => validateRisText("TY  - JOUR\nTI  - Example\n"), 'RIS terminator');
    echo "Misc-file validator self-tests passed\n";
}

try {
    if (($argv[1] ?? null) === '--self-test') {
        runSelfTests();
    } else {
        requireValid(($argv[1] ?? null) === null, 'Unexpected argument');
        requireValid(extension_loaded('yaml'), 'YAML extension is required for metadata validation');
        $settings = @parse_ini_file(REPO_ROOT . '/src/.user.ini', false, INI_SCANNER_RAW);
        requireValid(is_array($settings), 'Unable to parse src/.user.ini');
        validateIniSettings($settings);
        validateRobotsText((string) file_get_contents(REPO_ROOT . '/src/robots.txt'));
        validateToolforgeJobs(@yaml_parse_file(REPO_ROOT . '/toolforge.yaml'));
        validateBackstageCatalog(@yaml_parse_file(REPO_ROOT . '/catalog-info.yaml'));
        foreach (glob(REPO_ROOT . '/attic/ris/fixtures/*.ris') ?: [] as $risFile) {
            $contents = file_get_contents($risFile);
            requireValid($contents !== false, "Unable to read $risFile");
            validateRisText($contents);
        }
        validateImageFiles(REPO_ROOT . '/src/assets');
        echo "Miscellaneous file checks passed\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
