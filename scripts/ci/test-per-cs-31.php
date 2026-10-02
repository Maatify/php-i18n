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
    'terminated non-empty switch cases pass' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'marked fall-through to a following case passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break
    case 2:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'no-break marker by design passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break by design
    case 2:
        return;
}
PHP,
        null,
    ],
    'deliberate no-break marker passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // deliberate no break
    case 2:
        return;
}
PHP,
        null,
    ],
    'bare fall-through marker passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through
    case 2:
        return;
}
PHP,
        null,
    ],
    'intentional fall-through marker passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // intentional fall through
    case 2:
        return;
}
PHP,
        null,
    ],
    'deliberate fall-through comment passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // Deliberate fall-through
    case 2:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'intentional falls-through comment passes after case normalization' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // INTENTIONALLY   FALLS THROUGH
    case 2:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'block fall-through comment passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        /* deliberate fall through */
    case 2:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'falling-through comment passes' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // Falling through by design
    case 2:
        return;
    default:
        break;
}
PHP,
        null,
    ],
    'unmarked fall-through is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'unrelated TODO comment does not mark fall-through' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // TODO
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'handled-below comment does not mark fall-through' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // handled below
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'special-case comment does not mark fall-through' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // special case
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'accidental fall-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // accidental fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'unintended fall-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // unintended fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through that should not happen is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through should not happen
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through described as a bug is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // falling through is a bug
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no fall-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as unintended is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break is unintended
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as unintentional is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break is unintentional
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as accidental is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break was accidental
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as a mistake is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break by mistake
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as not intentional is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break is not intentional
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break explicitly described as not deliberate is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break is not deliberate
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break that should never happen is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break should never happen
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through explicitly described as not intentional is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through not intentional
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through may be a bug is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through may be a bug
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through could be accidental is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through could be accidental
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'maybe fall-through is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // maybe fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through that is never deliberate is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through is never deliberate
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through that should be avoided is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through should be avoided
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'accidentally falls-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // accidentally falls through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'unintentional fall-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // unintentional fall-through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'unintentionally falls-through comment is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // unintentionally falls through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through described as an error is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through is an error
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through described as a mistake is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through by mistake
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through that must not happen is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // fall through must not happen
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through preceded by do-not is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // do not fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through preceded by does-not is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // does not fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through preceded by never is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // never fall through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through preceded by avoid is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // avoid falling through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'fall-through preceded by prevent is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // prevent falling through
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'no-break marker described as a bug is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
        recordValue();
        // no break is a bug
    case 2:
        break;
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'final non-empty case without a terminator is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    default:
        recordValue();
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'final case marker without a following case is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    default:
        recordValue();
        // no break
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'final deliberate fall-through marker without a following case is rejected' => [
        <<<'PHP'
<?php
switch ($value) {
    default:
        recordValue();
        // Deliberate fall-through
}
PHP,
        'every non-empty case must end with a terminating statement',
    ],
    'empty grouped switch cases pass' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1:
    case 2:
        break;
    default:
        return;
}
PHP,
        null,
    ],
    'valid multiline switch conditions pass' => [
        <<<'PHP'
<?php
switch ($value) {
    case (
        $first
        === $second
    ):
        break;
    default:
        return;
}
PHP,
        null,
    ],
    'multiline switch conditions still require parentheses' => [
        <<<'PHP'
<?php
switch ($value) {
    case $first
        === $second:
        break;
    default:
        return;
}
PHP,
        'multiline case conditions must be wrapped in parentheses',
    ],
    'switch case bodies still reject braces' => [
        <<<'PHP'
<?php
switch ($value) {
    case 1: {
        break;
    }
    default:
        return;
}
PHP,
        'case bodies must not be wrapped in braces',
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
