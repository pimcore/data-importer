<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Cleanup;


use Pimcore\Model\Element\ElementInterface;

class UnpublishStrategy implements CleanupStrategyInterface
{

    public function doCleanup(ElementInterface $element = null): void
    {
        if($element && method_exists($element, 'setPublished')) {
            $element->setPublished(false);
            $element->save();
        }
    }
}
