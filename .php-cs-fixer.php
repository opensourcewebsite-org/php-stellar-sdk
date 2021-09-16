<?php

# https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/usage.rst
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        'src',
        'tests',
    ])
;

/*
 * Available rules: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/rules/index.rst
 * Available sets: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/ruleSets/index.rst
 */

$config = new PhpCsFixer\Config();

return $config
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true, // PSR12 coding style: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/ruleSets/PSR12.rst
        '@PSR12:risky' => true, // PSR12Risky coding style: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/ruleSets/PSR12Risky.rst
        '@DoctrineAnnotation' => true, // Format Doctrine annotations: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/ruleSets/DoctrineAnnotation.rst
    ]);
