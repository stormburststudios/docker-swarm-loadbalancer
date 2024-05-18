<?php

declare(strict_types=1);

use PhpStaticAnalysis\RectorRule\AnnotationsToAttributesRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\AnnotationWithValueToAttributeRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Removing\Rector\FuncCall\RemoveFuncCallRector;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

$rectorConfig = RectorConfig::configure();
$rectorConfig->withParallel(30);
$rectorConfig->withPreparedSets(
    deadCode: true,
    codeQuality: true
);
$rectorConfig->withPaths([
    __DIR__ . '/bin',
    __DIR__ . '/src',
    __DIR__ . '/tests',
]);
// uncomment to reach your current PHP version
$rectorConfig->withPhpSets();
$rectorConfig->withSets([
    PHPUnitSetList::PHPUNIT_80,
    PHPUnitSetList::PHPUNIT_90,
    PHPUnitSetList::PHPUNIT_100,
    PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    // PhpStaticAnalysisSetList::ANNOTATIONS_TO_ATTRIBUTES,// Implied by PHPUNIT_100
    DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
    __DIR__ . '/vendor/fakerphp/faker/rector-migrate.php',
]);
$rectorConfig->withConfiguredRule(RemoveFuncCallRector::class, [
    'var_dump',
]);
$rectorConfig->withRules([
    AddVoidReturnTypeWhereNoReturnRector::class,
    AnnotationToAttributeRector::class,
    AnnotationWithValueToAttributeRector::class,
    AnnotationsToAttributesRector::class,
]);

// Prefer self::assert* over $this->assert* in PHPUnit tests
$rectorConfig->withSkip([
    PreferPHPUnitThisCallRector::class,
]);
$rectorConfig->withRules([
    PreferPHPUnitSelfCallRector::class,
]);

return $rectorConfig;
