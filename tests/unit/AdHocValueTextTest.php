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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ValueText;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\DataObject\Data\ObjectMetadata;

class AdHocValueTextTest extends Unit
{
    public function testAValueIsOneLineOfText(): void
    {
        $object = $this->createStub(Concrete::class);
        $object->method('getRealFullPath')->willReturn('/Products/Chair');
        $image = $this->createStub(Image::class);
        $image->method('getRealFullPath')->willReturn('/images/chair.jpg');
        $metadata = $this->createStub(ObjectMetadata::class);
        $metadata->method('getObject')->willReturn($object);
        $link = new Link();
        $link->setDirect('https://example.org/chair');
        $stringable = new class() implements \Stringable {
            public function __toString(): string
            {
                return '3 kg';
            }
        };

        $this->assertNull(ValueText::of(null));
        $this->assertNull(ValueText::of(''));
        $this->assertNull(ValueText::of([]));
        $this->assertSame('0', ValueText::of(0));
        $this->assertSame('12.5', ValueText::of(12.5));
        $this->assertSame('true', ValueText::of(true));
        $this->assertSame('false', ValueText::of(false));
        $this->assertSame('/Products/Chair', ValueText::of($object));
        $this->assertSame('/Products/Chair', ValueText::of($metadata));
        $this->assertSame('2026-11-07', ValueText::of(Carbon::parse('2026-11-07 10:00')));
        $this->assertSame('red, blue', ValueText::of(['red', null, '', 'blue']));
        $this->assertSame('/images/chair.jpg', ValueText::of(new Hotspotimage($image)));
        $this->assertSame(
            '/images/chair.jpg, /images/chair.jpg',
            ValueText::of(new ImageGallery([new Hotspotimage($image), null, new Hotspotimage($image)]))
        );
        $this->assertSame('https://example.org/chair', ValueText::of($link));
        $this->assertSame('3 kg', ValueText::of($stringable));
        $this->assertSame('stdClass', ValueText::of(new \stdClass()));
    }

    public function testAConversionTellsWhatTheValueIsNot(): void
    {
        $this->assertSame(ValueError::NotANumber, ValueError::ofConversion('numeric'));
        $this->assertSame(ValueError::NotADate, ValueError::ofConversion('date'));
        $this->assertSame(ValueError::NotYesOrNo, ValueError::ofConversion('boolean'));
        $this->assertSame(ValueError::Invalid, ValueError::ofConversion('asArray'));
        $this->assertSame(ValueError::Invalid, ValueError::ofConversion(null));
    }
}
