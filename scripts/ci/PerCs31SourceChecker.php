<?php

declare(strict_types=1);

/** Repository-owned checks for PER Coding Style 3.1 requirements absent from @PER-CS3x0. */
final class PerCs31SourceChecker
{
    /** @return list<string> */
    public static function violations(string $source, string $file): array
    {
        /** @var list<array{int, string, int}|string> $tokens */
        $tokens = token_get_all($source);
        $tokenLines = [];
        $tokenEndLines = [];
        $currentLine = 1;
        foreach ($tokens as $token) {
            $tokenLines[] = $currentLine;
            $tokenEndLines[] = $currentLine + substr_count(is_array($token) ? $token[1] : $token, "\n");
            $currentLine = $tokenEndLines[array_key_last($tokenEndLines)];
        }
        $errors = [];
        $lines = explode("\n", $source);

        foreach ($tokens as $index => $token) {
            $id = is_array($token) ? $token[0] : null;
            $line = $tokenLines[$index];

            if ($token === '[' && trim($lines[$line - 1] ?? '') === '['
                && self::startsArrayDeclaration($tokens, $index)) {
                $errors[] = self::location($file, $line) . ' multiline array opening bracket must not be on its own line';
            }

            if ($id === T_ENUM) {
                $end = self::findDeclarationEnd($tokens, $index);
                if ($end !== null) {
                    for ($inner = $index + 1; $inner < $end; $inner++) {
                        $item = $tokens[$inner];
                        if (is_array($item) && $item[0] === T_PROTECTED
                            && self::isToken(self::nextSignificant($tokens, $inner + 1), T_CONST)) {
                            $errors[] = self::location($file, $item[2]) . ' non-public enum constants must be private';
                        }
                    }
                }
            }

            if ($id === T_CASE || $id === T_DEFAULT) {
                $colon = self::findCaseColon($tokens, $index + 1);
                if ($colon !== null) {
                    $next = self::nextIndex($tokens, $colon + 1);
                    if ($next !== null && $tokens[$next] === '{') {
                        $errors[] = self::location($file, $line) . ' case bodies must not be wrapped in braces';
                    }
                    if ($id === T_CASE && $next !== null && $tokenLines[$colon] > $line) {
                        $firstExpr = self::nextIndex($tokens, $index + 1);
                        $lastExpr = self::previousIndex($tokens, $colon - 1);
                        if ($firstExpr === null || $lastExpr === null
                            || $tokens[$firstExpr] !== '(' || $tokenLines[$firstExpr] !== $line
                            || $tokens[$lastExpr] !== ')' || $tokenLines[$lastExpr] !== $tokenLines[$colon]
                            || trim($lines[$tokenLines[$colon] - 1] ?? '') !== '):') {
                            $errors[] = self::location($file, $line) . ' multiline case conditions must be wrapped in parentheses';
                        }
                    }
                    $caseEnd = self::findCaseEnd($tokens, $colon + 1);
                    $caseIsEmpty = $caseEnd !== null
                        && self::nextIndex($tokens, $colon + 1) === $caseEnd;
                    if ($caseEnd !== null && !$caseIsEmpty
                        && !self::caseEndsWithTerminator($tokens, $colon + 1, $caseEnd)
                        && !self::hasMarkedFallThrough($tokens, $colon + 1, $caseEnd)) {
                        $errors[] = self::location($file, $line) . ' every non-empty case must end with a terminating statement';
                    }
                }
            }

            if ($id === T_ATTRIBUTE && self::previousIsNew($tokens, $index)) {
                array_push($errors, ...self::checkAnonymousClassAttributes($source, $tokens, $tokenLines, $index, $file));
            }

            if ($id === T_FUNCTION && self::isClosure($tokens, $index)) {
                $body = self::findClosureBody($tokens, $index);
                if ($body !== null && self::nextSignificant($tokens, $body + 1) === '}') {
                    $close = self::nextIndex($tokens, $body + 1);
                    if ($close !== null && $tokenLines[$body] !== $tokenLines[$close]) {
                        $errors[] = self::location($file, $tokenLines[$body]) . ' empty closure braces must be on one line';
                    }
                }
            }
        }

        array_push($errors, ...self::checkMultilineLists($source, $tokens, $tokenLines, $tokenEndLines, $file));

        return $errors;
    }

