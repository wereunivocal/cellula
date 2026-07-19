<?php
/**
 * Composer script: minifies a single PHP file.
 * Strips comments, whitespace, and newlines wherever PHP's syntax
 * allows it, then lint-checks the result before writing it out.
 *
 * Usage:
 *   php scripts/minify.php --input=src/foo.php --output=dist/foo.min.php
 *   php scripts/minify.php -i src/foo.php -o dist/foo.min.php
 */

function minify_php(string $source): string
{
    $tokens = token_get_all($source);
    $out = '';
    $spacePending = false;
    $lastId = null; // token id of the last appended token; null for raw (single-char) tokens

    // Punctuation that never needs a surrounding space to stay valid PHP.
    // (), [], {} can't fuse with whatever's adjacent into a different token
    // no matter the spacing, so both their sides are always safe. `=` covers
    // itself and everything built from it (==, ===, =>, etc., since they
    // always start or end with `=`) because PHP requires an operand right
    // after any of those — never another bare operator character — so they
    // can't collide into something else by losing the gap between them; `>`
    // is included the same way to close out the trailing side of `=>`/`->`.
    // `&`/`|`/`!` cover &&, ||, ! the same way — no valid operand starts with
    // a bare & or |, so nothing they touch can collide either.
    // Deliberately excludes - + ? : since those can change meaning
    // depending on what's adjacent (++/--, ternaries).
    $noSpaceAfter = ['(', '[', ',', ';', ')', '{', '}', '=', '>', '&', '|', '!', '.'];
    $noSpaceBefore = [')', ']', ',', ';', '(', '{', '}', '=', '&', '|', '!', '.'];

    // `.` is the odd one out: it's also the decimal point, and PHP allows
    // leading/trailing-dot floats (.5, 1.), so a `.` sitting next to a
    // number can get silently absorbed into it instead of staying a
    // concatenation operator (`1 . 2` means "12"; `1.2` means the float
    // 1.2 — same characters, different token entirely, and `php -l` won't
    // catch it since both are valid syntax). Only safe to strip when
    // neither side is actually a number.
    $numberIds = [T_LNUMBER, T_DNUMBER];

    // Emits $text, resolving any pending whitespace against what's on
    // either side of it instead of always turning it into a literal space.
    // $id is the token's PHP token constant (null for raw single-char
    // tokens like `.` or `;`), used only for the dot/number check above.
    $append = function (string $text, ?int $id = null) use (&$out, &$spacePending, &$lastId, $noSpaceAfter, $noSpaceBefore, $numberIds) {
        if ($text === '') {
            return;
        }
        if ($spacePending) {
            $prev = substr($out, -1);
            $next = $text[0];
            $dotMeetsNumber =
                ($text === '.' && in_array($lastId, $numberIds, true)) ||
                ($prev === '.' && $lastId === null && in_array($id, $numberIds, true));
            // $prev === ' ' guards against the mandatory space `<?php` already
            // carries in its own token text (PHP requires it to recognize the
            // tag at all), which would otherwise get a second space stacked on.
            $genericNeedsSpace = !in_array($prev, $noSpaceAfter, true) && !in_array($next, $noSpaceBefore, true);
            if ($prev !== ' ' && ($dotMeetsNumber || $genericNeedsSpace)) {
                $out .= ' ';
            }
        }
        $out .= $text;
        $spacePending = false;
        $lastId = $id;
    };

    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$id, $text] = $token;

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                $spacePending = true;
                continue;
            }

            if ($id === T_WHITESPACE) {
                $spacePending = true;
                continue;
            }

            if ($id === T_OPEN_TAG) {
                $out .= '<?php ';
                $spacePending = false;
                continue;
            }

            if ($id === T_CLOSE_TAG) {
                // token_get_all() bakes the newline that PHP's runtime swallows
                // after the closing tag into this token's own text, so left
                // untouched it reintroduces a newline for every closing tag.
                // Also drop any pending space instead of emitting it before the closing tag.
                $out .= '?>';
                $spacePending = false;
                continue;
            }

            if ($id === T_INLINE_HTML) {
                // HTML/text sitting outside php tags isn't tokenized
                // further by PHP, so its newlines survive untouched unless
                // we collapse them here ourselves. Embedded <style> blocks get
                // their CSS minified on top of that.
                $out .= minify_html_fragment($text);
                $spacePending = false;
                continue;
            }

            // Deliberately untouched: heredoc/nowdoc bodies and multi-line
            // string literals. Their newlines are either part of the
            // string's actual value, or (for heredoc) structurally
            // required — PHP demands the closing marker be on its own
            // line. Stripping those would change behavior or break syntax.
            $append($text, $id);
        } else {
            $append($token);
        }
    }

    return trim($out);
}


