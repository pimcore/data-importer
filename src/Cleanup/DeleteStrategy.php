<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Cleanup;


use Pimcore\Model\Element\ElementInterface;

class DeleteStrategy implements CleanupStrategyInterface
{

    public function doCleanup(ElementInterface $element = null): void
    {
        if($element && method_exists($element, 'delete')) {
            $element->delete();
        }
    }

}
