<?php

declare(strict_types=1);

/**
 * Check that an opened descriptor and a pathname refer to the same regular
 * file. Both stat results must describe regular files on the same device and
 * inode. A dangling symlink, directory, or replaced pathname is not accepted.
 *
 * This is an identity check only: it does not verify ownership, permissions,
 * link count, or acquire/hold a lock. Keep those policy and lifetime checks
 * at the individual callers. Callers must obtain the pathname stat with
 * lstat(), not stat(), to detect symbolic-link substitution.
 *
 * @param array<string|int, int>|false $opened Result of fstat().
 * @param array<string|int, int>|false $named Result of lstat().
 */
function citation_bot_regular_file_identity_matches(array|false $opened, array|false $named): bool {
    return is_array($opened) &&
        is_array($named) &&
        isset($opened['mode'], $named['mode'], $opened['dev'], $named['dev'], $opened['ino'], $named['ino']) &&
        (($opened['mode'] & 0170000) === 0100000) &&
        (($named['mode'] & 0170000) === 0100000) &&
        $opened['dev'] === $named['dev'] &&
        $opened['ino'] === $named['ino'];
}
