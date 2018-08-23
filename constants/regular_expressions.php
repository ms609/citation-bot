<?php 
const REGEXP_PLAIN_WIKILINK = '~\[\[([^|\[\]]+?)\]\]~';
// Matches: [1], target; [2], display text
const REGEXP_PIPED_WIKILINK = '~\[\[([^|\[\]]+?)\|([^|\[\]]+?)\]\]~';