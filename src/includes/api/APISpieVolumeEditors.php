<?php

declare(strict_types=1);

/** Maximum repository-local, verified SPIE editor dataset size (bytes). */
const SPIE_VOLUME_EDITOR_JSON_MAX_BYTES = 512 * 1024;

/**
 * Offline SPIE proceedings volume editor enrichment.
 *
 * The JSON file contains *independently verified editorial roles*, not
 * Crossref or OpenAlex authorships. To add a volume, record its full title,
 * number, publication year, complete ordered editor roster, and source URLs.
 * Neither a publisher DOI prefix nor a matching author list establishes an
 * editor role on its own.
 *
 * @return array<string, mixed>
 */
function spie_volume_editor_data(): array {
    static $records = null;
    if ($records !== null) {
        return $records;
    }
    $records = [];
    $path = __DIR__ . '/../data/spie_volume_editors.json';
    // Read at most one byte beyond the limit to reject oversized files without
    // allocating the entire contents. This remains a fixed local path.
    $raw = @file_get_contents($path, false, null, 0, SPIE_VOLUME_EDITOR_JSON_MAX_BYTES + 1);
    if (!is_string($raw) || $raw === '' || mb_strlen($raw, '8bit') > SPIE_VOLUME_EDITOR_JSON_MAX_BYTES) {
        return $records;
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $records = $decoded;
    }
    return $records;
}

/** Match names conservatively; never infer titles or volume numbers. */
function spie_editor_data_normalize(string $value): string {
    $value = mb_strtolower(mb_trim($value));
    $normalized = preg_replace('~\s+~u', ' ', $value);
    return is_string($normalized) ? $normalized : '';
}

/**
 * @return array<string, string> A complete editor field list, or nothing.
 */
function spie_verified_volume_editor_fields(string $title, string $volume, string $year): array {
    if (!preg_match('~^[1-9][0-9]{2,5}$~D', $volume) ||
        !preg_match('~^[12][0-9]{3}$~D', $year)) {
        return [];
    }
    $record = spie_volume_editor_data()[$volume . ':' . $year] ?? null;
    if (!is_array($record) || !is_string($record['title'] ?? null) ||
        spie_editor_data_normalize($title) !== spie_editor_data_normalize($record['title']) ||
        !is_array($record['editors'] ?? null) ||
        !array_is_list($record['editors']) ||
        count($record['editors']) < 1 || count($record['editors']) > 30 ||
        !is_array($record['sources'] ?? null) || $record['sources'] === []) {
        return [];
    }
    $fields = [];
    foreach ($record['editors'] as $index => $editor) {
        if (!is_array($editor) ||
            !is_string($editor['given'] ?? null) || !is_string($editor['family'] ?? null)) {
            return [];
        }
        $given = mb_trim($editor['given']);
        $family = mb_trim($editor['family']);
        if ($given === '' || $family === '' ||
            preg_match('~[{}<>|\[\]\x00-\x1f]~u', $given . $family)) {
            return [];
        }
        $number = (string) ($index + 1);
        $fields['editor-last' . $number] = $family;
        $fields['editor-first' . $number] = $given;
    }
    return $fields;
}

/**
 * Refuse to mix the offline roster with any existing editor-name parameter,
 * regardless of CS1 alias or numbering. Link/mask-only fields are not names.
 */
function spie_citation_has_editors(Template $template): bool {
    if ($template->had_initial_editor()) {
        return true;
    }
    // A conservative check of the serialized template also catches aliases
    // beyond editor99 and editor fields added since initial construction.
    $pattern = '~\|\s*(?:editors?|veditors?|editor(?:\d+)?|'
        . 'editor-(?:last|first|surname|given)\d*|'
        . 'editor\d+-(?:last|first|surname|given)|others)\s*=\s*(?=\S)~iu';
    return preg_match($pattern, $template->parsed_text()) === 1;
}

/** Add only the complete independently verified roster, never partial lists. */
function spie_add_verified_volume_editors(Template $template, string $doi): bool {
    if (!str_starts_with(mb_strtolower($doi), '10.1117/12.')) {
        return false;
    }
    $title = $template->get_without_comments_and_placeholders('title');
    $volume = $template->get_without_comments_and_placeholders('volume');
    $year = $template->get_without_comments_and_placeholders('year');
    if ($year === '' && preg_match('~\b([12][0-9]{3})\b~', $template->get('date'), $match)) {
        $year = $match[1];
    }
    $fields = spie_verified_volume_editor_fields($title, $volume, $year);
    if ($fields === []) {
        return false;
    }

    $series = $template->get_without_comments_and_placeholders('series');
    if (preg_match('~^\[\[([^\]|]+)(?:\|[^\]]+)?\]\]$~u', $series, $match)) {
        $series = $match[1];
    }
    if ($series !== '' && !in_array(spie_editor_data_normalize($series),
        ['proceedings of spie', 'spie proceedings'], true)) {
        return false;
    }
    if (spie_citation_has_editors($template)) {
        return false;
    }
    // The empty API label is intentional: these editor names came from the
    // local, sourced data file, not Crossref or another live service.
    return $template->add_editor_fields_atomically($fields, '');
}
