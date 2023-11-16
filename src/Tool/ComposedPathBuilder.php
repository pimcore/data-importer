<?php

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Model\Element\Service as ElementService;

class ComposedPathBuilder{

    public static function buildPath(array $inputData, string $path, string $type='object'): string{

        $parts = explode('/', $path);

        foreach($parts as $partIndex => $part){

            $matches = array();

            preg_match_all('/\$((\[[0-9A-Za-z]+\])+)/', $part, $matches);

            if(count($matches) && count($matches[0])){
                foreach($matches[0] as $mIndex => $m){
                    //get keys out of array string
                    $keyString = $matches[1][$mIndex];
                    $keys = explode(',',str_replace(']', '', str_replace('[', '', str_replace('][', ',', $keyString))));
                    $val = $inputData;
                    foreach($keys as $k){
                        $val = $val[$k];
                    }
                    $parts[$partIndex] = str_replace($m, $val, $parts[$partIndex]);
                }
            }

            $parts[$partIndex] = ElementService::getValidKey($parts[$partIndex], $type);
        }

        $path = implode('/',$parts);

        return $path;

    }
}
