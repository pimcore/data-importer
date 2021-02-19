<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Controller;

use Ahc\Cron\Expression;
use Cron\CronExpression;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\DataTarget\Direct;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple\Trim;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\Cron\CronExecutionService;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\ResolverFactory;
use Pimcore\Controller\FrontendController;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Car;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Event;
use Pimcore\Model\DataObject\PortalUser;
use Pimcore\Model\Element\Service;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    /**
     * @Route("/pimcore_data_hub_batch_import")
     */
    public function indexAction(Request $request)
    {

        $tags = [
            'Trade Show',
            'Salzburg',
            'Vienna',
            'Wels',
            'Retro',
            'International',
            '1950',
            '1960',
            '1970'
        ];
        $tags = array_flip($tags);

        $cars = new Car\Listing();
        $cars->setCondition('objectType = "actual-car"');
        $carsIdList = $cars->loadIdList();
        $carsIdList = array_flip($carsIdList);

        $assets = new Asset\Listing();
        $assets->setCondition('type = "image"');
        $assetIdList = $assets->loadIdList();
        $assetIdList = array_flip($assetIdList);

        $data[] = [
            'title_de',
            'title_en',
            'description_en',
            'description_de',
            'start',
            'end',
            'tags',
            'location',
            'cars',
            'mainimage',
            'image2',
            'image3',
            'image4',
            'name',
            'phone',
            'email',
        ];

        for($i = 0; $i < 6; $i++) {
            $faker = \Faker\Factory::create();

            $startDate = $faker->dateTimeBetween('-1 year');
            $data[] = [
                'title_de' => $faker->text(60),
                'title_en' => $faker->text(40),
                'description_en' => $faker->realText(300, 4),
                'description_de' => $faker->realText(400, 4),
                'start' => $startDate->format('y-m-d H:i'),
                'end' => $faker->dateTimeBetween($startDate)->format('y-m-d H:i'),
                'tags' => implode(',', array_rand($tags, rand(2,4))),
                'location' => $faker->city,
                'cars' => implode(',', array_rand($carsIdList, rand(3,10))),
                'mainimage' => 'https://via.placeholder.com/400x200/' . $faker->hexColor . '/000000?text=/' . $faker->text(9) . 'jpg',
                'image2' => Asset::getById(array_rand($assetIdList, 1))->getRealFullPath(),
                'image3' => Asset::getById(array_rand($assetIdList, 1))->getRealFullPath(),
                'image4' => Asset::getById(array_rand($assetIdList, 1))->getRealFullPath(),
                'name' => $faker->name,
                'phone' => $faker->phoneNumber,
                'email' => $faker->email,
                'attributes' => [
                    [
                        'key' => '1-6',
                        'value' => $faker->name()
                    ],
                    [
                        'key' => '2-4',
                        'value' => $faker->text(5)
                    ]
                ]
            ];

            \Pimcore::collectGarbage();
        }


//        $fp = fopen(PIMCORE_PROJECT_ROOT . '/import.csv', 'w');
//
//        foreach ($data as $fields) {
//            fputcsv($fp, $fields);
//        }
//
//        fclose($fp);

//        $json = json_encode($data);
//        file_put_contents(PIMCORE_PROJECT_ROOT . '/import.json', $json);

        array_shift($data);
        p_r($data);
        $xml = new \SimpleXMLElement('<root/>');
        // function call to convert array to xml
        $this->array_to_xml($data, $xml, 'item');

//        array_walk_recursive($data, function($item, $key) use ($xml) {
//
//            p_r($key);
//
////            $xml->addChild($key, $item);
//        });
//        p_r($xml->asXML());

//        $dom = new \DOMDocument("1.0");
//        $dom->preserveWhiteSpace = false;
//        $dom->formatOutput = true;
//        $dom->loadXML($xml->asXML());
//        p_r($dom->saveXML());

//        file_put_contents(PIMCORE_PROJECT_ROOT . '/import.xml', $dom->saveXML());


//        $xmlstring = $dom->saveXML();
//
//        $xml = \simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
//        $json = json_encode($xml);
//        $array = json_decode($json,TRUE);
//
//        p_r($array);

        $schema = $this->getSchema();
        $dom = XmlUtils::loadFile(PIMCORE_PROJECT_ROOT . '/import.xml', function($dom) use ($schema) {
            return @$dom->schemaValidateSource($schema);
        });
        $xpath = new \DOMXpath($dom);

        foreach($xpath->evaluate('/root/item') as $item) {
            /**
             * @var $item \DOMElement
             */
//            p_r($item->nodeName);
//            p_r(iterator_to_array($item->childNodes));
            p_r(XmlUtils::convertDomElementToArray($item));
        }
//        var_dump(
//            $xpath->evaluate('/root/item')
//        );
        $list = new \DOMNodeList();
        die("done");

    }


    function array_to_xml( $data, &$xml_data, $firstLevelKey = null ) {
        foreach( $data as $key => $value ) {
            $elementName = $key;
            if($firstLevelKey) {
                $elementName = $firstLevelKey;
            }
            if(is_numeric($elementName)) {
                $elementName = 'item_' . $elementName;
            }

            if( is_array($value) ) {
                $subnode = $xml_data->addChild($elementName);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml_data->addChild($elementName, htmlspecialchars($value));
            }
        }
    }


    function getSchema() {
        return <<<SCHEMA
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="root">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="item" maxOccurs="unbounded" minOccurs="0">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element type="xs:string" name="title_de"/>
                            <xs:element type="xs:string" name="title_en"/>
                            <xs:element type="xs:string" name="description_en"/>
                            <xs:element type="xs:string" name="description_de"/>
                            <xs:element type="xs:string" name="start"/>
                            <xs:element type="xs:string" name="end"/>
                            <xs:element type="xs:string" name="tags"/>
                            <xs:element type="xs:string" name="location"/>
                            <xs:element type="xs:string" name="cars"/>
                            <xs:element type="xs:string" name="mainimage"/>
                            <xs:element type="xs:string" name="image2"/>
                            <xs:element type="xs:string" name="image3"/>
                            <xs:element type="xs:string" name="image4"/>
                            <xs:element type="xs:string" name="name"/>
                            <xs:element type="xs:string" name="phone"/>
                            <xs:element type="xs:string" name="email"/>
                            <xs:element type="xs:string" name="email2" minOccurs="0"/>
                            <xs:any minOccurs="0" maxOccurs="unbounded" processContents="skip" />

                        </xs:sequence>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
SCHEMA;
    }

}
