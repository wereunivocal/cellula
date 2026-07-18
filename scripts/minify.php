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

    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$id, $text] = $token;

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                $out .= ' ';
                continue;
            }

            if ($id === T_WHITESPACE) {
                // Already collapses spaces, tabs, AND newlines to one space.
                $out .= ' ';
                continue;
            }

            if ($id === T_OPEN_TAG) {
                $out .= '<?php ';
                continue;
            }

            if ($id === T_CLOSE_TAG) {
                // token_get_all() bakes the newline that PHP's runtime swallows
                // after the closing tag into this token's own text, so left
                // untouched it reintroduces a newline for every closing tag.
                $out .= '?>';
                continue;
            }

            if ($id === T_INLINE_HTML) {
                // HTML/text sitting outside php tags isn't tokenized
                // further by PHP, so its newlines survive untouched unless
                // we collapse them here ourselves. Embedded <style> blocks get
                // their CSS minified on top of that.
                $out .= minify_html_fragment($text);
                continue;
            }

            // Deliberately untouched: heredoc/nowdoc bodies and multi-line
            // string literals. Their newlines are either part of the
            // string's actual value, or (for heredoc) structurally
            // required — PHP demands the closing marker be on its own
            // line. Stripping those would change behavior or break syntax.
            $out .= $text;
        } else {
            $out .= $token;
        }
    }

    $out = preg_replace('/ {2,}/', ' ', $out);

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
    return preg_replace('/>\s+</', '><', $html);
}


// Minifies a CSS string: strips comments and whitespace that CSS
// never treats as significant (around { } ; : ,), including the
// whitespace runs that used to be newlines.
function minify_css(string $css): string
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);

    return trim($css);
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