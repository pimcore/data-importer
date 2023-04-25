<?php

if(\Pimcore\Version::getMajorVersion() >= 11) {
    return [
        \Pimcore\Bundle\AdminBundle\PimcoreAdminBundle::class => ['all' => true],
        \Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle::class => ['all' => true]
    ];
}

return [
    \Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle::class => ['all' => true]
];
