<?php
namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Carbon\Carbon;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsArray;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsGeobounds;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsGeopoint;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsGeopolygon;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsGeopolyline;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Boolean;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Date;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Gallery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\ImageAdvanced;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\InputQuantityValue;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\InputQuantityValueArray;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Numeric;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValueArray;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Geobounds;
use Pimcore\Model\DataObject\Data\GeoCoordinates;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Tests\Util\TestHelper;

class FactoryOperatorTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
        TestHelper::cleanUp();
    }

    // tests
    public function testAsArray()
    {
        /**
         * @var AsArray $asArray
         */
        $asArray = $this->tester->grabService(AsArray::class);

        $result = $asArray->process('some value');
        $this->assertIsArray($result);

        $result = $asArray->process(['some value']);
        $this->assertIsArray($result);

    }

    public function testBoolean() {

        /**
         * @var Boolean $boolean
         */
        $boolean = $this->tester->grabService(Boolean::class);

        $result = $boolean->process(true);
        $this->assertIsBool($result);
        $this->assertTrue($result);

        $result = $boolean->process([true, false]);
        $this->assertIsBool($result);
        $this->assertTrue($result);

        $result = $boolean->process('false');
        $this->assertIsBool($result);
        $this->assertFalse($result);

        $result = $boolean->process('true');
        $this->assertIsBool($result);
        $this->assertTrue($result);

        $result = $boolean->process('0');
        $this->assertIsBool($result);
        $this->assertFalse($result);

    }

    public function testDate() {

        /**
         * @var Date $date
         */
        $date = $this->tester->grabService(Date::class);

        $date->setSettings([]);

        /**
         * @var Carbon $result
         */
        $result = $date->process('2022-01-05');
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2022, $result->year);
        $this->assertEquals(1, $result->month);
        $this->assertEquals(5, $result->day);
        $preview = $date->generateResultPreview($result);
        $this->assertEquals($result->format('c'), $preview);


        $date->setSettings(['format' => 'Y.m']);
        $result = $date->process('2022.08');
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2022, $result->year);
        $this->assertEquals(8, $result->month);
        $preview = $date->generateResultPreview($result);
        $this->assertEquals($result->format('c'), $preview);


        $dates = ['2022.08', '1950.09', '2250.12'];
        $dateParts = [
            ['year' => 2022, 'month' => 8],
            ['year' => 1950, 'month' => 9],
            ['year' => 2250, 'month' => 12],
        ];
        $resultArray = $date->process($dates);

        $this->assertIsArray($resultArray);
        foreach($resultArray as $index => $result) {
            $this->assertInstanceOf(Carbon::class, $result);
            $this->assertEquals($dateParts[$index]['year'], $result->year);
            $this->assertEquals($dateParts[$index]['month'], $result->month);
        }

        $preview = $date->generateResultPreview($resultArray);
        $this->assertIsArray($preview);

    }

    public function testGallery() {

        /**
         * @var Gallery $gallery
         */
        $gallery = $this->tester->grabService(Gallery::class);

        /**
         * @var ImageGallery $result
         */
        $result = $gallery->process(new Asset());
        $this->assertInstanceOf(ImageGallery::class, $result);
        $this->assertEquals(1, count($result->getItems()));

        $result = $gallery->process([new Asset(), new Asset()]);
        $this->assertInstanceOf(ImageGallery::class, $result);
        $this->assertEquals(2, count($result->getItems()));

        $preview = $gallery->generateResultPreview($result);
        $this->assertIsArray($preview);
        $this->assertStringStartsWith('GalleryImage', $preview[0]);

        $result = $gallery->process('foo');
        $this->assertInstanceOf(ImageGallery::class, $result);
        $this->assertEquals(0, count($result->getItems()));
    }

    public function testImageAdvanced() {

        /**
         * @var ImageAdvanced $imageAdvanced
         */
        $imageAdvanced = $this->tester->grabService(ImageAdvanced::class);


        $result = $imageAdvanced->process([]);
        $this->assertNull($result);

        $asset1 = new Asset\Image();
        $asset1->setKey('asset1');
        $asset2 = new Asset\Image();
        $asset2->setKey('asset2');

        /**
         * @var Hotspotimage $result
         */
        $result = $imageAdvanced->process([$asset1, $asset2]);
        $this->assertInstanceOf(Hotspotimage::class, $result);
        $this->assertEquals('asset1', $result->getImage()->getKey());
        $this->assertNotEquals('asset2', $result->getImage()->getKey());

        $result = $imageAdvanced->process($asset1);
        $this->assertInstanceOf(Hotspotimage::class, $result);
        $this->assertEquals('asset1', $result->getImage()->getKey());

        $preview = $imageAdvanced->generateResultPreview($result);
        $this->assertStringStartsWith('Image Advanced', $preview);

    }

    public function testNumeric() {

        /**
         * @var Numeric $numeric
         */
        $numeric = $this->tester->grabService(Numeric::class);
        $result = $numeric->process('123');
        $this->assertIsNumeric($result);
        $this->assertEquals(123.0, $result);

        $result = $numeric->process(['123.7']);
        $this->assertIsNumeric($result);
        $this->assertEquals(123.7, $result);

    }

    public function testInputQuantityValue() {

        $unit = new Unit();
        $unit->setId('m');
        $unit->setAbbreviation('m');
        $unit->setLongname('Meter');
        $unit->save();

        /**
         * @var InputQuantityValue $inputQuantityValue
         */
        $inputQuantityValue = $this->tester->grabService(InputQuantityValue::class);

        /**
         * @var QuantityValue $result
         */
        $result = $inputQuantityValue->process(['12', 'm']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('m', $result->getUnitId());
        $this->assertEquals('Meter', $result->getUnit()->getLongname());

        $result = $inputQuantityValue->process(['12']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertNull($result->getUnitId());

        $result = $inputQuantityValue->process([null, 'm']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertNull($result->getValue());
        $this->assertEquals('m', $result->getUnitId());

        $preview = $inputQuantityValue->generateResultPreview($result);
        $this->assertStringStartsWith('InputQuantity', $preview);

        $unit->delete();
    }


    public function testQuantityValue() {

        $unit = new Unit();
        $unit->setId('1');
        $unit->setAbbreviation('m');
        $unit->setLongname('Meter');
        $unit->save();

        /**
         * @var \Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValue $quantityValue
         */
        $quantityValue = $this->tester->grabService(\Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValue::class);

        /**
         * @var QuantityValue $result
         */
        $result = $quantityValue->process(['12', '1']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('1', $result->getUnitId());
        $this->assertEquals('Meter', $result->getUnit()->getLongname());

        $preview = $quantityValue->generateResultPreview($result);
        $this->assertStringStartsWith('Quantity', $preview);

        $result = $quantityValue->process(['12']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertNull($result->getUnitId());

        $result = $quantityValue->process([null, '1']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertNull($result->getValue());
        $this->assertEquals('1', $result->getUnitId());

        $result = $quantityValue->process([]);
        $this->assertNull($result);

        $result = $quantityValue->process(['', '']);
        $this->assertNull($result);

        $quantityValue->setSettings(['unitSourceSelect' => 'abbr']);

        $result = $quantityValue->process(['12', 'm']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('1', $result->getUnitId());
        $this->assertEquals('Meter', $result->getUnit()->getLongname());


        $quantityValue->setSettings(['unitSourceSelect' => 'static', 'staticUnitSelect' => '1']);

        $result = $quantityValue->process(['12']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('1', $result->getUnitId());
        $this->assertEquals('Meter', $result->getUnit()->getLongname());

        $result = $quantityValue->process(['12', '2']);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('1', $result->getUnitId());
        $this->assertEquals('Meter', $result->getUnit()->getLongname());


        $result = $quantityValue->process('12');
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('12', $result->getValue());
        $this->assertEquals('1', $result->getUnitId());

        $result = $quantityValue->process([]);
        $this->assertInstanceOf(QuantityValue::class, $result);
        $this->assertEquals('1', $result->getUnitId());


        $quantityValue->setSettings(['unitSourceSelect' => 'abbr', 'unitNullIfNoValueCheckbox' => true]);
        $result = $quantityValue->process([null, 'm']);
        $this->assertNull($result);

        $result = $quantityValue->process([]);
        $this->assertNull($result);


        $quantityValue->setSettings(['unitSourceSelect' => 'static', 'staticUnitSelect' => '1', 'unitNullIfNoValueCheckbox' => true]);
        $result = $quantityValue->process([null, 'm']);
        $this->assertNull($result);

        $result = $quantityValue->process([]);
        $this->assertNull($result);

        $unit->delete();
    }



    public function testQuantityArray() {
        $unit = new Unit();
        $unit->setId('m');
        $unit->setAbbreviation('m');
        $unit->setLongname('Meter');
        $unit->save();


        $operators = [
            $this->tester->grabService(InputQuantityValueArray::class),
            $this->tester->grabService(QuantityValueArray::class),
        ];

        foreach($operators as $operator) {
            /**
             * @var QuantityValue $result
             */
            $result = $operator->process([['12', 'm']]);
            $this->assertInstanceOf(QuantityValue::class, $result[0]);
            $this->assertEquals('12', $result[0]->getValue());
            $this->assertEquals('m', $result[0]->getUnitId());
            $this->assertEquals('Meter', $result[0]->getUnit()->getLongname());

            $result = $operator->process([['12']]);
            $this->assertInstanceOf(QuantityValue::class, $result[0]);
            $this->assertEquals('12', $result[0]->getValue());
            $this->assertNull($result[0]->getUnitId());

            $result = $operator->process([[null, 'm']]);
            $this->assertInstanceOf(QuantityValue::class, $result[0]);
            $this->assertNull($result[0]->getValue());
            $this->assertEquals('m', $result[0]->getUnitId());

            $preview = $operator->generateResultPreview($result);
            $this->assertStringContainsString('Quantity', $preview[0]);

        }

        $unit->delete();
    }

    public function testAsGeopoint() {
        $service = $this->tester->grabService(AsGeopoint::class);
        $data = $service->process(["47.83595982332057", "13.06517167884434"]);
        $this->assertInstanceOf(GeoCoordinates::class, $data);
        $this->assertEquals($data->getLatitude(), "47.83595982332057");
        $this->assertEquals($data->getLongitude(), "13.06517167884434");
    }

    public function testAsGeobounds() {
        $service = $this->tester->grabService(AsGeobounds::class);
        $data = $service->process(["47.83595982332057", "13.06517167884434", "47.810540991091045", "13.073721286556358"]);
        $this->assertInstanceOf(Geobounds::class, $data);

        $northEast = $data->getNorthEast();
        $southWest = $data->getSouthWest();

        $this->assertEquals($northEast->getLatitude(), "47.83595982332057");
        $this->assertEquals($northEast->getLongitude(), "13.06517167884434");

        $this->assertEquals($southWest->getLatitude(), "47.810540991091045");
        $this->assertEquals($southWest->getLongitude(), "13.073721286556358");
    }

    public function testAsGeopolygon() {
        $service = $this->tester->grabService(AsGeopolygon::class);
        $data = $service->process(["47.83595982332057", "13.06517167884434", "47.810540991091045", "13.073721286556358"]);
        $this->assertIsArray($data);

        $this->assertEquals($data[0]->getLatitude(), "47.83595982332057");
        $this->assertEquals($data[0]->getLongitude(), "13.06517167884434");

        $this->assertEquals($data[1]->getLatitude(), "47.810540991091045");
        $this->assertEquals($data[1]->getLongitude(), "13.073721286556358");
    }

    public function testAsGeopolygonArray() {
        $service = $this->tester->grabService(AsGeopolygon::class);
        $data = $service->process([0 => ["47.83595982332057", "13.06517167884434"], 1 => ["47.810540991091045", "13.073721286556358"]]);
        $this->assertIsArray($data);

        $this->assertEquals($data[0]->getLatitude(), "47.83595982332057");
        $this->assertEquals($data[0]->getLongitude(), "13.06517167884434");

        $this->assertEquals($data[1]->getLatitude(), "47.810540991091045");
        $this->assertEquals($data[1]->getLongitude(), "13.073721286556358");
    }

    public function testAsGeopolyline() {
        $service = $this->tester->grabService(AsGeopolyline::class);
        $data = $service->process(["47.83595982332057", "13.06517167884434", "47.810540991091045", "13.073721286556358"]);
        $this->assertIsArray($data);

        $this->assertEquals($data[0]->getLatitude(), "47.83595982332057");
        $this->assertEquals($data[0]->getLongitude(), "13.06517167884434");

        $this->assertEquals($data[1]->getLatitude(), "47.810540991091045");
        $this->assertEquals($data[1]->getLongitude(), "13.073721286556358");
    }

    public function testAsGeopolylineArray() {
        $service = $this->tester->grabService(AsGeopolyline::class);
        $data = $service->process([0 => ["47.83595982332057", "13.06517167884434"], 1 => ["47.810540991091045", "13.073721286556358"]]);
        $this->assertIsArray($data);

        $this->assertEquals($data[0]->getLatitude(), "47.83595982332057");
        $this->assertEquals($data[0]->getLongitude(), "13.06517167884434");

        $this->assertEquals($data[1]->getLatitude(), "47.810540991091045");
        $this->assertEquals($data[1]->getLongitude(), "13.073721286556358");
    }
}