    /** @param list<array{int, string, int}|string> $tokens
     *  @param list<int> $tokenLines
     *  @param list<int> $tokenEndLines
     *  @return list<string>
     */
    private static function checkMultilineLists(string $source, array $tokens, array $tokenLines, array $tokenEndLines, string $file): array
    {
        $errors = [];
        foreach ($tokens as $open => $token) {
            if ($token !== '(') {
                continue;
            }

            $context = self::listContext($tokens, $open);
            if ($context === null) {
                continue;
            }

            $close = self::matchingParen($tokens, $open);
            if ($close === null) {
                continue;
            }

            $items = self::callArguments($tokens, $open, $close);
            if ($items === []) {
                continue;
            }

            $openLine = $tokenLines[$open];
            if (!self::isMultilineList($items, $context, $openLine, $tokenLines, $tokenEndLines, $close)) {
                continue;
            }

            $firstStart = $items[0][0];
            if ($tokenLines[$firstStart] <= $openLine) {
                $errors[] = self::location($file, $tokenLines[$firstStart]) . ' first item in a multiline list must start on the next line';
            }
            $expectedIndent = self::lineIndent($source, $openLine) . '    ';
            $firstLine = $tokenLines[$firstStart];
            if (self::lineIndent($source, $firstLine) !== $expectedIndent) {
                $errors[] = self::location($file, $firstLine) . ' multiline list items must be indented once';
            }

            $previousEndLine = $tokenEndLines[$items[0][1]];
            foreach (array_slice($items, 1) as [$start, $end]) {
                $startLine = $tokenLines[$start];
                if ($startLine <= $previousEndLine) {
                    $errors[] = self::location($file, $startLine) . ' multiline lists must keep one item per line';
                }
                if ($startLine > $previousEndLine && self::lineIndent($source, $startLine) !== $expectedIndent) {
                    $errors[] = self::location($file, $startLine) . ' multiline list items must be indented once';
                }
                $previousEndLine = $tokenEndLines[$end];
            }

            $lastToken = self::previousIndex($tokens, $close - 1);
            if ($lastToken === null || $tokens[$lastToken] !== ',') {
                $errors[] = self::location($file, $tokenLines[$close]) . ' multiline lists must have a trailing comma';
            }

            if (in_array($context, ['declaration-parameters', 'closure-parameters', 'closure-use'], true)) {
                $lastItem = $items[array_key_last($items)] ?? null;
                $lastItemLine = $lastItem === null ? null : $tokenEndLines[$lastItem[1]];
                if ($lastItemLine !== null && $tokenLines[$close] <= $lastItemLine) {
                    $errors[] = self::location($file, $tokenLines[$close]) . ' multiline list closing parenthesis must be on its own line';
                }

                $endingClose = $close;
                if ($context === 'closure-parameters') {
                    $useOpen = self::closureUseOpen($tokens, $close);
                    if ($useOpen !== null) {
                        $useClose = self::matchingParen($tokens, $useOpen);
                        if ($useClose !== null) {
                            $useItems = self::callArguments($tokens, $useOpen, $useClose);
                            if ($useItems !== [] && self::isMultilineList(
                                $useItems,
                                'closure-use',
                                $tokenLines[$useOpen],
                                $tokenLines,
                                $tokenEndLines,
                                $useClose,
                            )) {
                                $endingClose = $useClose;
                            }
                            $body = self::nextBodyBrace($tokens, $useClose + 1);
                        } else {
                            $body = self::nextBodyBrace($tokens, $close + 1);
                        }
                    } else {
                        $body = self::nextBodyBrace($tokens, $close + 1);
                    }
                } else {
                    $body = self::nextBodyBrace($tokens, $close + 1);
                }

                if ($body !== null && ($tokenLines[$body] !== $tokenLines[$endingClose]
                    || self::lineIndent($source, $tokenLines[$endingClose]) !== self::lineIndent($source, $openLine))) {
                    $errors[] = self::location($file, $tokenLines[$endingClose]) . ' multiline declaration or closure list must close with the opening brace on the same line';
                }
            }
        }
        return $errors;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function listContext(array $tokens, int $open): ?string
    {
        $previous = self::previousIndex($tokens, $open - 1);
        if ($previous === null) {
            return null;
        }

        if (self::isToken($tokens[$previous], T_USE)) {
            return 'closure-use';
        }

        if (self::isToken($tokens[$previous], T_FN)
            || (self::isAmpersand($tokens[$previous])
                && self::isToken(self::previousSignificant($tokens, $previous - 1), T_FN))) {
            return 'arrow-parameters';
        }

        if (self::isToken($tokens[$previous], T_FUNCTION)
            || (self::isAmpersand($tokens[$previous])
                && self::isToken(self::previousSignificant($tokens, $previous - 1), T_FUNCTION))) {
            return 'closure-parameters';
        }

        $function = $previous;
        if (self::isAmpersand($tokens[$function])) {
            $function = self::previousIndex($tokens, $function - 1) ?? -1;
        }
        if (self::isToken($tokens[$function], T_STRING)) {
            $beforeName = self::previousIndex($tokens, $function - 1);
            if ($beforeName !== null && self::isAmpersand($tokens[$beforeName])) {
                $beforeName = self::previousIndex($tokens, $beforeName - 1);
            }
            if ($beforeName !== null && self::isToken($tokens[$beforeName], T_FUNCTION)) {
                return 'declaration-parameters';
            }
        }

        return self::isCallParenthesis($tokens, $open) ? 'call' : null;
    }

    /** @param array{int, string, int}|string $token */
    private static function isAmpersand(mixed $token): bool
    {
        return $token === '&' || (is_array($token) && in_array($token[0], [T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true));
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function previousSignificant(array $tokens, int $index): mixed
    {
        $index = self::previousIndex($tokens, $index);
        return $index === null ? null : $tokens[$index];
    }

    /**
     * @param list<array{int, int}> $items
     * @param list<int> $tokenLines
     * @param list<int> $tokenEndLines
     */
    private static function isMultilineList(array $items, string $context, int $openLine, array $tokenLines, array $tokenEndLines, int $close): bool
    {
        if (count($items) === 1 && $context === 'call') {
            return $tokenLines[$items[0][0]] > $openLine
                && $tokenEndLines[$items[0][1]] === $tokenLines[$items[0][0]];
        }

        if ($tokenLines[$items[0][0]] > $openLine) {
            return true;
        }

        $previousEndLine = $tokenEndLines[$items[0][1]];
        foreach (array_slice($items, 1) as [$start, $end]) {
            if ($tokenLines[$start] > $previousEndLine) {
                return true;
            }
            $previousEndLine = $tokenEndLines[$end];
        }

        return $tokenLines[$close] > $previousEndLine;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function closureUseOpen(array $tokens, int $close): ?int
    {
        $index = self::nextIndex($tokens, $close + 1);
        if ($index !== null && self::isToken($tokens[$index], T_USE)) {
            $open = self::nextIndex($tokens, $index + 1);
            return $open !== null && $tokens[$open] === '(' ? $open : null;
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function nextBodyBrace(array $tokens, int $index): ?int
    {
        for (; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                return $index;
            }
            if ($tokens[$index] === ';' || $tokens[$index] === '=>' || $tokens[$index] === '}') {
                return null;
            }
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function isCallParenthesis(array $tokens, int $open): bool
    {
        $previous = self::previousIndex($tokens, $open - 1);
        if ($previous === null) {
            return false;
        }
        $token = $tokens[$previous];
        if (in_array($token, [')', ']', '}'], true)) {
            return true;
        }
        if (!is_array($token) || !in_array($token[0], [T_VARIABLE, T_STRING, T_CLASS, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
            return false;
        }
        return true;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function matchingParen(array $tokens, int $open): ?int
    {
        $stack = [')'];
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        for ($index = $open + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token)) {
                if ($token[0] === T_ATTRIBUTE) {
                    $stack[] = ']';
                }
                continue;
            }
            if (isset($pairs[$token])) {
                $stack[] = $pairs[$token];
            } elseif (in_array($token, [')', ']', '}'], true)) {
                if (array_pop($stack) !== $token) {
                    return null;
                }
                if ($stack === []) {
                    return $index;
                }
            }
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens
     *  @return list<array{int, int}>
     */
    private static function callArguments(array $tokens, int $open, int $close): array
    {
        $arguments = [];
        $start = self::nextIndex($tokens, $open + 1);
        if ($start === null || $start >= $close) {
            return [];
        }
        $depth = 0;
        for ($index = $open + 1; $index < $close; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $depth++;
            } elseif (!is_array($token)) {
                if (in_array($token, ['(', '[', '{'], true)) {
                    $depth++;
                } elseif (in_array($token, [')', ']', '}'], true)) {
                    $depth--;
                } elseif ($token === ',' && $depth === 0) {
                    $end = self::previousIndex($tokens, $index - 1);
                    if ($end !== null && $start !== null && $end >= $start) {
                        $arguments[] = [$start, $end];
                    }
                    $start = self::nextIndex($tokens, $index + 1);
                }
            }
        }
        $end = self::previousIndex($tokens, $close - 1);
        if ($end !== null && $start !== null && $end >= $start && $tokens[$end] !== ',') {
            $arguments[] = [$start, $end];
        }
        return $arguments;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     * @param list<int> $tokenLines
     * @return list<string>
     */
    private static function checkAnonymousClassAttributes(string $source, array $tokens, array $tokenLines, int $attribute, string $file): array
    {
        $errors = [];
        $new = self::previousIndex($tokens, $attribute - 1);
        if ($new === null) {
            return [];
        }

        $newLine = $tokenLines[$new];
        $attributeLine = $tokenLines[$attribute];
        $expectedIndent = self::lineIndent($source, $newLine) . '    ';
        if ($attributeLine !== $newLine + 1 || self::lineIndent($source, $attributeLine) !== $expectedIndent) {
            $errors[] = self::location($file, $attributeLine) . ' anonymous-class attributes must start on the next line, indented once';
        }

        $current = $attribute;
        $lastAttributeLine = $attributeLine;
        while (true) {
            $end = self::findAttributeEnd($tokens, $current);
            if ($end === null) {
                return $errors;
            }
            $lastAttributeLine = $tokenLines[$end];
            $following = self::nextIndex($tokens, $end + 1);
            if ($following === null) {
                return $errors;
            }

            if (self::isToken($tokens[$following], T_ATTRIBUTE)) {
                $nextAttributeLine = $tokenLines[$following];
                if ($nextAttributeLine !== $lastAttributeLine + 1
                    || self::lineIndent($source, $nextAttributeLine) !== $expectedIndent) {
                    $errors[] = self::location($file, $nextAttributeLine) . ' consecutive anonymous-class attributes must be adjacent and equally indented';
                }
                $current = $following;
                continue;
            }

            if (!self::isToken($tokens[$following], T_CLASS)
                || $tokenLines[$following] !== $lastAttributeLine + 1
                || self::lineIndent($source, $tokenLines[$following]) !== $expectedIndent) {
                $errors[] = self::location($file, $tokenLines[$following]) . ' anonymous-class declaration must start on the next line at the attribute indentation';
            }
            return $errors;
        }
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function findAttributeEnd(array $tokens, int $index): ?int
    {
        $depth = 1;
        for ($index++; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '[') {
                $depth++;
            } elseif ($token === ']') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }
        return null;
    }

    private static function lineIndent(string $source, int $line): string
    {
        $text = explode("\n", $source)[$line - 1] ?? '';
        return substr($text, 0, strlen($text) - strlen(ltrim($text, " \t")));
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function startsArrayDeclaration(array $tokens, int $index): bool
    {
        $previous = self::previousIndex($tokens, $index - 1);
        if ($previous === null) {
            return true;
        }
        $token = $tokens[$previous];
        if (!is_array($token)) {
            return !in_array($token, [')', ']', '}'], true);
        }

        // Tokens that can terminate an expression immediately before postfix indexing.
        return !in_array($token[0], [
            T_VARIABLE,
            T_STRING,
            T_LNUMBER,
            T_DNUMBER,
            T_CONSTANT_ENCAPSED_STRING,
            T_LINE,
            T_FILE,
            T_DIR,
            T_CLASS_C,
            T_TRAIT_C,
            T_METHOD_C,
            T_FUNC_C,
            T_NS_C,
            T_STRING_VARNAME,
            T_INC,
            T_DEC,
        ], true);
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function caseEndsWithTerminator(array $tokens, int $start, int $end): bool
    {
        $terminators = [T_BREAK, T_RETURN, T_THROW, T_CONTINUE, T_EXIT, T_GOTO];
        $depth = 0;
        $statementStart = null;
        $lastStatementStart = null;
        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($depth === 0 && $statementStart === null) {
                $statementStart = $index;
            }
            if (!is_array($token)) {
                if (in_array($token, ['{', '(', '['], true)) {
                    $depth++;
                } elseif (in_array($token, ['}', ')', ']'], true)) {
                    $depth--;
                    if ($token === '}' && $depth === 0) {
                        $statementStart = null;
                    }
                } elseif ($token === ';' && $depth === 0) {
                    $lastStatementStart = $statementStart;
                    $statementStart = null;
                }
                continue;
            }
        }
        return $lastStatementStart !== null
            && is_array($tokens[$lastStatementStart])
            && in_array($tokens[$lastStatementStart][0], $terminators, true);
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function hasMarkedFallThrough(array $tokens, int $start, int $end): bool
    {
        if (!self::isToken($tokens[$end] ?? null, T_CASE)
            && !self::isToken($tokens[$end] ?? null, T_DEFAULT)) {
            return false;
        }

        for ($index = $end - 1; $index >= $start; $index--) {
            $token = $tokens[$index];
            if (self::isToken($token, T_WHITESPACE)) {
                continue;
            }

            if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                return false;
            }

            // Match the default marker_text accepted by the active no_break_comment fixer.
            return preg_match('~^((//|#)\\s*no break\\s*)|(/\\*\\*?\\s*no break(\\s+.*)*\\*/)$~i', $token[1]) === 1;
        }

        return false;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function nextIndex(array $tokens, int $index): ?int
    {
        for (; $index < count($tokens); $index++) {
            if (is_array($tokens[$index]) && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $index;
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function previousIndex(array $tokens, int $index): ?int
    {
        for (; $index >= 0; $index--) {
            if (is_array($tokens[$index]) && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $index;
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function nextSignificant(array $tokens, int $index): mixed
    {
        $index = self::nextIndex($tokens, $index);
        return $index === null ? null : $tokens[$index];
    }

    private static function isToken(mixed $token, int $id): bool
    {
        return is_array($token) && $token[0] === $id;
    }

    private static function location(string $file, int $line): string
    {
        return $file . ':' . $line . ':';
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function findDeclarationEnd(array $tokens, int $index): ?int
    {
        $depth = 0;
        $opened = false;
        for (; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                $depth++;
                $opened = true;
            } elseif ($tokens[$index] === '}') {
                $depth--;
                if ($opened && $depth === 0) {
                    return $index;
                }
            }
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function findCaseColon(array $tokens, int $index): ?int
    {
        $depth = 0;
        for (; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token)) {
                continue;
            }
            if (in_array($token, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($token, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($token === ':' && $depth === 0) {
                return $index;
            } elseif (in_array($token, ['=', ';'], true) && $depth === 0) {
                return null;
            }
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function findCaseEnd(array $tokens, int $index): ?int
    {
        $depth = 0;
        for (; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_CASE, T_DEFAULT], true) && $depth === 0) {
                return $index;
            }
            if (!is_array($token)) {
                if (in_array($token, ['{', '(', '['], true)) {
                    $depth++;
                } elseif (in_array($token, ['}', ')', ']'], true)) {
                    if ($token === '}' && $depth === 0) {
                        return $index;
                    }
                    $depth--;
                }
            }
        }
        return null;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function previousIsNew(array $tokens, int $index): bool
    {
        $previous = self::previousIndex($tokens, $index - 1);
        return $previous !== null && self::isToken($tokens[$previous], T_NEW);
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function isClosure(array $tokens, int $index): bool
    {
        $next = self::nextIndex($tokens, $index + 1);
        return $next !== null && ($tokens[$next] === '(' || $tokens[$next] === '&');
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private static function findClosureBody(array $tokens, int $index): ?int
    {
        for ($index++; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                return $index;
            }
            if ($tokens[$index] === ';' || $tokens[$index] === '=>' || $tokens[$index] === '}') {
                return null;
            }
        }
        return null;
    }
}
