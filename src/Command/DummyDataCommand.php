<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Car;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DummyDataCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('datahub:data-importer:create-dummy-data')
            ->setDescription("Creates a dummy-data file for test imports. File is located will be created in private tmp directory of Pimcore. Don't forget to run 'composer require fzaninotto/faker' in advance.")
            ->addOption('items', 'i', InputOption::VALUE_OPTIONAL, 'Number of data items in file', 100)
            ->addOption('targetType', 't', InputOption::VALUE_OPTIONAL, 'Target filetype, one of csv, xml, json', 'csv')
            ->addOption('targetFilename', 'f', InputOption::VALUE_OPTIONAL, 'Target filename, File is located will be created in private tmp directory of Pimcore.', 'export.csv')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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

        $count = (int) $input->getOption('items');

        for ($i = 0; $i < $count; $i++) {
            $output->writeln('Generating item ' . $i . ' ...');

            $faker = \Faker\Factory::create();

            $startDate = $faker->dateTimeBetween('-1 year');
            $data[] = [
                'title_de' => $faker->text(60),
                'title_en' => $faker->text(40),
                'description_en' => $faker->realText(300, 4),
                'description_de' => $faker->realText(400, 4),
                'start' => $startDate->format('y-m-d H:i'),
                'end' => $faker->dateTimeBetween($startDate)->format('y-m-d H:i'),
                'tags' => implode(',', array_rand($tags, rand(2, 4))),
                'location' => $faker->city,
                'cars' => implode(',', array_rand($carsIdList, rand(3, 10))),
                'mainimage' => 'https://via.placeholder.com/400x200/' . substr($faker->hexColor, 1) . '/000000?text=' . $faker->text(9) . 'jpg',
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

            if ($i % 100 === 0) {
                \Pimcore::collectGarbage();
            }
        }

        $format = $input->getOption('targetType');

        $directory = PIMCORE_PRIVATE_VAR . '/tmp/datahub-dummyfiles';
        File::mkdir($directory);
        $filename = $directory . '/' . $input->getOption('targetFilename');

        $output->writeln('Writing file to ' . $filename);

        switch ($format) {
            case 'csv':
                $this->writeCsv($filename, $data);
                break;
            case 'json':
                $this->writeJson($filename, $data);
                break;
            case 'xml':
                $this->writeXml($filename, $data);
                break;
            default:
                throw new \Exception('Invalid format: ' . $format);
        }

        return 0;
    }

    protected function writeCsv(string $filename, array $data)
    {
        $fp = fopen($filename, 'w');

        foreach ($data as $fields) {
            foreach ($fields as &$field) {
                if (is_array($field)) {
                    $flattenedField = [];
                    array_walk_recursive($field, function ($item) use (&$flattenedField) {
                        $flattenedField[] = $item;
                    });
                    $field = implode('||', $flattenedField);
                }
            }

            fputcsv($fp, $fields);
        }

        fclose($fp);
    }

    protected function writeXml(string $filename, array $data)
    {
        array_shift($data);

        $xml = new \SimpleXMLElement('<root/>');
        $this->arrayToXml($data, $xml, 'item');

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        file_put_contents($filename, $dom->saveXML());
    }

    public function arrayToXml($data, &$xml_data, $firstLevelKey = null)
    {
        foreach ($data as $key => $value) {
            $elementName = $key;
            if ($firstLevelKey) {
                $elementName = $firstLevelKey;
            }
            if (is_numeric($elementName)) {
                $elementName = 'item_' . $elementName;
            }

            if (is_array($value)) {
                $subnode = $xml_data->addChild($elementName);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml_data->addChild($elementName, htmlspecialchars($value));
            }
        }
    }

    protected function writeJson(string $filename, array $data)
    {
        array_shift($data);
        $json = json_encode($data);
        file_put_contents($filename, $json);
    }
}
