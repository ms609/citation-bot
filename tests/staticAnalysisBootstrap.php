<?php

declare(strict_types=1);

if (getenv('PUBLIC_BASE_URL') === false) {
    putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
}
if (getenv('ALLOWED_HOSTS') === false) {
    putenv('ALLOWED_HOSTS=citations.toolforge.org');
}
if (getenv('ALLOWED_ORIGINS') === false) {
    putenv('ALLOWED_ORIGINS=https://citations.toolforge.org,https://mdwiki.org,https://*.wikipedia.org');
}

require_once __DIR__ . '/../src/includes/setup.php';
