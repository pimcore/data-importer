<?php
namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\StaticText;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\StringReplace;
use Pimcore\Tests\Util\TestHelper;

class SimpleOperatorTest extends \Codeception\Test\Unit
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


    public function testStringReplaceProcessFunction() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'Test', 'replace' => 'Result']);
        $data = $service->process("Hello Test");

        $this->assertEquals($data, "Hello Result");
    }

    public function testStringReplaceProcessFunctionWithArray() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'Test', 'replace' => 'Result']);
        $data = $service->process(["Hello Test", "Test Array", "*Test*"]);

        $this->assertEquals($data[0], "Hello Result");
        $this->assertEquals($data[1], "Result Array");
        $this->assertEquals($data[2], "*Result*");
    }

    public function testStringReplaceEvaluateReturnTypeFunctionWithWrongInputType() {
        $this->expectException(InvalidConfigurationException::class);
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'Test', 'replace' => 'Result']);
        $service->evaluateReturnType("boolean");
    }

    public function testStringReplaceProcessFunctionWithZero() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'ObjectKey ', 'replace' => '']);
        $data = $service->process("ObjectKey 0");

        $this->assertEquals($data, "0");
    }

    public function testStringReplaceProcessFunctionWithZeroInArray() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'Test', 'replace' => 'Result']);
        $data = $service->process(["Test", "Test 0", "0"]);

        $this->assertEquals($data[0], "Result");
        $this->assertEquals($data[1], "Result 0");
        $this->assertEquals($data[2], "0");
    }

    public function testStringReplaceProcessFunctionWithEmpty() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'ObjectKey', 'replace' => '']);
        $data = $service->process("ObjectKey");

        $this->assertEquals($data, '');
    }

    public function testStringReplaceProcessFunctionWithEmptyInArray() {
        $service = $this->tester->grabService(StringReplace::class);
        $service->setSettings(['search' => 'Test', 'replace' => '']);
        $data = $service->process(["Hello Test", "", "Test"]);

        $this->assertEquals($data[0], "Hello ");
        $this->assertEquals($data[1], "");
        $this->assertEquals($data[2], "");
    }

    public function testStaticTextProcessFunctionWithZero() {
        $service = $this->tester->grabService(StaticText::class);
        $service->setSettings(['mode' => StaticText::MODE_APPEND, 'text' => '0', 'alwaysAdd' => false]);
        $data = $service->process("Test");

        $this->assertEquals("Test0", $data);
    }
    public function testStaticTextProcessFunctionWithZeroAndAlwaysAdd() {
        $service = $this->tester->grabService(StaticText::class);
        $service->setSettings(['mode' => StaticText::MODE_APPEND, 'text' => '0', 'alwaysAdd' => true]);
        $data = $service->process("");

        $this->assertEquals("0", $data);
    }
}
