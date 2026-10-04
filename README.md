# nette/forms 3.3.0: `$this->onFoo()` no longer calls event handlers #

Minimal reproduction, plain Nette only (`nette/forms` and its dependencies).

## What happens ##

A class extending `Nette\Forms\Container`, `Nette\Forms\Form` or any
`Nette\Forms\Controls\BaseControl` descendant declares an event
(`public array $onFoo = []`) and fires it the documented `SmartObject` way,
`$this->onFoo(...)`.

- **nette/forms 3.2.8** – the handlers are called.
- **nette/forms 3.3.0** – `Nette\MemberAccessException: Call to undefined method …::onFoo()`.

The same declaration on a plain class with `use Nette\SmartObject` works in both.

## Run it ##

```bash
composer update --with nette/forms:3.2.8
php repro.php

composer update --with nette/forms:3.3.0
php repro.php

composer update --with nette/forms:3.3.0 --with nette/component-model:3.2.0
php repro.php
```

## Output ##

```
nette/forms            v3.2.8
nette/component-model  v3.2.0
nette/utils            v4.1.5
PHP                    8.5.10

PlainObject  OK      handler was called
MyContainer  OK      handler was called
MyForm       OK      handler was called
MyInput      OK      handler was called
```

```
nette/forms            v3.3.0
nette/component-model  v4.0.1
nette/utils            v4.1.5
PHP                    8.5.10

PlainObject  OK      handler was called
MyContainer  FAIL    Nette\MemberAccessException: Call to undefined method MyContainer::onFoo().
MyForm       FAIL    Nette\MemberAccessException: Call to undefined method MyForm::onFoo().
MyInput      FAIL    Nette\MemberAccessException: Call to undefined method MyInput::onFoo().
```

```
nette/forms            v3.3.0
nette/component-model  v3.2.0
nette/utils            v4.1.5
PHP                    8.5.10

PlainObject  OK      handler was called
MyContainer  FAIL    Nette\MemberAccessException: Call to undefined method MyContainer::onFoo().
MyForm       FAIL    Nette\MemberAccessException: Call to undefined method MyForm::onFoo().
MyInput      FAIL    Nette\MemberAccessException: Call to undefined method MyInput::onFoo().
```

## In the browser ##

`www/index.php` is the same thing as a real form: `CancellableForm` declares
`public array $onCancel = []` and its Cancel button fires `$this->onCancel($this)`.
Tracy is enabled in development mode, so the exception is shown as is.

```bash
composer update --with nette/forms:3.3.0
php -S localhost:8000 -t www
```

Open <http://localhost:8000/> and click **Cancel**:

- **nette/forms 3.3.0** – Tracy reports
  `Nette\MemberAccessException: Call to undefined method CancellableForm::onCancel().`
- **Send** works in both versions – `onSuccess` is fired by Nette itself through
  `Arrays::invoke()`, which does not go through `__call()`.

## Where it comes from ##

Commit `27f9490` "compatibility with nette/component-model 4", first released in
v3.3.0, changed the tail of `Container::__call()` and `BaseControl::__call()`:

```diff
+	use Nette\SmartObject;
 …
-		return parent::__call($name, $args);
+		Nette\Utils\ObjectHelpers::strictCall(static::class, $name);
```

Until then the call fell through to `SmartObject::__call()` inherited from
`Nette\ComponentModel\Component`, which handles events. `Component` no longer
uses `SmartObject` in component-model 4, so the trait was added to `Container`
and `BaseControl` directly. A method declared in the class takes precedence
over the one from a trait, though, so the class's own `__call()` now shadows
`SmartObject::__call()` and the event branch is never reached.

The change is not listed among the breaking changes in the v3.3.0 release notes.

## Proposed fix ##

Keep the trait's implementation reachable and delegate to it, in both
`Container` and `BaseControl`:

```php
use Nette\SmartObject {
    __call as private smartObjectCall;
}

public function __call(string $name, array $args)
{
    if (isset(self::$extMethods[$name])) {
        return (self::$extMethods[$name])($this, ...$args);
    }

    return $this->smartObjectCall($name, $args);
}
```

The complete change is in [`fix.patch`](fix.patch), generated against the
`v3.3.0` tag. To try it:

```bash
composer update --with nette/forms:3.3.0
patch -p1 -d vendor/nette/forms -i "$PWD/fix.patch"
php repro.php
php verify-fix.php
```

With the patch applied, `repro.php` reports `OK` for all four classes and
`verify-fix.php` confirms that nothing else changed. Same result with
nette/component-model 3.2.0 and 4.0.1:

```
nette/forms            v3.3.0
nette/component-model  v4.0.1
nette/utils            v4.1.5

MyContainer: event handlers are called in order            OK
MyContainer: event without handlers is a no-op             OK
MyContainer: undefined method still throws                 OK
MyForm: event handlers are called in order                 OK
MyForm: event without handlers is a no-op                  OK
MyForm: undefined method still throws                      OK
MyInput: event handlers are called in order                OK
MyInput: event without handlers is a no-op                 OK
MyInput: undefined method still throws                     OK
Container: extension method still works                    OK
Form: extension method still works                         OK
TextInput: extension method still works                    OK
Nette built-in events still fire (Form::onRender)          OK
```

The nette/forms test suite has not been run against the patch.
