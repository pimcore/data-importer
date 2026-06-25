<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;
use Pimcore\Model\Element\ElementInterface;

class DirectDataTargetTest extends Unit
{
    protected $tester;

    public function testEmptyFieldNameThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $target = new Direct();
        $target->setSettings([]);
    }

    public function testPathOnUnsupportedElementTypeThrows(): void
    {
        $target = new Direct();
        $target->setSettings(['fieldName' => 'path']);

        // A generic element that is neither a DataObject, Asset nor Document
        // cannot have its parent resolved from a path.
        $element = $this->createMock(ElementInterface::class);

        $this->expectException(InvalidConfigurationException::class);
        $target->assignData($element, '/some/folder');
    }

    public function testEmptyPathIsSkipped(): void
    {
        $target = new Direct();
        $target->setSettings(['fieldName' => 'path']);

        $element = $this->createMock(ElementInterface::class);

        // No exception expected and no folder resolution: an empty path is a no-op.
        $target->assignData($element, '');
        $this->addToAssertionCount(1);
    }
}
