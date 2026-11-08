<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Bundle\DataImporterBundle\Mapping\WritesElementsInterface;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Model\Element\Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Refuses to save or delete elements while a mapping is applied without saving, for operators and data targets that
 * write elements without implementing WritesElementsInterface.
 *
 * @internal
 */
final class ElementWriteGuard implements EventSubscriberInterface
{
    public function __construct(
        private readonly MappingApplicationScope $scope,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach ([DataObjectEvents::class, AssetEvents::class, DocumentEvents::class] as $class) {
            foreach (['PRE_ADD', 'PRE_UPDATE', 'PRE_DELETE'] as $name) {
                // first, so no other listener acts on a write that is refused
                $events[constant($class . '::' . $name)] = ['refuseWrite', 2048];
            }
        }

        return $events;
    }

    public function refuseWrite(ElementEventInterface $event): void
    {
        if (!$this->scope->isActive()) {
            return;
        }

        $element = $event->getElement();

        throw new \LogicException(sprintf(
            '%s `%s` cannot be saved or deleted while a mapping is applied without saving. The operator or data '
            . 'target that writes it has to implement %s.',
            ucfirst(Service::getElementType($element) ?? 'element'),
            $element->getFullPath(),
            WritesElementsInterface::class
        ));
    }
}
