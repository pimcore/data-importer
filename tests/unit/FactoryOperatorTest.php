<?php
namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsArray;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Boolean;
use Pimcore\Log\ApplicationLogger;
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
//        $asArray = new AsArray(new ApplicationLogger());

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
//        $boolean = new Boolean(new ApplicationLogger());

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
}
