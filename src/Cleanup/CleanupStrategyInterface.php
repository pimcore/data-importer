<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Cleanup;


use Pimcore\Model\Element\ElementInterface;

interface CleanupStrategyInterface
{

    public function doCleanup(ElementInterface $element): void;

}
