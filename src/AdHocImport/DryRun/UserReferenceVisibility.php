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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceVisibilityInterface;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * Keeps a configuration's load operators to the elements the user may view; the operators load them as they do.
 *
 * @internal
 */
final readonly class UserReferenceVisibility implements ReferenceVisibilityInterface
{
    public function __construct(
        private User $user,
    ) {
    }

    public function find(ReferenceQuery $query): ?ElementInterface
    {
        return null;
    }

    public function isVisible(ElementInterface $element): bool
    {
        return $element->isAllowed('view', $this->user);
    }

    /**
     * The value without the elements the user may not view: such an element counts as not found.
     */
    public function visibleIn(mixed $value): mixed
    {
        if ($value instanceof ElementInterface) {
            return $this->isVisible($value) ? $value : null;
        }
        if ($value instanceof ImageGallery) {
            return new ImageGallery(array_values(array_filter(
                $value->getItems(),
                fn (?Hotspotimage $item): bool => $item?->getImage() !== null && $this->isVisible($item->getImage())
            )));
        }
        if (is_array($value)) {
            $visible = [];
            foreach ($value as $key => $item) {
                $item = $this->visibleIn($item);
                if ($item !== null) {
                    $visible[$key] = $item;
                }
            }

            return array_is_list($value) ? array_values($visible) : $visible;
        }

        return $value;
    }
}
