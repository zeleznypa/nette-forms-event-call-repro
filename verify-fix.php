<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Composer\InstalledVersions;
use Nette\Forms\Container;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\Form;
use Nette\MemberAccessException;

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

function report(string $label, callable $test): void
{
    try {
        $result = $test() === true ? 'OK' : 'FAIL';
    } catch (Throwable $exception) {
        $result = 'FAIL    ' . $exception::class . ': ' . $exception->getMessage();
    }
    printf("%-58s %s\n", $label, $result);
}

function throwsMemberAccess(callable $call): bool
{
    try {
        $call();
    } catch (MemberAccessException) {
        return true;
    }
    return false;
}

foreach (['nette/forms', 'nette/component-model', 'nette/utils'] as $package) {
    printf("%-22s %s\n", $package, InstalledVersions::getPrettyVersion($package));
}
echo "\n";

Container::extensionMethod('addGreeting', static fn(Container $container, string $name): string => 'hello ' . $name);
TextInput::extensionMethod('shout', static fn(TextInput $input, string $text): string => strtoupper($text));

foreach ([MyContainer::class, MyForm::class, MyInput::class] as $class) {
    report($class . ': event handlers are called in order', static function () use ($class): bool {
        $object = new $class();
        $log = [];
        $object->onFoo[] = static function (string $value) use (&$log): void {
            $log[] = 'first:' . $value;
        };
        $object->onFoo[] = static function (string $value) use (&$log): void {
            $log[] = 'second:' . $value;
        };
        $object->onFoo('bar');
        return $log === ['first:bar', 'second:bar'];
    });
    report($class . ': event without handlers is a no-op', static function () use ($class): bool {
        $object = new $class();
        return $object->onFoo('bar') === null;
    });
    report($class . ': undefined method still throws', static function () use ($class): bool {
        $object = new $class();
        return throwsMemberAccess(static fn() => $object->undefinedMethod());
    });
}

report('Container: extension method still works', static fn(): bool => (new MyContainer())->addGreeting('world') === 'hello world');
report('Form: extension method still works', static fn(): bool => (new MyForm())->addGreeting('world') === 'hello world');
report('TextInput: extension method still works', static fn(): bool => (new MyInput())->shout('hi') === 'HI');
report('Nette built-in events still fire (Form::onRender)', static function (): bool {
    $form = new MyForm();
    $called = false;
    $form->onRender[] = static function () use (&$called): void {
        $called = true;
    };
    $form->fireRenderEvents();
    return $called;
});
