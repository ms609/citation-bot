function should_url2chapter(Template $template, bool $force): bool
    {
        if ($template->has('chapterurl')) {
            return false;
        }
        if ($template->has('chapter-url')) {
            return false;
        }
        if ($template->has('trans-chapter')) {
            return false;
        }
        if ($template->blank('chapter')) {
            return false;
        }
        if (mb_strpos($template->get('chapter'), '[') !== false) {
            return false;
        }
        $url = $template->get('url');
        $url = str_ireplace('%2F', '/', $url);
        if (mb_stripos($url, 'google') && !mb_strpos($template->get('url'), 'pg=')) {
            return false;
        } // Do not move books without page numbers
        if (mb_stripos($url, 'archive.org/details/isbn')) {
            return false;
        }
        if (mb_stripos($url, 'page_id=0')) {
            return false;
        }
        if (mb_stripos($url, 'page=0')) {
            return false;
        }
        if (mb_substr($url, -2) === '_0') {
            return false;
        }
        if (preg_match('~archive\.org/details/[^/]+$~', $url)) {
            return false;
        }
        if (preg_match('~archive\.org/details/.+/page/n(\d+)~', $url, $matches)) {
            if ((int) $matches[1] < 16) {
                return false;
            } // Assume early in the book - title page, etc
        }
        if (mb_stripos($url, 'PA1') && !preg_match('~PA1[0-9]~i', $url)) {
            return false;
        }
        if (mb_stripos($url, 'PA0')) {
            return false;
        }
        if (mb_stripos($url, 'PP1') && !preg_match('~PP1[0-9]~i', $url)) {
            return false;
        }
        if (mb_stripos($url, 'PP0')) {
            return false;
        }
        if ($template->get_without_comments_and_placeholders('chapter') === '') {
            return false;
        }
        if (mb_stripos($url, 'archive.org')) {
            if (mb_strpos($url, 'chapter')) {
                return true;
            }
            if (mb_strpos($url, 'page')) {
                if (preg_match('~page/?[01]?$~i', $url)) {
                    return false;
                }
                return true;
            }
            return false;
        }
        if (mb_stripos($url, 'wp-content')) {
            // Private websites are hard to judge
            if (mb_stripos($url, 'chapter') || mb_stripos($url, 'section')) {
                return true;
            }
            if (mb_stripos($url, 'pages') && !preg_match('~[^\d]1[-–]~u', $url)) {
                return true;
            }
            return false;
        }
        if (mb_strpos($url, 'link.springer.com/chapter/10.')) {
            return true;
        }
        if (preg_match('~10\.1007\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
            return true;
        }
        if (preg_match('~10\.1057\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
            return true;
        }
        if ($force) {
            return true;
        }
        // Only do a few select website unless we just converted to cite book from cite journal
        if (mb_strpos($url, 'archive.org')) {
            return true;
        }
        if (mb_strpos($url, 'google.com')) {
            return true;
        }
        if (mb_strpos($url, 'www.sciencedirect.com/science/article')) {
            return true;
        }
        return false;
    }
