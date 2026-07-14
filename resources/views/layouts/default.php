<?php // Register layout assets on the stack — emitted below by renderCss() / renderJs().?>
<?php $this->css('/assets/css/app.css'); ?>
<?php $this->js('/assets/js/app.js'); ?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->esc($title ?? 'AppKit'); ?></title>
    <?= $this->renderCss(); ?>
</head>
<body class="h-full antialiased text-gray-900">
    <?= $content; ?>
    <?= $this->renderJs(); ?>
</body>
</html>
