<?php
// doxygen v1.8

$src = file_get_contents($argv[1]);
$ns = preg_match('/^\s*namespace\s+(?<NS>\S+);/m', $src, $match) ? $match['NS'] . '\\' : '';

function status (string $message = '') {
    static $last;
    if ($message) {
        if (!$last) {
            fputs(STDERR, "\n");
        }
        fputs(STDERR, "\e[92m{$message}\e[0m\n");
        $last = $message;
    }
    elseif ($last) {
        if (!$message) {
            fputs(STDERR, "\n");
        }
    }
}

function getClass (string $declaration): string {
    global $ns;
    preg_match('/^((?!class).)*class\s+(?<NAME>\w+)/is', $declaration, $class);
    return $ns . $class['NAME'];
}

// convert traits to abstract classes
$rx = '/^trait\s+(?<TRAIT>\w+)\s*{/m';
while (preg_match($rx, $src, $match)) {
    status("{$ns}{$match['TRAIT']}: converting trait to abstract class.");
    $src = str_replace($match[0], "abstract class {$match['TRAIT']} {", $src);
}

// "use" traits by extending them one by one.
// there is currently a limitation to this:
// all `use` statements must be at the top of each class with no obstructions.
$rx = <<<'RX'
/^
    \s*(?<CLASS>
        (final\s+|abstract\s+)?
        class\s+
        (?<CLASS_NAME>\w+)
    )
    \s*(?<EXTENDS>extends\s+        (\w+(,\s*)?)+   )?
    \s*(?<IMPLEMENTS>implements\s+  (\w+(,\s*)?)+   )?
    \s*{
    \s*use\s+
    (?<TRAIT>\w+)
    \s*
    ( ; | {[^}]*} )               #semicolon or body
/imx
RX;
while (preg_match($rx, $src, $match)) {
    status("{$ns}{$match['CLASS_NAME']}: \"using\" trait {$match['TRAIT']} by extending it.");
    $extends = $match['EXTENDS'] ? "{$match['EXTENDS']}, {$match['TRAIT']}" : "extends {$match['TRAIT']}";
    $src = str_replace($match[0], "{$match['CLASS']} {$extends} {$match['IMPLEMENTS']} {", $src);
}

