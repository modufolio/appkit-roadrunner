<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->in(__DIR__.'/config')
    ->in(__DIR__.'/database')
    ->in(__DIR__.'/public')
    ->in(__DIR__.'/resources')
    ->append([__FILE__, __DIR__.'/bootstrap.php', __DIR__.'/router.php', __DIR__.'/bin/console']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'echo_tag_syntax' => ['format' => 'short'],
        'no_alternative_syntax' => false,
    ])
    ->setFinder($finder);
