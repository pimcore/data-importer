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
use Pimcore\Model\DataObject\PortalUser;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    protected $targetFolderPath = '';

    /**
     * @Route("/pimcore_data_hub_batch_import")
     */
    public function indexAction(Request $request, XlsxFileInterpreter $fileInterpreter)
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

        for($i = 0; $i < 100; $i++) {
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
                'email' => $faker->email
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

        $json = json_encode($data);
        file_put_contents(PIMCORE_PROJECT_ROOT . '/import.json', $json);


//        $fileInterpreter->setConfigName('test');
//        $fileInterpreter->setExecutionType('Sequ');
//        $fileInterpreter->setSettings([
//            'skipFirstRow' => true,
//            'sheetName' => 'import'
//        ]);
//        $asset = Asset::getById(412);
//        p_r($fileInterpreter->previewData($asset->getFileSystemPath(), 500));

        die("done");
    }


}
