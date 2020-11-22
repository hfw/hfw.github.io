<?php

// this code is purposefully trash to fuzz the input filter as much as possible.

namespace My\Name\Space;

/**
 * @immutable
 * expect: @xrefitem immutable "Immutable" "Immutable Objects" ^^^default description^^^
 *
 * @immutable foo bar
 * expect: @xrefitem immutable "Immutable" "Immutable Objects" foo bar
 */
interface I {

}

/**
 * @see https://example.com
 * expect: @see https://example.com
 *
 * @see https://example.com?asd&123#fooBar Foo Bar
 * expect: @see [Foo Bar](https://example.com?asd&123#fooBar)
 *
 * expect: abstract class Z {
 */
trait Z {

    /**
     * expect: public function foo() {
     */
    public function foo () {

    }
}

/**
 * expect: abstract class ZZ extends Z {
 */
trait ZZ {

    use Z;
}

/**
 * a class named Y
 *
 *
 * expect: abstract class Y extends Z implements I {
 */
abstract class Y
    implements I {

    use Z;

    /**
     * expect: abstract public int function A();
     */
    abstract public function A (): int;
}

/**
 * A class named X.
 *
 * @property            string $foo    @see bar() here's a tag within a tag.
 *  expect: @see bar() here's a tag within a tag.
 *  Access `foo`
 *  a multiline
 *  comment for $foo
 *  with preserved indentation
 *      - like so
 *      - and so
 * @method              $this           bar(int $bar) Sets `bar`
 *  a multiline
 *  comment for bar
 * @method              static static   baz(int $baz) This is
 *  a multiline
 *  comment for baz.
 *  with preserved indentation
 *      - like so
 *      - and so
 *
 * @depends some text
 * expect: some text
 *
 * @uses    some text
 * expect: @see some text
 *
 * @used-by some text
 * expect: @see some text
 *
 * expect: final class X extends Y, ZZ implements I {
 * expect: magic public static self baz(int $baz);
 * expect: magic public $this bar(int $bar);
 * expect: magic public string $foo;
 */
final class X
    extends Y
    implements I {

    use ZZ {
        foo as bar; // this shouldn't mess up the use statement;
    }

    /**
     * Fizz.
     *
     * @var $this |that[] Some text.
     *
     * expect: protected static $this $fizz;
     */
    protected static $fizz;

    /**
     * @return null|static[] Some text.
     *
     * expect: final public static null|self[] function B() {
     */
    final public static function B () {
        return null;
    }

    /**
     * @inheritDoc
     * @return $this|null
     *
     * expect: public $this|null function A() {
     */
    public function A (): int {
        return null;
    }

    /**
     * @return string
     *
     * expect: public string function __toString() {
     */
    public function __toString (): string {
        return '';
    }

    /**
     * Some text
     *
     * @internal
     *
     * expect: internal void function _hidden() {
     */
    public function _hidden (): void {
    }

    // expect: no docblock
    // expect: public void function doNothing (/* ::hello */) {
    /**
     * @inheritDoc
     */
    public function doNothing (/* ::hello */): void {
    }
}
