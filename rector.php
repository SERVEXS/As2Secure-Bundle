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
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_82,
//        \Rector\Symfony\Set\SymfonyLevelSetList::UP_TO_SYMFONY_54,
    ]);
};
