<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Composer\InstalledVersions;
use Nette\Forms\Container;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\Form;
use Nette\SmartObject;

/**
 * Control group: a plain class with Nette\SmartObject and nothing else.
 *
 * @method void onFoo(string $value)
 */
class PlainObject
{
    use SmartObject;

    /** @var array<callable(string): void> */
    public array $onFoo = [];
}

/**
 * @method void onFoo(string $value)
 */
class MyContainer extends Container
{
    /** @var array<callable(string): void> */
    public array $onFoo = [];
}

/**
 * @method void onFoo(string $value)
 */
class MyForm extends Form
{
    /** @var array<callable(string): void> */
    public array $onFoo = [];
}

/**
 * @method void onFoo(string $value)
 */
class MyInput extends TextInput
{
    /** @var array<callable(string): void> */
    public array $onFoo = [];
}

function check(object $object): void
{
    $called = false;
    $object->onFoo[] = static function (string $value) use (&$called): void {
        $called = $value === 'bar';
    };

    try {
        $object->onFoo('bar');
        $result = $called ? 'OK      handler was called' : 'FAIL    no exception, but the handler was not called';
    } catch (Throwable $exception) {
        $result = 'FAIL    ' . $exception::class . ': ' . $exception->getMessage();
    }

    printf("%-12s %s\n", $object::class, $result);
}

foreach (['nette/forms', 'nette/component-model', 'nette/utils'] as $package) {
    printf("%-22s %s\n", $package, InstalledVersions::getPrettyVersion($package));
}
printf("%-22s %s\n\n", 'PHP', PHP_VERSION);

check(new PlainObject());
check(new MyContainer());
check(new MyForm());
check(new MyInput());
