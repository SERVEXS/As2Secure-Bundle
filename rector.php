<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/Tests',
    ]);
    $rectorConfig->sets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_74,
//        \Rector\Symfony\Set\SymfonySetList::SYMFONY_40,
//        \Rector\Symfony\Set\SymfonySetList::SYMFONY_41,
//        \Rector\Symfony\Set\SymfonySetList::SYMFONY_42,
//        \Rector\Symfony\Set\SymfonySetList::SYMFONY_43,
//        \Rector\Symfony\Set\SymfonySetList::SYMFONY_44,
    ]);
};
