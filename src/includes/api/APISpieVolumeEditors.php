<?php

declare(strict_types=1);

/** Maximum repository-local SPIE editor dataset size (bytes). */
const SPIE_VOLUME_EDITOR_JSON_MAX_BYTES = 512 * 1024;

/**
 * Offline SPIE proceedings volume editor enrichment.
 *
 * The local rosters are curated from DBLP proceedings editor metadata; SPIE's
 * publisher title list corroborates volume titles and years, not editor names.
 * Individual editor roles should be checked against publisher front matter or
 * another independent source before considering a roster independently verified.
 * A matching DOI prefix or article authorship is not evidence of editor roles.
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
 * Refuse to mix the local roster with any populated editor-name parameter,
 * regardless of CS1 alias or numbering. Link/mask-only fields are not names.
 * Use the shared structural helper, retaining the initial-editor safeguard.
 */
function spie_citation_has_editors(Template $template): bool {
    return $template->had_initial_editor() || $template->has_any_editor_parameter();
}

/**
 * Only a specific proceedings title can identify this roster. A paper title
 * may remain in |title=; its mismatch is not a container-title conflict.
 * Generic SPIE series labels are neutral, not proof of a volume match.
 */
function spie_citation_matches_volume_title(Template $template, string $volume_title): bool {
    $expected = spie_editor_data_normalize($volume_title);
    if ($expected === '') {
        return false;
    }

    $matched = false;
    foreach (['book-title', 'journal', 'periodical', 'series'] as $field) {
        $value = $template->get_without_comments_and_placeholders($field);
        if (preg_match('~^\[\[([^\]|]+)(?:\|[^\]]+)?\]\]$~u', $value, $link)) {
            $value = $link[1];
        }
        $value = spie_editor_data_normalize($value);
        if ($value === '' || in_array($value, ['proceedings of spie', 'spie proceedings'], true)) {
            continue;
        }
        // A different specific publication title must not be ignored.
        if ($value !== $expected) {
            return false;
        }
        $matched = true;
    }

    // Preserve citations that already put the volume title in |title=.
    return $matched ||
        spie_editor_data_normalize($template->get_without_comments_and_placeholders('title')) === $expected;
}

/** Add only a complete local roster, never partial lists. */
function spie_add_verified_volume_editors(Template $template, string $doi): bool {
    if (!str_starts_with(mb_strtolower($doi), '10.1117/12.')) {
        return false;
    }
    $volume = $template->get_without_comments_and_placeholders('volume');
    $year = $template->get_without_comments_and_placeholders('year');
    if ($year === '' && preg_match('~\b([12][0-9]{3})\b~', $template->get('date'), $match)) {
        $year = $match[1];
    }
    $record = spie_volume_editor_data()[$volume . ':' . $year] ?? null;
    if (!is_array($record) || !is_string($record['title'] ?? null) ||
        !spie_citation_matches_volume_title($template, $record['title'])) {
        return false;
    }
    $fields = spie_verified_volume_editor_fields($record['title'], $volume, $year);
    if ($fields === []) {
        return false;
    }
    if (spie_citation_has_editors($template) ||
        $template->has_any_editor_link_or_mask_parameter()) {
        // Pre-existing links and masks may refer to different people.
        return false;
    }
    // The empty API label is intentional: these editor names came from the
    // local, sourced data file, not Crossref or another live service.
    return $template->add_editor_fields_atomically($fields, '');
}
