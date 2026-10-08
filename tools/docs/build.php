<?php

declare(strict_types=1);

/*
 * Documentation site generator.
 *
 *   php tools/docs/build.php [options]
 *
 * Reads the content fragments in tools/docs/content/<lang>/<slug>.html
 * (format: tools/docs/CONTENT-SPEC.md), validates them and writes the static
 * site to docs/. Generated files must never be edited by hand.
 *
 * Options:
 *   --content=DIR     fragment directory (default tools/docs/content)
 *   --out=DIR         output directory (default docs)
 *   --spec=FILE       structure file (default tools/docs/spec.php)
 *   --allow-missing   missing slugs from the spec are warnings, not errors
 *   --check           build in memory and verify the output directory is up to
 *                     date (writes nothing; exit 1 when stale)
 *   --quiet           print only warnings and errors
 *   --help            show this text
 *
 * Exit codes: 0 success, 1 validation errors / missing pages / stale output, 2 usage error.
 */

const DOCS_LANGS = ['fa', 'en'];
const DOCS_HEADER_KEYS = ['title', 'description', 'group', 'order'];
const DOCS_RESERVED_SLUGS = ['index', 'all', 'search-index', 'llms'];
const DOCS_ALLOWED_TAGS = [
    'h2', 'h3', 'p', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    'pre', 'code', 'strong', 'em', 'a', 'kbd', 'blockquote', 'div', 'br', 'hr', 'dl', 'dt', 'dd', 'sub', 'sup',
];
const DOCS_VOID_TAGS = ['br', 'hr'];
const DOCS_CALLOUTS = ['note', 'warn', 'known'];
const DOCS_CODE_LANGS = ['php', 'bash', 'json', 'text'];

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function lineOf(string $text, int $offset): int
{
    return substr_count(substr($text, 0, $offset), "\n") + 1;
}

function toLocaleDigits(int|string $n, string $lang): string
{
    $s = (string) $n;
    return $lang === 'fa' ? strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']) : $s;
}