// Minifies a chunk of literal HTML (a T_INLINE_HTML token's text).
// Collapses whitespace runs to a single space, drops that space
// entirely when it sits directly between two tags, and minifies the
// CSS inside any embedded <style> block.
function minify_html_fragment(string $html): string
{
    $html = preg_replace_callback(
        '/(<style\b[^>]*>)(.*?)(<\/style>)/is',
        static fn(array $m): string => $m[1] . minify_css($m[2]) . $m[3],
        $html
    );

    $html = preg_replace('/\s+/', ' ', $html);

    // Whitespace directly between two tags has no text of its own to
    // separate, so — unlike whitespace next to real text — it's always
    // safe to drop rather than merely collapse. Markup that relies on
    // that gap for visual spacing (e.g. two adjacent inline elements)
    // needs to carry its own spacing instead.
    $html = preg_replace('/>\s+</', '><', $html);

    // Same reasoning at the fragment's own edges: a leading space is only
    // there because a PHP closing tag sits right before it, and dropping it
    // is safe as long as what follows inside the fragment is a tag rather
    // than real text (and symmetrically for a trailing space before the
    // next opening tag).
    $html = preg_replace('/^ (?=<)/', '', $html);
    $html = preg_replace('/(?<=>) $/', '', $html);

    return $html;
}


// Minifies a CSS string: strips comments and whitespace that CSS
// never treats as significant (around { } ; : , and the child
// combinator >), including the whitespace runs that used to be
// newlines. The +/~ sibling combinators are deliberately left alone
// since those characters double as calc()'s operators, where the
// surrounding whitespace is required.
function minify_css(string $css): string
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);

    return trim($css);
}


// Reduces source to the token sequence that actually determines its
// behavior: drops whitespace/comments/tag markers (expected to change) and
// T_INLINE_HTML text (intentionally rewritten by minify_html_fragment(),
// and checked separately). What's left is the id+text of every remaining
// token — if that sequence differs between the original and the minified
// output, some whitespace we dropped was load-bearing after all.
function significant_tokens(string $source): array
{
    $ignored = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML];
    $out = [];

    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], $ignored, true)) {
                continue;
            }
            $out[] = $token[0] . ':' . $token[1];
        } else {
            $out[] = $token;
        }
    }

    return $out;
}

// Lint-checks PHP source by writing it to a temp file and running `php -l`
// against it. Returns [ok: bool, output: string].
function lint_check(string $code): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'minify_lint_');
    rename($tmp, $tmp .= '.php'); // php -l cares about the .php extension
    file_put_contents($tmp, $code);

    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp);
    exec($cmd, $lines, $exitCode);

    unlink($tmp);

    return [$exitCode === 0, implode(PHP_EOL, $lines)];
}

function parse_args(): array
{
    $options = getopt('i:o:', ['input:', 'output:']);

    $src = $options['i'] ?? $options['input'] ?? null;
    $dest = $options['o'] ?? $options['output'] ?? null;

    if ($src === null || $dest === null) {
        fwrite(STDERR, 'Usage: php minify.php --input=<file> --output=<file>' . PHP_EOL);
        exit(1);
    }

    return [$src, $dest];
}

[$src, $dest] = parse_args();

if (!is_file($src)) {
    fwrite(STDERR, "Source file not found: $src" . PHP_EOL);
    exit(1);
}

$source = file_get_contents($src);
$minified = minify_php($source);

[$lintOk, $lintOutput] = lint_check($minified);

if (!$lintOk) {
    fwrite(STDERR, 'Minified output failed lint check — aborting, nothing was written.' . PHP_EOL);
    fwrite(STDERR, $lintOutput . PHP_EOL);
    exit(1);
}

if (significant_tokens($source) !== significant_tokens($minified)) {
    fwrite(STDERR, 'Minified output has a different token sequence than the source — aborting, nothing was written.' . PHP_EOL);
    exit(1);
}

$destDir = dirname($dest);
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}
file_put_contents($dest, $minified);

$originalSize = strlen($source);
$minifiedSize = strlen($minified);
$saved = $originalSize - $minifiedSize;
$pct = $originalSize > 0 ? round($saved / $originalSize * 100, 1) : 0;

echo "Lint check passed." . PHP_EOL;
echo "Minified $src -> $dest" . PHP_EOL;
echo "$originalSize bytes -> $minifiedSize bytes (-$saved bytes, -{$pct}%)" . PHP_EOL;