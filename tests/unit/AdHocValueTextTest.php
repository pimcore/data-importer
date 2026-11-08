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
use Pimcore\Model\User;

class AdHocValueTextTest extends Unit
{
    private const string OBJECT_PATH = '/Products/Chair';

    public function testAValueIsOneLineOfText(): void
    {
        $object = $this->createStub(Concrete::class);
        $object->method('getRealFullPath')->willReturn(self::OBJECT_PATH);
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
        $this->assertSame(self::OBJECT_PATH, ValueText::of($object));
        $this->assertSame(self::OBJECT_PATH, ValueText::of($metadata));
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

    public function testAnElementTheViewerMayNotSeeIsLeftOut(): void
    {
        $viewer = new User();
        $hidden = $this->createStub(Concrete::class);
        $hidden->method('isAllowed')->willReturn(false);
        $hidden->method('getRealFullPath')->willReturn('/restricted/Chair');
        $visible = $this->createStub(Concrete::class);
        $visible->method('isAllowed')->willReturn(true);
        $visible->method('getRealFullPath')->willReturn(self::OBJECT_PATH);
        $hiddenImage = $this->createStub(Image::class);
        $hiddenImage->method('isAllowed')->willReturn(false);
        $hiddenImage->method('getRealFullPath')->willReturn('/restricted/chair.jpg');
        $metadata = $this->createStub(ObjectMetadata::class);
        $metadata->method('getObject')->willReturn($hidden);
        $link = $this->createStub(Link::class);
        $link->method('getElement')->willReturn($hidden);
        $link->method('getHref')->willReturn('/restricted/Chair');

        $this->assertNull(ValueText::of($hidden, $viewer));
        $this->assertSame(self::OBJECT_PATH, ValueText::of($visible, $viewer));
        $this->assertSame(self::OBJECT_PATH, ValueText::of([$hidden, $visible], $viewer), 'the others of a list stay');
        $this->assertNull(ValueText::of($metadata, $viewer));
        $this->assertNull(ValueText::of(new Hotspotimage($hiddenImage), $viewer));
        $this->assertNull(ValueText::of(new ImageGallery([new Hotspotimage($hiddenImage)]), $viewer));
        $this->assertNull(ValueText::of($link, $viewer));
        $this->assertSame('/restricted/Chair', ValueText::of($hidden), 'without a viewer nothing is left out');
    }

    public function testADateTimeKeepsItsTime(): void
    {
        $this->assertSame('2026-11-07 10:00:00', ValueText::ofDateTime(Carbon::parse('2026-11-07 10:00')));
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
