<?php

declare(strict_types=1);

    function correct_param_mistakes(Template $template): void
    {
     // It will correct any that appear to be mistyped in minor templates
        if (empty($template->param)) {
            return;
        }
        $mistake_corrections = array_values(COMMON_MISTAKES);
        $mistake_keys = array_keys(COMMON_MISTAKES);

        foreach ($template->param as $p) {
            if (strlen($p->param) > 0) {
                $mistake_id = array_search($p->param, $mistake_keys);
                if ($mistake_id) {
                    $new = $mistake_corrections[$mistake_id];
                    if ($template->blank($new)) {
                        $old = $p->param;
                        $p->param = $new;
                        report_modification('replaced ' . echoable($old) . ' with ' . echoable($new) . ' (common mistakes list)');
                    }
                    continue;
                }
            }
        }
     // Catch archive=url=http......
        foreach ($template->param as $p) {
            if (substr_count($p->val, "=") === 1 && !in_array($p->param, PARAMETER_LIST, true)) {
                $param = $p->param;
                $value = $p->val;
                $equals = (int) strpos($value, '=');
                $before = trim(substr($value, 0, $equals));
                $after = trim(substr($value, $equals + 1));
                $possible = $param . '-' . $before;
                if (in_array($possible, PARAMETER_LIST, true)) {
                    $p->param = $possible;
                    $p->val = $after;
                }
            }
        }
    }