function textOf(string $html): string
{
    $html = preg_replace('#</(p|li|h[1-6]|tr|div|pre|blockquote|dd|dt|caption|td|th)>|<br\s*/?>#i', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function truncateText(string $s, int $max): string
{
    if (mb_strlen($s) <= $max) {
        return $s;
    }
    $cut = mb_substr($s, 0, $max);
    $pos = mb_strrpos($cut, ' ');
    return ($pos !== false && $pos > $max * 0.6 ? mb_substr($cut, 0, $pos) : $cut);
}

function normalizePath(string $p): string
{
    return rtrim(str_replace('\\', '/', $p), '/');
}

function isAbsolutePath(string $p): bool
{
    return $p !== '' && ($p[0] === '/' || $p[0] === '\\' || preg_match('#^[A-Za-z]:[\\\\/]#', $p) === 1);
}

// ---------------------------------------------------------------------------
// Fragment parsing and validation
// ---------------------------------------------------------------------------

/**
 * @return array{meta: array<string,string>, body: string, line: int}|null
 */
function parseFragment(string $raw, string $label, array &$errors): ?array
{
    $raw = str_replace("\r\n", "\n", $raw);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    $raw = ltrim($raw);
    if (!str_starts_with($raw, '<!--')) {
        $errors[] = "$label: the file must start with the <!-- key: value --> header comment";
        return null;
    }
    $end = strpos($raw, '-->');
    if ($end === false) {
        $errors[] = "$label: the header comment is not closed with -->";
        return null;
    }
    $headerText = substr($raw, 4, $end - 4);
    $body = trim(substr($raw, $end + 3));
    $meta = [];
    foreach (explode("\n", $headerText) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/^([a-z]+):\s*(.*)$/u', $line, $m)) {
            $errors[] = "$label: header line is not 'key: value': $line";
            continue;
        }
        $key = $m[1];
        if (!in_array($key, DOCS_HEADER_KEYS, true)) {
            $errors[] = "$label: unknown header key '$key' (allowed: " . implode(', ', DOCS_HEADER_KEYS) . ')';
            continue;
        }
        if (isset($meta[$key])) {
            $errors[] = "$label: duplicate header key '$key'";
            continue;
        }
        if (trim($m[2]) === '') {
            $errors[] = "$label: header key '$key' is empty";
            continue;
        }
        $meta[$key] = trim($m[2]);
    }
    foreach (DOCS_HEADER_KEYS as $key) {
        if (!isset($meta[$key])) {
            $errors[] = "$label: missing required header key '$key'";
        }
    }
    if ($body === '') {
        $errors[] = "$label: the body is empty";
        return null;
    }
    $rest = substr($raw, $end + 3);
    $lead = strlen($rest) - strlen(ltrim($rest));
    return ['meta' => $meta, 'body' => $body, 'line' => substr_count(substr($raw, 0, $end + 3 + $lead), "
") + 1];
}

/**
 * Structural lint of the body markup. Returns collected info for linking and rendering.
 *
 * @return array{ids: array<string,true>, headings: list<array{level:int,id:string,text:string}>, links: list<array{href:string,line:int}>}
 */
function lintBody(string $body, string $label, array &$errors, array &$warnings, int $base = 1): array
{
    $lineOf = static fn (string $text, int $offset): int => lineOf($text, $offset) + $base - 1;
    $info = ['ids' => [], 'headings' => [], 'links' => []];

    // Mask HTML comments (keep newlines so line numbers stay right).
    $masked = preg_replace_callback('/<!--.*?-->/s', static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]) ?? '', $body) ?? $body;

    // Forbidden content.
    $forbidden = ['script', 'style', 'iframe', 'object', 'embed', 'img', 'svg', 'link', 'meta', 'form', 'input', 'button', 'video', 'audio', 'h1', 'h4', 'h5', 'h6'];
    foreach ($forbidden as $tag) {
        if (preg_match('/<' . $tag . '\b/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
            $errors[] = "$label:" . $lineOf($masked, $m[0][1]) . ": <$tag> is not allowed in fragments";
        }
    }
    if (preg_match('/\son[a-z]+\s*=/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
        $errors[] = "$label:" . $lineOf($masked, $m[0][1]) . ': inline event handlers are not allowed';
    }
    if (preg_match('/\sstyle\s*=/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
        $errors[] = "$label:" . $lineOf($masked, $m[0][1]) . ': inline style attributes are not allowed';
    }
    if (preg_match('/&(?!#[0-9]+;|#x[0-9a-fA-F]+;|[A-Za-z][A-Za-z0-9]*;)/', $masked, $m, PREG_OFFSET_CAPTURE)) {
        $errors[] = "$label:" . $lineOf($masked, $m[0][1]) . ': unescaped & (write &amp;)';
    }
    if (preg_match('/\b(TODO|TBD|FIXME|lorem ipsum)\b/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
        $errors[] = "$label:" . $lineOf($masked, $m[0][1]) . ": placeholder text '{$m[0][0]}'";
    }

    // Tag balance and allow-list.
    $stack = [];
    if (preg_match_all('#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>#', $masked, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $m) {
            $closing = $m[1][0] === '/';
            $tag = strtolower($m[2][0]);
            $line = $lineOf($masked, $m[0][1]);
            if (!in_array($tag, DOCS_ALLOWED_TAGS, true)) {
                if (in_array($tag, $forbidden, true)) {
                    continue;
                }
                $errors[] = "$label:$line: <$tag> is not an allowed tag";
                continue;
            }
            if (in_array($tag, DOCS_VOID_TAGS, true)) {
                continue;
            }
            if ($closing) {
                $top = array_pop($stack);
                if ($top === null || $top[0] !== $tag) {
                    $errors[] = "$label:$line: </$tag> does not match" . ($top ? " <{$top[0]}> opened on line {$top[1]}" : ' any open tag');
                    return $info;
                }
            } elseif (!str_ends_with(rtrim($m[3][0]), '/')) {
                $stack[] = [$tag, $line];
            }
        }
    }
    foreach ($stack as [$tag, $line]) {
        $errors[] = "$label:$line: <$tag> is never closed";
    }
    if ($stack) {
        return $info;
    }

    // DOM checks.
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($doc);

    $globalAttrs = ['id', 'lang', 'dir'];
    $tagAttrs = [
        'a' => ['href', 'title', 'hreflang'],
        'td' => ['colspan', 'rowspan', 'scope'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start'],
        'code' => ['class'],
        'div' => ['class'],
    ];
    foreach ($xp->query('//body//*') ?: [] as $node) {
        /** @var DOMElement $node */
        $tag = strtolower($node->tagName);
        $allowed = array_merge($globalAttrs, $tagAttrs[$tag] ?? []);
        foreach ($node->attributes ?? [] as $attr) {
            if (!in_array($attr->name, $allowed, true)) {
                $errors[] = "$label: <$tag> must not have the attribute '{$attr->name}'";
            }
        }
        if ($node->hasAttribute('id')) {
            $id = $node->getAttribute('id');
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
                $errors[] = "$label: id '$id' must match [a-z][a-z0-9-]* (same ASCII id in both languages)";
            } elseif (isset($info['ids'][$id])) {
                $errors[] = "$label: duplicate id '$id'";
            }
            $info['ids'][$id] = true;
        }
        if ($tag === 'h2' || $tag === 'h3') {
            if (!$node->hasAttribute('id')) {
                $errors[] = "$label: <$tag>" . textOf($node->textContent) . "</$tag> needs an id";
            } else {
                $info['headings'][] = ['level' => (int) $tag[1], 'id' => $node->getAttribute('id'), 'text' => textOf($node->textContent)];
            }
            if ($node->getElementsByTagName('*')->length > 0 && $node->getElementsByTagName('code')->length !== $node->getElementsByTagName('*')->length) {
                $errors[] = "$label: <$tag id=\"" . $node->getAttribute('id') . '"> may only contain text and <code>';
            }
        }
        if ($tag === 'div') {
            $class = $node->getAttribute('class');
            if (!in_array($class, DOCS_CALLOUTS, true)) {
                $errors[] = "$label: <div> must have class=\"note\", \"warn\" or \"known\" (got '$class')";
            } else {
                $first = null;
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMText && trim($child->textContent) === '') {
                        continue;
                    }
                    $first = $child;
                    break;
                }
                if (!($first instanceof DOMElement) || strtolower($first->tagName) !== 'strong') {
                    $errors[] = "$label: callout div.$class must start with <strong>Label.</strong>";
                }
            }
        }
        if ($tag === 'code') {
            $inPre = $node->parentNode instanceof DOMElement && strtolower($node->parentNode->tagName) === 'pre';
            $class = $node->getAttribute('class');
            if ($inPre && !preg_match('/^language-(' . implode('|', DOCS_CODE_LANGS) . ')$/', $class)) {
                $errors[] = "$label: <pre><code> needs class=\"language-" . implode('|', DOCS_CODE_LANGS) . '"';
            }
            if (!$inPre && $class !== '') {
                $errors[] = "$label: inline <code> must not have a class";
            }
        }
        if ($tag === 'pre') {
            $kids = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText && trim($child->textContent) === '') {
                    continue;
                }
                $kids[] = $child;
            }
            if (count($kids) !== 1 || !($kids[0] instanceof DOMElement) || strtolower($kids[0]->tagName) !== 'code') {
                $errors[] = "$label: <pre> must contain exactly one <code> element";
            }
        }
        if ($tag === 'a') {
            if (!$node->hasAttribute('href')) {
                $errors[] = "$label: <a> without href";
            } else {
                $info['links'][] = ['href' => $node->getAttribute('href'), 'line' => 0];
            }
        }
    }

    if (!$info['headings']) {
        $warnings[] = "$label: no h2/h3 headings (no table of contents will be shown)";
    }
    return $info;
}

// ---------------------------------------------------------------------------
// PHP syntax tint (build time, no external library)
// ---------------------------------------------------------------------------

function highlightPhp(string $escaped): string
{
    $code = html_entity_decode($escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $hasTag = str_starts_with(ltrim($code), '<?php');
    $tokens = token_get_all($hasTag ? $code : '<?php ' . $code);
    $keywords = [
        'T_USE', 'T_FUNCTION', 'T_FN', 'T_NEW', 'T_ECHO', 'T_PRINT', 'T_RETURN', 'T_CLASS', 'T_FINAL', 'T_ABSTRACT', 'T_STATIC',
        'T_PUBLIC', 'T_PRIVATE', 'T_PROTECTED', 'T_READONLY', 'T_IF', 'T_ELSE', 'T_ELSEIF', 'T_FOREACH', 'T_FOR', 'T_WHILE', 'T_AS',
        'T_CONST', 'T_NAMESPACE', 'T_TRY', 'T_CATCH', 'T_FINALLY', 'T_THROW', 'T_MATCH', 'T_ARRAY', 'T_LIST', 'T_EXTENDS',
        'T_IMPLEMENTS', 'T_INTERFACE', 'T_ENUM', 'T_INSTANCEOF', 'T_DECLARE', 'T_OPEN_TAG', 'T_REQUIRE', 'T_REQUIRE_ONCE',
        'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_SWITCH', 'T_CASE', 'T_DEFAULT', 'T_BREAK', 'T_CONTINUE', 'T_YIELD', 'T_ISSET', 'T_EMPTY',
        'T_UNSET', 'T_TRAIT', 'T_GLOBAL', 'T_DO', 'T_CALLABLE', 'T_EXIT',
    ];
    $segments = [];
    $push = static function (?string $class, string $text) use (&$segments): void {
        $n = count($segments);
        if ($n > 0 && $segments[$n - 1][0] === $class) {
            $segments[$n - 1][1] .= $text;
        } else {
            $segments[] = [$class, $text];
        }
    };
    $inString = false;
    $first = true;
    foreach ($tokens as $tok) {
        if (is_array($tok)) {
            [$id, $text] = $tok;
            $name = token_name($id);
            if ($first && !$hasTag && $name === 'T_OPEN_TAG') {
                $first = false;
                continue;
            }
            $first = false;
            $class = null;
            if ($inString || in_array($name, ['T_CONSTANT_ENCAPSED_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_START_HEREDOC', 'T_END_HEREDOC'], true)) {
                $class = 's';
                if ($name === 'T_START_HEREDOC') {
                    $inString = true;
                } elseif ($name === 'T_END_HEREDOC') {
                    $inString = false;
                }
            } elseif ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                $class = 'c';
            } elseif ($name === 'T_VARIABLE') {
                $class = 'v';
            } elseif ($name === 'T_LNUMBER' || $name === 'T_DNUMBER') {
                $class = 'n';
            } elseif (in_array($name, $keywords, true)) {
                $class = 'k';
            } elseif ($name === 'T_STRING' && in_array(strtolower($text), ['true', 'false', 'null'], true)) {
                $class = 'k';
            }
            $push($class, $text);
        } else {
            $first = false;
            if ($tok === '"' || $tok === '`') {
                $inString = !$inString;
                $push('s', $tok);
            } else {
                $push($inString ? 's' : null, $tok);
            }
        }
    }
    $out = '';
    foreach ($segments as [$class, $text]) {
        $h = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out .= $class !== null ? "<span class=\"tok-$class\">$h</span>" : $h;
    }
    return $out;
}
// ---------------------------------------------------------------------------
// Body rendering
// ---------------------------------------------------------------------------

function renderBody(string $body, array $S): string
{
    // Headings: add permalink anchors.
    $body = preg_replace_callback('#<(h[23]) id="([^"]+)">(.*?)</\1>#s', static function (array $m) use ($S): string {
        return "<{$m[1]} id=\"{$m[2]}\">{$m[3]}<a class=\"anchor\" href=\"#{$m[2]}\" aria-label=\"" . e($S['page']['anchor']) . '">#</a></' . $m[1] . '>';
    }, $body) ?? $body;

    // Callouts.
    $body = preg_replace('#<div class="(note|warn|known)">#', '<div class="callout $1" role="note">', $body) ?? $body;

    // Code blocks.
    $body = preg_replace_callback('#<pre>\s*<code class="language-([a-z]+)">(.*?)</code>\s*</pre>#s', static function (array $m): string {
        $lang = $m[1];
        $inner = rtrim($m[2], "\n");
        $inner = $lang === 'php' ? highlightPhp($inner) : $inner;
        return '<pre tabindex="0"><code class="language-' . $lang . '">' . $inner . '</code></pre>';
    }, $body) ?? $body;

    // Tables.
    $body = str_replace('<th>', '<th scope="col">', $body);
    $body = preg_replace('#<table>#', '<div class="table-wrap" tabindex="0" role="region" aria-label="' . e($S['page']['table']) . '"><table>', $body) ?? $body;
    $body = str_replace('</table>', '</table></div>', $body);

    // External links.
    $body = preg_replace('#<a href="(https?://[^"]*)"#', '<a href="$1" rel="noopener noreferrer"', $body) ?? $body;

    return $body;
}

// ---------------------------------------------------------------------------
// Counting facts from the code base (home page statistics)
// ---------------------------------------------------------------------------

/** @return array<string,int> */
function countFacts(string $root, array &$errors): array
{
    $count = static function (string $glob, string $regex) use ($root): int {
        $n = 0;
        foreach (glob($root . '/' . $glob) ?: [] as $file) {
            $n += (int) preg_match_all($regex, (string) file_get_contents($file));
        }
        return $n;
    };
    $facts = [
        'calendars' => $count('src/Calendar/*.php', '/final class \w+ implements [^{]*\bCalendarDate\b/'),
        'validators' => $count('src/Validation/*.php', '/final class \w+ implements [^{]*\bValidator\b/'),
        'helpers' => $count('src/helpers.php', '/^function\s+\w+/m'),
        'prayer_methods' => $count('src/Prayer/PrayerTimes.php', '/public const METHOD_\w+/'),
        'locales' => count(glob($root . '/resources/lang/*', GLOB_ONLYDIR) ?: []),
    ];
    foreach ($facts as $k => $v) {
        if ($v < 1) {
            $errors[] = "cannot count '$k' from the source tree ($root); stats would be wrong";
        }
    }
    return $facts;
}

// ---------------------------------------------------------------------------
// Page templates
// ---------------------------------------------------------------------------

/**
 * @param array<string,mixed> $P page context
 */
function pageHead(array $P, string $title, string $description, string $altHref, string $altLang): string
{
    $S = $P['S'];
    $lang = $P['lang'];
    $dir = $P['langs'][$lang]['dir'];
    $i18n = e((string) json_encode($S['js'] + ['search_label' => $S['search_label'], 'search_placeholder' => $S['search_placeholder']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $fullTitle = $title . ' | ' . $P['site']['name'] . ' ' . $S['doc_title'];
    return '<!doctype html>' . "\n"
        . '<html lang="' . $lang . '" dir="' . $dir . '" data-i18n="' . $i18n . '" data-search-index="search-index.js">' . "\n"
        . "<head>\n<meta charset=\"utf-8\">\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . '<title>' . e($fullTitle) . "</title>\n"
        . '<meta name="description" content="' . e($description) . "\">\n"
        . "<meta name=\"color-scheme\" content=\"light dark\">\n"
        . '<meta property="og:title" content="' . e($fullTitle) . "\">\n"
        . '<meta property="og:description" content="' . e($description) . "\">\n"
        . "<meta property=\"og:type\" content=\"website\">\n"
        . '<meta property="og:locale" content="' . $P['langs'][$lang]['locale'] . "\">\n"
        . "<link rel=\"icon\" type=\"image/svg+xml\" href=\"../assets/favicon.svg\">\n"
        . '<link rel="alternate" hreflang="' . $altLang . '" href="' . e($altHref) . "\">\n"
        . "<link rel=\"stylesheet\" href=\"../styles.css\">\n"
        . "<script>try{var t=localStorage.getItem(\"rtly-docs-theme\");if(t===\"light\"||t===\"dark\")document.documentElement.setAttribute(\"data-theme\",t)}catch(e){}</script>\n"
        . "<script defer src=\"../app.js\"></script>\n"
        . "</head>\n<body>\n";
}

function pageHeader(array $P, string $current, string $altHref): string
{
    $S = $P['S'];
    $nav = $S['nav'];
    $links = [['index.html', $nav['overview'], 'overview']];
    if (isset($P['pages']['quick-start'])) {
        $links[] = ['quick-start.html', $nav['quick_start'], 'quick-start'];
    }
    $links[] = ['all.html', $nav['all'], 'all'];
    $h = '<a class="skip" href="#main">' . e($S['skip']) . '</a>';
    $h .= '<header class="site-header"><div class="shell header-inner">';
    $h .= '<a class="brand" href="index.html"><img src="../assets/logo.svg" width="34" height="34" alt=""><span><span class="name">' . e($P['site']['name']) . '</span><small>' . e($S['doc_title']) . '</small></span></a>';
    $h .= '<nav class="top-nav" aria-label="' . e($nav['primary']) . '">';
    foreach ($links as [$href, $label, $key]) {
        $h .= '<a href="' . $href . '"' . ($current === $key ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    $h .= '<a href="' . e($P['site']['repo']) . '" rel="noopener noreferrer">' . e($nav['github']) . ' &#8599;</a></nav>';
    $other = $P['lang'] === 'fa' ? 'en' : 'fa';
    $h .= '<div class="header-tools"><span data-search-host></span><button type="button" class="icon-button" data-theme-toggle hidden>Theme</button>';
    $h .= '<a class="icon-button" href="' . e($altHref) . '" hreflang="' . $other . '" lang="' . $other . '" aria-label="' . e($S['lang_switch_label']) . '">' . e($S['lang_switch']) . '</a></div>';
    $h .= "</div></header>\n";
    return $h;
}

function pageFooter(array $P): string
{
    $S = $P['S'];
    return '<footer class="footer"><div class="shell footer-inner"><p>' . e(sprintf($S['footer']['text'], $P['site']['license'])) . '</p><div>'
        . '<a href="' . e($P['site']['repo']) . '" rel="noopener noreferrer">' . e($S['footer']['repo']) . ' &#8599;</a>'
        . '<a href="' . e($P['site']['issues']) . '" rel="noopener noreferrer">' . e($S['footer']['issues']) . ' &#8599;</a>'
        . "</div></div></footer>\n</body>\n</html>\n";
}

function sidebarHtml(array $P, string $currentFile): string
{
    $S = $P['S'];
    $h = '<aside class="sidebar"><details open><summary>' . e($S['page']['menu']) . '</summary><nav aria-label="' . e($S['page']['menu_label']) . '">';
    $h .= '<a href="all.html"' . ($currentFile === 'all.html' ? ' aria-current="page"' : '') . '>' . e($S['nav']['all']) . '</a>';
    foreach ($P['groupOrder'] as $gk) {
        $in = array_filter($P['pages'], static fn (array $pg): bool => $pg['group'] === $gk);
        if (!$in) {
            continue;
        }
        $h .= '<p>' . e($S['groups'][$gk]['label']) . '</p>';
        foreach ($in as $pg) {
            $h .= '<a href="' . $pg['slug'] . '.html"' . ($currentFile === $pg['slug'] . '.html' ? ' aria-current="page"' : '') . '>' . e($pg['title']) . '</a>';
        }
    }
    return $h . "</nav></details></aside>\n";
}

function tocHtml(array $headings): string
{
    $h = '<ol>';
    $open = false;
    $n = count($headings);
    foreach ($headings as $i => $hd) {
        if ($hd['level'] === 2) {
            if ($open) {
                $h .= '</ol></li>';
                $open = false;
            } elseif ($i > 0) {
                $h .= '</li>';
            }
            $h .= '<li><a href="#' . $hd['id'] . '">' . e($hd['text']) . '</a>';
        } else {
            if (!$open) {
                $h .= '<ol>';
                $open = true;
            }
            $h .= '<li><a href="#' . $hd['id'] . '">' . e($hd['text']) . '</a></li>';
        }
        if ($i === $n - 1) {
            $h .= $open ? '</ol></li>' : '</li>';
            $open = false;
        }
    }
    return $h . '</ol>';
}

function cardHtml(array $pg, array $S, string $tag, bool $filterItem): string
{
    return '<article class="card"' . ($filterItem ? ' data-filter-item' : '') . '><' . $tag . '><a href="' . $pg['slug'] . '.html">' . e($pg['title']) . '</a></' . $tag . '><p>'
        . e($pg['description']) . '</p><span class="card-link" aria-hidden="true">' . e($S['home']['open']) . ' &rarr;</span></article>';
}

function altHrefFor(array $P, ?string $slug, string $file): string
{
    $other = $P['lang'] === 'fa' ? 'en' : 'fa';
    if ($slug !== null && isset($P['allPages'][$other][$slug])) {
        return '../' . $other . '/' . $slug . '.html';
    }
    if ($file === 'all.html') {
        return '../' . $other . '/all.html';
    }
    return '../' . $other . '/index.html';
}

function renderGuide(array $P, array $pg): string
{
    $S = $P['S'];
    $file = $pg['slug'] . '.html';
    $alt = altHrefFor($P, $pg['slug'], $file);
    $other = $P['lang'] === 'fa' ? 'en' : 'fa';
    $out = pageHead($P, $pg['title'], $pg['description'], $alt, $other);
    $out .= pageHeader($P, $pg['slug'], $alt);
    $out .= "<main id=\"main\">\n";
    $out .= '<div class="shell page-intro"><nav class="breadcrumbs" aria-label="' . e($S['page']['breadcrumb']) . '"><ol>'
        . '<li><a href="index.html">' . e($S['page']['home']) . '</a></li>'
        . '<li><a href="all.html#group-' . $pg['group'] . '">' . e($S['groups'][$pg['group']]['label']) . '</a></li>'
        . '<li aria-current="page">' . e($pg['title']) . '</li></ol></nav>'
        . '<h1>' . e($pg['title']) . '</h1><p class="lead">' . e($pg['description']) . "</p></div>\n";
    $hasToc = count($pg['headings']) >= 2;
    $out .= '<div class="shell doc-layout' . ($hasToc ? ' has-toc' : '') . "\">\n";
    $out .= sidebarHtml($P, $file);
    $out .= "<article class=\"prose\">\n";
    if ($hasToc) {
        $out .= '<details class="toc-inline"><summary>' . e($S['page']['on_this_page']) . '</summary>' . tocHtml($pg['headings']) . "</details>\n";
    }
    $out .= renderBody($pg['body'], $S) . "\n";

    // Previous / next in reading order.
    $slugs = array_keys($P['pages']);
    $idx = array_search($pg['slug'], $slugs, true);
    $prev = $idx > 0 ? $P['pages'][$slugs[$idx - 1]] : null;
    $next = $idx < count($slugs) - 1 ? $P['pages'][$slugs[$idx + 1]] : null;
    $out .= '<nav class="reading-footer" aria-label="' . e($S['page']['reading_nav']) . '">';
    $out .= $prev ? '<a class="prev" rel="prev" href="' . $prev['slug'] . '.html"><small>' . e($S['page']['previous']) . '</small>' . e($prev['title']) . '</a>' : '<span></span>';
    $out .= $next ? '<a class="next" rel="next" href="' . $next['slug'] . '.html"><small>' . e($S['page']['next']) . '</small>' . e($next['title']) . '</a>' : '<span></span>';
    $out .= "</nav>\n</article>\n";
    if ($hasToc) {
        $out .= '<nav class="toc" aria-label="' . e($S['page']['on_this_page']) . '"><p>' . e($S['page']['on_this_page']) . '</p>' . tocHtml($pg['headings']) . "</nav>\n";
    }
    $out .= "</div>\n</main>\n";
    return $out . pageFooter($P);
}

function renderAll(array $P): string
{
    $S = $P['S'];
    $alt = altHrefFor($P, null, 'all.html');
    $other = $P['lang'] === 'fa' ? 'en' : 'fa';
    $out = pageHead($P, $S['all']['title'], $S['all']['description'], $alt, $other);
    $out .= pageHeader($P, 'all', $alt);
    $out .= "<main id=\"main\">\n";
    $out .= '<div class="shell page-intro"><nav class="breadcrumbs" aria-label="' . e($S['page']['breadcrumb']) . '"><ol>'
        . '<li><a href="index.html">' . e($S['page']['home']) . '</a></li><li aria-current="page">' . e($S['all']['title']) . '</li></ol></nav>'
        . '<h1>' . e($S['all']['title']) . '</h1><p class="lead">' . e($S['all']['description']) . "</p></div>\n";
    $out .= '<div class="shell section">';
    $out .= '<div class="filter" hidden><label for="guide-filter">' . e($S['all']['filter_label']) . '</label>'
        . '<input id="guide-filter" type="search" autocomplete="off" placeholder="' . e($S['all']['filter_placeholder']) . '" data-filter="guide-list" data-status="filter-status" data-empty="filter-empty" data-status-text="' . e($S['all']['status']) . '"></div>';
    $out .= '<p class="filter-status" id="filter-status" role="status" aria-live="polite"></p>';
    $out .= '<p class="empty" id="filter-empty" hidden>' . e($S['all']['empty']) . '</p>';
    $out .= '<div id="guide-list">';
    foreach ($P['groupOrder'] as $gk) {
        $in = array_filter($P['pages'], static fn (array $pg): bool => $pg['group'] === $gk);
        if (!$in) {
            continue;
        }
        $out .= '<section class="group-block" data-filter-block aria-labelledby="group-' . $gk . '"><h2 id="group-' . $gk . '">' . e($S['groups'][$gk]['label']) . '</h2><p>' . e($S['groups'][$gk]['blurb']) . '</p><div class="grid">';
        foreach ($in as $pg) {
            $out .= cardHtml($pg, $S, 'h3', true);
        }
        $out .= '</div></section>';
    }
    $out .= "</div></div>\n</main>\n";
    return $out . pageFooter($P);
}

function renderHome(array $P, array $facts): string
{
    $S = $P['S'];
    $H = $S['home'];
    $lang = $P['lang'];
    $alt = altHrefFor($P, null, 'index.html');
    $other = $lang === 'fa' ? 'en' : 'fa';
    $out = pageHead($P, $H['meta_title'], $H['lead'], $alt, $other);
    $out = str_replace('<title>' . e($H['meta_title'] . ' | ' . $P['site']['name'] . ' ' . $S['doc_title']) . '</title>', '<title>' . e($H['meta_title']) . '</title>', $out);
    $out .= pageHeader($P, 'overview', $alt);
    $out .= "<main id=\"main\">\n";

    $start = isset($P['pages']['quick-start']) ? 'quick-start.html' : (($first = array_key_first($P['pages'])) !== null ? $first . '.html' : 'all.html');
    $out .= '<section class="hero"><div class="shell hero-grid"><div>'
        . '<p class="eyebrow">' . e($H['eyebrow']) . '</p><h1>' . e($H['title']) . '</h1><p class="subtitle">' . e($H['subtitle']) . '</p>'
        . '<p class="lead">' . e($H['lead']) . '</p>'
        . '<div class="actions"><a class="button primary" href="' . $start . '">' . e($H['cta_start']) . '</a>'
        . '<a class="button" href="all.html">' . e($H['cta_all']) . '</a>'
        . '<a class="button" href="' . e($P['site']['repo']) . '" rel="noopener noreferrer">' . e($H['cta_github']) . ' &#8599;</a></div>'
        . '<p class="hero-note">' . e(sprintf($H['hero_note'], $P['site']['php'], $P['site']['license'])) . '</p></div>'
        . '<div class="hero-card"><h2>' . e($H['install_title']) . '</h2><p>' . e($H['install_lead']) . '</p>'
        . '<pre tabindex="0"><code class="language-bash">' . e($P['site']['install']) . '</code></pre>'
        . '<h2>' . e($H['sample_title']) . '</h2>'
        . '<pre tabindex="0"><code class="language-php">' . highlightPhp(e($P['homeSample'])) . '</code></pre></div>'
        . "</div></section>\n";

    // What you can do.
    $out .= '<section class="section"><div class="shell"><div class="section-heading"><h2>' . e($H['can_title']) . '</h2><p>' . e($H['can_lead']) . '</p></div><ul class="can-list">';
    foreach ($H['can_items'] as [$text, $slug]) {
        $out .= '<li>' . (isset($P['pages'][$slug]) ? '<a href="' . $slug . '.html">' . e($text) . '</a>' : e($text)) . '</li>';
    }
    $out .= "</ul></div></section>\n";

    // Stats.
    $statsValues = $facts + ['guides' => count($P['pages'])];
    $out .= '<section class="section"><div class="shell"><div class="section-heading"><h2>' . e($H['stats_title']) . '</h2><p>' . e($H['stats_note']) . '</p></div><ul class="stats">';
    foreach (['calendars', 'validators', 'helpers', 'prayer_methods', 'locales', 'guides'] as $key) {
        $out .= '<li><strong>' . toLocaleDigits($statsValues[$key], $lang) . '</strong><span>' . e($H['stats'][$key]) . '</span></li>';
    }
    $out .= "</ul></div></section>\n";

    // Honest limits.
    if (isset($P['pages']['accuracy-and-data'])) {
        $out .= '<section class="section"><div class="shell"><div class="notice"><h2>' . e($H['honest_title']) . '</h2><p>' . e($H['honest_text']) . '</p>'
            . '<p><a href="accuracy-and-data.html">' . e($H['honest_cta']) . ' &rarr;</a></p></div></div></section>' . "\n";
    }

    // Groups.
    $out .= '<section class="section"><div class="shell"><div class="section-heading"><h2>' . e($H['groups_title']) . '</h2><p>' . e($H['groups_lead']) . '</p></div>';
    foreach ($P['groupOrder'] as $gk) {
        $in = array_filter($P['pages'], static fn (array $pg): bool => $pg['group'] === $gk);
        if (!$in) {
            continue;
        }
        $out .= '<div class="group-block"><h3>' . e($S['groups'][$gk]['label']) . '</h3><p>' . e($S['groups'][$gk]['blurb']) . '</p><div class="grid">';
        foreach ($in as $pg) {
            $out .= cardHtml($pg, $S, 'h4', false);
        }
        $out .= '</div></div>';
    }
    $out .= "</div></section>\n</main>\n";
    return $out . pageFooter($P);
}

function renderRoot(array $strings, array $langsPresent): string
{
    $R = $strings['root'];
    $site = $strings['site'];
    $h = "<!doctype html>\n<html lang=\"en\" dir=\"ltr\" data-i18n=\"{}\">\n<head>\n<meta charset=\"utf-8\">\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . '<title>' . e($R['title']) . "</title>\n"
        . '<meta name="description" content="' . e($R['description']) . "\">\n"
        . "<meta name=\"color-scheme\" content=\"light dark\">\n"
        . '<meta property="og:title" content="' . e($R['title']) . "\">\n"
        . '<meta property="og:description" content="' . e($R['description']) . "\">\n"
        . "<link rel=\"icon\" type=\"image/svg+xml\" href=\"assets/favicon.svg\">\n";
    foreach ($langsPresent as $l) {
        $h .= '<link rel="alternate" hreflang="' . $l . '" href="' . $l . "/index.html\">\n";
    }
    $h .= "<link rel=\"stylesheet\" href=\"styles.css\">\n"
        . "<script>try{var t=localStorage.getItem(\"rtly-docs-theme\");if(t===\"light\"||t===\"dark\")document.documentElement.setAttribute(\"data-theme\",t)}catch(e){}</script>\n"
        . "<script defer src=\"app.js\"></script>\n</head>\n<body>\n"
        . "<main id=\"main\" class=\"chooser\"><div class=\"shell\">\n"
        . '<div class="brand-row"><img src="assets/logo.svg" width="52" height="52" alt=""><span class="eyebrow">' . e($site['package']) . "</span></div>\n"
        . '<h1>' . e($site['name']) . '</h1>'
        . '<p class="lead" lang="en">' . e($R['heading']) . '</p><p class="lead" lang="fa" dir="rtl">' . e($R['heading_fa']) . "</p>\n"
        . '<div class="lang-cards">';
    foreach ($langsPresent as $l) {
        $c = $R['cards'][$l];
        $dir = $strings['langs'][$l]['dir'];
        $h .= '<article class="card" data-lang-card="' . $l . '" lang="' . $l . '" dir="' . $dir . '"><h2><a href="' . $l . '/index.html" hreflang="' . $l . '">' . e($c['title']) . '</a></h2><p>' . e($c['text']) . '</p>'
            . '<span class="tag" data-suggest hidden>' . e($R['suggest'][$l]) . '</span></article>';
    }
    $h .= "</div>\n"
        . '<p class="hero-note" style="margin-top:22px"><a href="' . e($site['repo']) . '" rel="noopener noreferrer">' . e($R['repo']) . ' &#8599;</a></p>'
        . "\n</div></main>\n</body>\n</html>\n";
    return $h;
}

// ---------------------------------------------------------------------------
// Search index and llms.txt
// ---------------------------------------------------------------------------

function searchIndex(array $P): string
{
    $entries = [];
    foreach ($P['pages'] as $pg) {
        $entries[] = [
            't' => $pg['title'],
            'u' => $pg['slug'] . '.html',
            'd' => $pg['description'],
            'g' => $P['S']['groups'][$pg['group']]['label'],
            'h' => array_map(static fn (array $h): array => [$h['text'], $h['id']], $pg['headings']),
            'x' => truncateText(textOf($pg['body']), 6000),
        ];
    }
    return "/* Generated by tools/docs/build.php. Do not edit. */\nwindow.RTLY_DOCS = "
        . json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ";\n";
}

function llmsTxt(array $P, string $prefix): string
{
    $S = $P['S'];
    $L = $S['llms'];
    $t = '# ' . $P['site']['name'] . "\n\n> " . $L['intro'] . "\n\n" . sprintf($L['meta'], $P['site']['package'], $P['site']['install'], $P['site']['php'], $P['site']['repo']) . "\n";
    foreach ($P['groupOrder'] as $gk) {
        $in = array_filter($P['pages'], static fn (array $pg): bool => $pg['group'] === $gk);
        if (!$in) {
            continue;
        }
        $t .= "\n## " . $S['groups'][$gk]['label'] . "\n\n";
        foreach ($in as $pg) {
            $t .= '- [' . $pg['title'] . '](' . $prefix . $pg['slug'] . '.html): ' . $pg['description'] . "\n";
        }
    }
    $t .= "\n## " . $L['notes_title'] . "\n\n";
    foreach ($L['notes'] as $note) {
        $t .= '- ' . $note . "\n";
    }
    if (isset($P['pages']['accuracy-and-data'])) {
        $t .= '- ' . sprintf($L['accuracy_note'], $prefix . 'accuracy-and-data.html') . "\n";
    }
    return $t;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

function main(array $argv): int
{
    $root = normalizePath(dirname(__DIR__, 2));
    $opts = ['content' => __DIR__ . '/content', 'out' => $root . '/docs', 'spec' => __DIR__ . '/spec.php'];
    $flags = ['allow-missing' => false, 'check' => false, 'quiet' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $self = (string) file_get_contents(__FILE__);
            preg_match('#/\*\n(.*?)\*/#s', $self, $m);
            echo preg_replace('/^ \* ?/m', '', $m[1] ?? ''), "\n";
            return 0;
        }
        if (preg_match('/^--(content|out|spec)=(.+)$/', $arg, $m)) {
            $opts[$m[1]] = isAbsolutePath($m[2]) ? $m[2] : (string) getcwd() . '/' . $m[2];
            continue;
        }
        if (preg_match('/^--(allow-missing|check|quiet)$/', $arg, $m)) {
            $flags[$m[1]] = true;
            continue;
        }
        fwrite(STDERR, "Unknown option: $arg (try --help)\n");
        return 2;
    }
    $opts = array_map(normalizePath(...), $opts);
    $say = static function (string $msg) use ($flags): void {
        if (!$flags['quiet']) {
            echo $msg, "\n";
        }
    };

    $strings = require __DIR__ . '/strings.php';
    $spec = require $opts['spec'];
    $errors = [];
    $warnings = [];

    $groupOrder = array_keys($spec['groups']);
    usort($groupOrder, static fn (string $a, string $b): int => $spec['groups'][$a] <=> $spec['groups'][$b]);
    foreach ($groupOrder as $gk) {
        foreach (DOCS_LANGS as $l) {
            if (!isset($strings[$l]['groups'][$gk])) {
                $errors[] = "strings.php: group '$gk' has no label for '$l'";
            }
        }
    }
    foreach ($spec['pages'] as $slug => [$g, $o]) {
        if (!isset($spec['groups'][$g])) {
            $errors[] = "spec: page '$slug' uses unknown group '$g'";
        }
        if (in_array($slug, DOCS_RESERVED_SLUGS, true) || !preg_match('/^[a-z][a-z0-9-]*$/', $slug)) {
            $errors[] = "spec: invalid slug '$slug'";
        }
    }

    // 1. Read and lint fragments.
    $pagesByLang = [];
    foreach (glob($opts['content'] . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (!in_array(basename($dir), DOCS_LANGS, true)) {
            $warnings[] = 'content/' . basename($dir) . ': ignored (languages are ' . implode(', ', DOCS_LANGS) . ')';
        }
    }
    foreach (DOCS_LANGS as $lang) {
        $pagesByLang[$lang] = [];
        foreach (glob($opts['content'] . "/$lang/*") ?: [] as $file) {
            $name = basename($file);
            $label = "$lang/$name";
            if (!preg_match('/^([a-z][a-z0-9-]*)\.html$/', $name, $m)) {
                $errors[] = "$label: unexpected file (fragments are <slug>.html)";
                continue;
            }
            $slug = $m[1];
            if (!isset($spec['pages'][$slug])) {
                $errors[] = "$label: slug '$slug' is not in the spec (tools/docs/spec.php)";
                continue;
            }
            $parsed = parseFragment((string) file_get_contents($file), $label, $errors);
            if ($parsed === null) {
                continue;
            }
            $meta = $parsed['meta'];
            [$g, $o] = $spec['pages'][$slug];
            if (isset($meta['group']) && $meta['group'] !== $g) {
                $errors[] = "$label: group is '{$meta['group']}' but the spec says '$g'";
            }
            if (isset($meta['order']) && (!ctype_digit($meta['order']) || (int) $meta['order'] !== $o)) {
                $errors[] = "$label: order is '{$meta['order']}' but the spec says $o";
            }
            if (isset($meta['title']) && mb_strlen($meta['title']) > 90) {
                $errors[] = "$label: title is longer than 90 characters";
            }
            if (isset($meta['description'])) {
                $len = mb_strlen($meta['description']);
                if ($len < 30) {
                    $warnings[] = "$label: description is very short ($len characters)";
                } elseif ($len > 320) {
                    $warnings[] = "$label: description is long ($len characters); it is the lead, the card text and the meta description";
                }
                if (preg_match('/[<>]/', $meta['description'])) {
                    $errors[] = "$label: description must be plain text (no markup)";
                }
            }
            if (isset($meta['title']) && preg_match('/[<>]/', $meta['title'])) {
                $errors[] = "$label: title must be plain text (no markup)";
            }
            $info = lintBody($parsed['body'], $label, $errors, $warnings, $parsed['line']);
            $pagesByLang[$lang][$slug] = [
                'slug' => $slug,
                'title' => $meta['title'] ?? $slug,
                'description' => $meta['description'] ?? '',
                'group' => $g,
                'order' => $o,
                'body' => $parsed['body'],
                'headings' => $info['headings'],
                'ids' => $info['ids'],
                'links' => $info['links'],
            ];
        }
        // Sort by (group order, order, slug).
        uasort($pagesByLang[$lang], static function (array $a, array $b) use ($spec): int {
            return [$spec['groups'][$a['group']], $a['order'], $a['slug']] <=> [$spec['groups'][$b['group']], $b['order'], $b['slug']];
        });
    }

    // 2. Links (needs all fragments of the language).
    foreach (DOCS_LANGS as $lang) {
        foreach ($pagesByLang[$lang] as $slug => $pg) {
            $label = "$lang/$slug.html";
            foreach ($pg['links'] as $link) {
                $href = $link['href'];
                if (preg_match('#^(https?://[^\s]+|mailto:[^\s]+)$#i', $href)) {
                    continue;
                }
                if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
                    $errors[] = "$label: link '$href' uses a disallowed scheme or is empty";
                    continue;
                }
                if ($href[0] === '#') {
                    if (!isset($pg['ids'][substr($href, 1)])) {
                        $errors[] = "$label: link '$href' points to an id that does not exist on this page";
                    }
                    continue;
                }
                if (!preg_match('/^([a-z][a-z0-9-]*)\.html(?:#([a-z][a-z0-9-]*))?$/', $href, $m)) {
                    $errors[] = "$label: link '$href' must be slug.html or slug.html#id (same folder) or an absolute http(s) URL";
                    continue;
                }
                $target = $m[1];
                if (in_array($target, ['index', 'all'], true)) {
                    continue;
                }
                if (!isset($spec['pages'][$target])) {
                    $errors[] = "$label: link '$href' points to unknown slug '$target'";
                    continue;
                }
                if (!isset($pagesByLang[$lang][$target])) {
                    $warnings[] = "$label: link '$href' points to a page that is not written yet ($lang/$target.html)";
                    continue;
                }
                if (isset($m[2]) && !isset($pagesByLang[$lang][$target]['ids'][$m[2]])) {
                    $errors[] = "$label: link '$href' points to id '{$m[2]}' that does not exist in $lang/$target.html";
                }
            }
        }
    }

    // 3. Missing slugs, language parity, id parity.
    $missing = [];
    foreach (DOCS_LANGS as $lang) {
        foreach ($spec['pages'] as $slug => $_) {
            if (!isset($pagesByLang[$lang][$slug])) {
                $missing[] = "$lang/$slug.html";
            }
        }
    }
    foreach ($pagesByLang['fa'] as $slug => $pg) {
        if (isset($pagesByLang['en'][$slug])) {
            $a = array_keys($pg['ids']);
            $b = array_keys($pagesByLang['en'][$slug]['ids']);
            $diff = array_merge(array_diff($a, $b), array_diff($b, $a));
            if ($diff) {
                $warnings[] = "$slug: heading/element ids differ between fa and en: " . implode(', ', array_unique($diff));
            }
        }
    }
    if ($missing) {
        $msg = count($missing) . ' page(s) from the spec are not written yet: ' . implode(', ', $missing);
        if ($flags['allow-missing']) {
            $warnings[] = $msg;
        } else {
            $errors[] = $msg . ' (use --allow-missing to build anyway)';
        }
    }

    $facts = countFacts($root, $errors);
    $present = array_values(array_filter(DOCS_LANGS, static fn (string $l): bool => $pagesByLang[$l] !== []));
    if (!$present) {
        $errors[] = 'no fragments found in ' . $opts['content'];
    }

    $report = static function () use (&$errors, &$warnings): void {
        foreach (array_values(array_unique($warnings)) as $w) {
            fwrite(STDERR, "WARNING $w\n");
        }
        foreach ($errors as $err) {
            fwrite(STDERR, "ERROR   $err\n");
        }
    };
    if ($errors) {
        $report();
        fwrite(STDERR, count($errors) . " error(s). Nothing was written.\n");
        return 1;
    }

    // 4. Render everything into memory.
    $files = [];
    $assetDir = __DIR__ . '/assets';
    foreach (['styles.css', 'app.js'] as $f) {
        $files[$f] = (string) file_get_contents("$assetDir/$f");
    }
    foreach (['logo.svg', 'favicon.svg', 'social-preview.svg'] as $f) {
        $files["assets/$f"] = (string) file_get_contents("$assetDir/$f");
    }
    $files['README.md'] = (string) file_get_contents(__DIR__ . '/README.docs.md');
    $files['.nojekyll'] = '';
    $files['index.html'] = renderRoot($strings, $present);

    $homeSample = <<<'PHP'
        use function RtlyKit\{jdate, to_persian, is_national_code};

        echo jdate('2026-03-21')->format('l j F Y');   // شنبه 1 فروردین 1405
        echo to_persian(1405);                         // ۱۴۰۵
        var_dump(is_national_code('0499370899'));      // bool(true)
        PHP;
    $allPages = [];
    foreach (DOCS_LANGS as $l) {
        $allPages[$l] = $pagesByLang[$l];
    }
    foreach ($present as $lang) {
        $P = [
            'lang' => $lang,
            'S' => $strings[$lang],
            'site' => $strings['site'],
            'langs' => $strings['langs'],
            'pages' => $pagesByLang[$lang],
            'allPages' => $allPages,
            'groupOrder' => $groupOrder,
            'homeSample' => $homeSample,
        ];
        $files["$lang/index.html"] = renderHome($P, $facts);
        $files["$lang/all.html"] = renderAll($P);
        foreach ($pagesByLang[$lang] as $slug => $pg) {
            $files["$lang/$slug.html"] = renderGuide($P, $pg);
        }
        $files["$lang/search-index.js"] = searchIndex($P);
    }
    // llms.txt: English at the root, Persian next to the Persian pages.
    foreach (['en' => ['llms.txt', 'en/'], 'fa' => ['fa/llms.txt', '']] as $lang => [$path, $prefix]) {
        if (!in_array($lang, $present, true)) {
            continue;
        }
        $P = ['S' => $strings[$lang], 'site' => $strings['site'], 'pages' => $pagesByLang[$lang], 'groupOrder' => $groupOrder];
        $files[$path] = llmsTxt($P, $prefix);
    }
    ksort($files);

    // 5. Check or write.
    $out = $opts['out'];
    if ($flags['check']) {
        $stale = [];
        foreach ($files as $rel => $content) {
            $path = "$out/$rel";
            if (!is_file($path)) {
                $stale[] = "missing: $rel";
            } elseif (str_replace("\r\n", "\n", (string) file_get_contents($path)) !== str_replace("\r\n", "\n", $content)) {
                $stale[] = "changed: $rel";
            }
        }
        if (is_dir($out)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = ltrim(substr(normalizePath($f->getPathname()), strlen($out)), '/');
                if ($f->isFile() && !array_key_exists($rel, $files)) {
                    $stale[] = "unexpected: $rel";
                }
            }
        }
        $report();
        if ($stale) {
            fwrite(STDERR, "docs/ is out of date. Run: php tools/docs/build.php\n  " . implode("\n  ", $stale) . "\n");
            return 1;
        }
        $say('docs/ is up to date (' . count($files) . ' files).');
        return 0;
    }

    foreach ($files as $rel => $content) {
        $path = "$out/$rel";
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
            fwrite(STDERR, "Cannot create directory for $path\n");
            return 1;
        }
        file_put_contents($path, $content);
    }
    // Remove stale generated pages in the language folders.
    $removed = 0;
    foreach (DOCS_LANGS as $lang) {
        foreach (glob("$out/$lang/*") ?: [] as $f) {
            $rel = "$lang/" . basename($f);
            if (is_file($f) && !array_key_exists($rel, $files)) {
                unlink($f);
                $removed++;
            }
        }
    }
    $report();
    $say('Wrote ' . count($files) . ' files to ' . $out . ($removed ? " (removed $removed stale)" : '') . '.');
    foreach ($present as $lang) {
        $say("  $lang: " . count($pagesByLang[$lang]) . ' of ' . count($spec['pages']) . ' pages');
    }
    return 0;
}

exit(main($argv));
