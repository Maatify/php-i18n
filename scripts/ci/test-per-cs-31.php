<?php

declare(strict_types=1);

require_once __DIR__ . '/PerCs31SourceChecker.php';

$cases = [
    'same-line anonymous-class attribute is rejected' => [
        <<<'PHP'
<?php
$example = new #[Marker] class {};
PHP,
        'anonymous-class attributes must start on the next line',
    ],
    'anonymous-class attribute indentation is rejected' => [
        <<<'PHP'
<?php
$example = new
  #[Marker]
  class {};
PHP,
        'anonymous-class attributes must start on the next line, indented once',
    ],
    'anonymous-class declaration indentation is rejected' => [
        <<<'PHP'
<?php
$example = new
    #[Marker]
class {};
PHP,
        'anonymous-class declaration must start on the next line at the attribute indentation',
    ],
    'standalone ternary-array opener is rejected' => [
        <<<'PHP'
<?php
$value = $condition
    ? null
    :
    [
        'value',
    ];
PHP,
        'multiline array opening bracket must not be on its own line',
    ],
    'valid anonymous-class attributes and multiline arrays pass' => [
        <<<'PHP'
<?php
$example = new
    #[First]
    #[Second]
    class {};
$value = $condition
    ? null
    : [
        'value',
    ];
PHP,
        null,
    ],
    'array-argument bridge with expression-bound opener is compliant' => [
        <<<'PHP'
<?php
consume(
    'query',
    ([
        'value',
    ]),
    'tail',
);
PHP,
        null,
    ],
    'long-closure parameters reject multiple items on one line' => [
        <<<'PHP'
<?php
$closure = function (
    $first, $second,
) {};
PHP,
        'multiline lists must keep one item per line',
    ],
    'valid long-closure parameters pass' => [
        <<<'PHP'
<?php
$closure = function (
    $first,
    $second,
) {};
PHP,
        null,
    ],
    'by-reference closure parameter indentation is checked' => [
        <<<'PHP'
<?php
$closure = function &(
  $first,
    $second,
) {};
PHP,
        'multiline list items must be indented once',
    ],
    'valid by-reference long-closure parameters pass' => [
        <<<'PHP'
<?php
$closure = function &(
    $first,
    $second,
) {};
PHP,
        null,
    ],
    'closure use list requires its trailing comma' => [
        <<<'PHP'
<?php
$closure = function () use (
    $first,
    $second
) {};
PHP,
        'multiline lists must have a trailing comma',
    ],
    'valid closure use list passes' => [
        <<<'PHP'
<?php
$closure = function () use (
    $first,
    $second,
) {};
PHP,
        null,
    ],
    'multiline closure parameters with single-line use list pass' => [
        <<<'PHP'
<?php
$closure = function (
    $first,
    $second,
) use ($value) {
    return null;
};
PHP,
        null,
    ],
    'multiline closure parameters with valid multiline use list pass' => [
        <<<'PHP'
<?php
$closure = function (
    $first,
    $second,
) use (
    $value,
    $other,
) {
    return null;
};
PHP,
        null,
    ],
    'multiline closure parameters reject invalid multiline use list' => [
        <<<'PHP'
<?php
$closure = function (
    $first,
    $second,
) use (
    $value, $other,
) {
    return null;
};
PHP,
        'multiline lists must keep one item per line',
    ],
    'single-line closure parameters with single-line use list pass' => [
        <<<'PHP'
<?php
$closure = function ($first, $second) use ($value) {
    return null;
};
PHP,
        null,
    ],
    'short-closure parameters reject multiple items on one line' => [
        <<<'PHP'
<?php
$closure = fn(
    $first, $second,
) => $first + $second;
PHP,
        'multiline lists must keep one item per line',
    ],
    'valid short-closure parameters pass' => [
        <<<'PHP'
<?php
$closure = fn(
    $first,
    $second,
) => $first + $second;
PHP,
        null,
    ],
    'named function parameters reject multiple items on one line' => [
        <<<'PHP'
<?php
function named(
    $first, $second,
) {}
PHP,
        'multiline lists must keep one item per line',
    ],
    'method parameters reject multiple items on one line' => [
        <<<'PHP'
<?php
class Fixture
{
    public function named(
        $first, $second,
    ) {}
}
PHP,
        'multiline lists must keep one item per line',
    ],
    'multiline calls reject multiple arguments on one line' => [
        <<<'PHP'
<?php
consume(
    $first, $second,
);
PHP,
        'multiline lists must keep one item per line',
    ],
    'multiline calls require a trailing comma' => [
        <<<'PHP'
<?php
consume(
    $first,
    $second
);
PHP,
        'multiline lists must have a trailing comma',
    ],
    'multiline declaration closing brace stays with parameter close' => [
        <<<'PHP'
<?php
function separated(
    $first,
    $second,
)
{
}
PHP,
        'multiline declaration or closure list must close with the opening brace on the same line',
    ],
    'multiline parameter closing parenthesis is on its own line' => [
        <<<'PHP'
<?php
$closure = function (
    $first,
    $second,) {};
PHP,
        'multiline list closing parenthesis must be on its own line',
    ],
    'one multiline call argument is an expression not a multiline list' => [
        <<<'PHP'
<?php
consume(
    wrap([
        'value',
    ]),
);
PHP,
        null,
    ],
    'first multiline list item must start after the opener' => [
        <<<'PHP'
<?php
consume($first,
    $second,
);
PHP,
        'first item in a multiline list must start on the next line',
    ],
    'multiline list item indentation is checked' => [
        <<<'PHP'
<?php
consume(
  $first,
    $second,
);
PHP,
        'multiline list items must be indented once',
    ],
    'clone without parentheses is a SHOULD and passes' => [
        <<<'PHP'
<?php
$copy = clone $value;
PHP,
        null,
    ],
    'single-line empty closure satisfies the MUST without conversion' => [
        <<<'PHP'
<?php
$noop = function () {};
PHP,
        null,
    ],
    'multiline empty closure braces violate the MUST' => [
        <<<'PHP'
<?php
$noop = function () {
};
PHP,
        'empty closure braces must be on one line',
    ],
];

$failures = [];
foreach ($cases as $name => [$source, $expected]) {
    try {
        $fixtureTokens = token_get_all($source, TOKEN_PARSE);
        if ($fixtureTokens === []) {
            $failures[] = $name . ': fixture produced no PHP tokens';
            continue;
        }
    } catch (ParseError $exception) {
        $failures[] = $name . ': fixture is not valid PHP: ' . $exception->getMessage();
        continue;
    }

    $violations = PerCs31SourceChecker::violations($source, '<fixture>');
    $output = implode("\n", $violations);
    if ($expected === null ? $violations !== [] : !str_contains($output, $expected)) {
        $failures[] = $name . ($violations === [] ? ': expected a violation, got none' : ': ' . $output);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[i18n-per-cs-3.1-tests] failed:\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}

echo sprintf("[i18n-per-cs-3.1-tests] %d focused positive/negative proofs passed\n", count($cases));
