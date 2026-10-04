<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Composer\InstalledVersions;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Form;
use Tracy\Debugger;

Debugger::enable(Debugger::Development);

/**
 * A form that declares its own event and fires it the SmartObject way.
 *
 * @method void onCancel(self $form)
 */
class CancellableForm extends Form
{
    /** @var array<callable(self): void> */
    public array $onCancel = [];

    public function __construct()
    {
        parent::__construct();
        // Browsers send Sec-Fetch-Site only to trustworthy origins (https or localhost), so over
        // plain http on any other host nette/forms 3.3 would not treat the form as submitted.
        $this->allowCrossOrigin();
        $this->addText('name', 'Name:');
        $this->addSubmit('send', 'Send');
        $this->addSubmit('cancel', 'Cancel')
            ->setValidationScope([])
            ->onClick[] = function (SubmitButton $button): void {
                $this->onCancel($this);
            };
    }
}

$messages = [];
$form = new CancellableForm();
$form->onCancel[] = static function () use (&$messages): void {
    $messages[] = 'onCancel handler was called.';
};
$form->onSuccess[] = static function (Form $form) use (&$messages): void {
    if ($form->isSubmitted() === $form['send']) {
        $messages[] = 'onSuccess handler was called (built-in event, fired by Nette itself).';
    }
};

$form->fireEvents();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>nette/forms event call reproduction</title>
    <style>
        body { font-family: sans-serif; max-width: 50rem; margin: 2rem auto; padding: 0 1rem; }
        .ok { background: #dfd; border: 1px solid #5a5; padding: .75rem; }
        .fail { background: #fdd; border: 1px solid #a55; padding: .75rem; }
        table { border-collapse: collapse; margin: 1rem 0; }
        td { padding: .15rem 1rem .15rem 0; }
        code { background: #eee; padding: 0 .25rem; }
    </style>
</head>
<body>
    <h1>nette/forms: <code>$this->onCancel($this)</code></h1>

    <table>
        <?php foreach (['nette/forms', 'nette/component-model', 'nette/utils'] as $package) { ?>
            <tr><td><?= $package ?></td><td><?= htmlspecialchars((string) InstalledVersions::getPrettyVersion($package)) ?></td></tr>
        <?php } ?>
        <tr><td>PHP</td><td><?= PHP_VERSION ?></td></tr>
    </table>

    <p>
        The form declares <code>public array $onCancel = []</code> and the Cancel button fires it
        as <code>$this->onCancel($this)</code>. Click <strong>Cancel</strong>.
    </p>

    <?php foreach ($messages as $message) { ?>
        <p class="ok"><?= htmlspecialchars($message) ?></p>
    <?php } ?>

    <?php $form->render(); ?>
</body>
</html>