// convert @property to properties.
// any @tags can be used in @property comments as long as they're on the initial line.
$rx = <<<'RX'
/^
    \s*\*\s*@property\s+
    (?<TYPE>\w\S*)?
    \s*(?<NAME>\$\w+)
    (?<COMMENT>
        ((?!                # eat up to but not including
            \*\s*@          # next tag
            |
            \*\s*\n         # or empty comment line
            |
            \*\/            # or end of docblock
        ).)*
    )
    (?<STASH>
        ((?!
            \*\/            # capture the rest of the docblock
        ).)*
        \*\/                # capture end of the docblock
        (?<CLASS>
            [^{]+{          # capture the class declaration
        )
    )
/imsx
RX;
while (preg_match($rx, $src, $match)) {
    $class = getClass($match['CLASS']);
    status("{$class}: Converting @property {$match['NAME']} to class property.");
    $comment = trim($match['COMMENT']);
    $comment = preg_replace('/^\s*\*\s{0,2}/m', '     * ', $comment); // fix continued indent
    $prop = <<<PROP
        /**
         * {$comment}
         */
        magic public {$match['TYPE']} {$match['NAME']};
    PROP;
    $src = str_replace($match[0], "{$match['STASH']}\n\n{$prop}", $src);
}

// convert @method to functions.
// any @tags can be used in @method comments as long as they're on the initial line.
$rx = <<<'RX'
/^
    \s*\*\s*@method\s+
    (?<STATIC>static\s)?
    \s*(?<TYPE>\S+)?
    \s*(?<NAME>\w+)
    \s*(?<SIG>
        [^)]+\)
    )
    (?<COMMENT>
        ((?!                # eat up to but not including
            \*\s*@          # next tag
            |
            \*\s*\n         # or empty comment line
            |
            \*\/            # or end of docblock
        ).)*
    )
    (?<STASH>
        ((?!
            \*\/            # capture the rest of the docblock
        ).)*
        \*\/                # capture end of the docblock
        (?<CLASS>
            [^{]+{          # capture the class declaration
        )
    )
/imsx
RX;
while (preg_match($rx, $src, $match)) {
    $static = $match['STATIC'];
    $type = $match['TYPE'];
    $name = $match['NAME'];
    if ($static and !$type) {
        $type = 'static';
        $static = '';
    }
    if ($type and !$name) {
        $name = $type;
        $type = '';
    }
    if ($type === 'static') {
        $type = 'self'; // avoid double static
    }
    $class = getClass($match['CLASS']);
    status("{$class}: Converting {$static}@method {$match['NAME']} to class method.");
    $comment = trim($match['COMMENT']);
    $comment = preg_replace('/^\s*\*\s{0,2}/m', '     * ', $comment); // fix continued indent
    $function = <<<FUNCTION
        /**
         * {$comment}
         * @return {$type}
         */
        magic public {$static}function {$match['NAME']} {$match['SIG']};
    FUNCTION;
    $src = str_replace($match[0], "{$match['STASH']}\n\n{$function}", $src);
}

// add class member type hints.
// @var has to be stripped to preserve docblock content in result.
$rx = <<<'RX'
/^
    (\h*\*)                 # 1: line start
    \h*@var\h+
    (\S+)                   # 2: type
    \h*
    (                       # 3: rest of docblock
        ((?!\*\/).)*        # 4
        \*\/
    )
    ([\s\w]+)               # 5: sig
    (\$\w+)                 # 6: name
/imsx
RX;
$replace = '$1 $3 $5 $2 $6';
$src = preg_replace($rx, $replace, $src);

// replace "@return static" with "@return self",
// to avoid false static method during type extraction
$src = preg_replace('/^(\s*\*\s*@return\s+((?!static).)*)static/m', '$1self', $src);

// add type hinting to methods
$rx = <<<'RX'
/^
    (                       # 1
        \s*\*\s*@return\s+
        (\S+)               # 2: type
        ((?!\*\/).)*        # 3: text up to but not including end of docblock
        \*\/
    )
    ([\s\w]+)               # 4: sig
    function
/imsx
RX;
$src = preg_replace($rx, '$1 $4$2 function', $src);

// @return $this and static
$rx = <<<'RX'
/^
    (\s*\*\s*@return\s+)    # 1: @return
    (\S+\|)*                # 2: types before
    (\$this|self)           # 3: self ref
    (\|\S+)*                # 4: types after
/imsx
RX;
$src = preg_replace($rx, '$1$2{@link self $3}$4', $src);

// finally, after all return types are extracted, fix PHP 7 return hints.
// doxygen can't handle "function(): TYPE { }",
// 1) move the php7 return type for vanilla methods:
$rx = <<<'RX'
/^
    (                                   # 1: sig
        \h*
        (final\h+|abstract\h+)?         # 2
        (public|protected|private)\h+   # 3
        (static\h+)?                    # 4
    )
    (function\h+\w+\h*\(.*\))           # 5
    \h*:\h*
    (\w+)                               # 6: type
    \s*(\S)                             # 7: end of type
/imx
RX;
$src = preg_replace($rx, '$1 $6 $5 $7', $src);
// 2) remove the php7 return type for all methods everywhere:
$rx = <<<'RX'
/^
    (                   # 1: sig
        \h*\w[\h\S]+
        function\h+
        \w+\h*
        \(.*\)
    )
    \h*:\h*
    \w+\s*(\S)          #2: end of type
/imx
RX;
$src = preg_replace($rx, '$1 $2', $src);

// doxygen doesn't support @inheritdoc. the best it can do is INHERIT_DOCS,
// which requires no docblock at all. we can approximate @inheritDoc by:
// 1) removing @inheritDoc
$src = preg_replace('/@inheritDoc/i', '', $src);
// 2) removing empty docblocks
$src = preg_replace('/\/\*\*[\s\*]+\/\s*/', '', $src);

// @internal is broken, even if at the top of a docblock.
// force the member to "internal" visibility
$rx = <<<'RX'
/^
    \h*\*\h*@internal\s
    (                               # 1
        ((?!\*\/).)*                # 2
        \*\/
        \s*
        (final\s+|abstract\s+)?     # 3
    )
    (public|protected|private)      # 4
/imsx
RX;
$src = preg_replace($rx, '$1internal', $src);

// doxygen aliases are broken. expand custom aliases here.
// NOTE: doxygen stops at the first period, regardless of what we capture.

// @immutable with explanation
$src = preg_replace(
    '/^(\h*\*\h*)@immutable\h+(.+)$/im',
    '$1@xrefitem immutable "Immutable" "Immutable Objects" $2',
    $src
);
// @immutable without explanation
$src = preg_replace(
    '/^(\h*\*\h*)@immutable\h*$/im',
    '$1@xrefitem immutable "Immutable" "Immutable Objects" Each instance has a permanent state.',
    $src
);

// @depends
$src = preg_replace(
    '/^(\h*\*\h*)@depends\h+(.+)$/im',
    '$1@xrefitem depends "Depends" "Conditional Methods" $2',
    $src
);

// @mixin
$src = preg_replace(
    '/^(\h*\*\h*)@mixin\h+(.+)$/im',
    '$1@xrefitem mixin "For Use With" "Traits" $2',
    $src
);

// @uses / @used-by
$src = preg_replace(
    '/^(\h*\*\h*)@use(s|d-by)\h+/im',
    '$1@see ',
    $src
);

// fix up named links in @see
// extra.js takes care of opening all external links in new tabs.
$src = preg_replace(
    '/^(\h*\*\h*@see)\h+(https?:\/\/\S+)\h+(.+)$/im',
    '$1 [$3]($2)',
    $src
);

// emojis
foreach ([':warning:', ':info:'] as $label) {
    $html = "<img src=\"../" . str_replace(':', '', $label) . ".png\">";
    $src = str_replace(' > ' . $label, $html, $src);
}

// done
status();
echo $src;
