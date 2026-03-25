/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/**
 * Service IDs for the Data Importer Bundle
 * Centralized location for all dependency injection service identifiers
 */
export const bundleServiceIds = {
  'DataImporter/DynamicTypes/Adapter/DataImporterDataObject': 'DataImporter/DynamicTypes/Adapter/DataImporterDataObject',

  // Transformer registry
  'DataImporter/DynamicTypes/Transformer/Registry': 'DataImporter/DynamicTypes/Transformer/Registry',

  // Transformer types — with settings
  'DataImporter/DynamicTypes/Transformer/Trim': 'DataImporter/DynamicTypes/Transformer/Trim',
  'DataImporter/DynamicTypes/Transformer/Combine': 'DataImporter/DynamicTypes/Transformer/Combine',
  'DataImporter/DynamicTypes/Transformer/StaticText': 'DataImporter/DynamicTypes/Transformer/StaticText',
  'DataImporter/DynamicTypes/Transformer/StringReplace': 'DataImporter/DynamicTypes/Transformer/StringReplace',
  'DataImporter/DynamicTypes/Transformer/Date': 'DataImporter/DynamicTypes/Transformer/Date',
  'DataImporter/DynamicTypes/Transformer/Numeric': 'DataImporter/DynamicTypes/Transformer/Numeric',
  'DataImporter/DynamicTypes/Transformer/Explode': 'DataImporter/DynamicTypes/Transformer/Explode',
  'DataImporter/DynamicTypes/Transformer/ConditionalConversion': 'DataImporter/DynamicTypes/Transformer/ConditionalConversion',
  'DataImporter/DynamicTypes/Transformer/ObjectField': 'DataImporter/DynamicTypes/Transformer/ObjectField',
  'DataImporter/DynamicTypes/Transformer/LoadAsset': 'DataImporter/DynamicTypes/Transformer/LoadAsset',

  // Transformer types — no settings
  'DataImporter/DynamicTypes/Transformer/FlattenArray': 'DataImporter/DynamicTypes/Transformer/FlattenArray',
  'DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs': 'DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs',
  'DataImporter/DynamicTypes/Transformer/HtmlDecode': 'DataImporter/DynamicTypes/Transformer/HtmlDecode',
  'DataImporter/DynamicTypes/Transformer/Boolean': 'DataImporter/DynamicTypes/Transformer/Boolean',
  'DataImporter/DynamicTypes/Transformer/AsArray': 'DataImporter/DynamicTypes/Transformer/AsArray',
  'DataImporter/DynamicTypes/Transformer/AsColor': 'DataImporter/DynamicTypes/Transformer/AsColor',
  'DataImporter/DynamicTypes/Transformer/AsCountries': 'DataImporter/DynamicTypes/Transformer/AsCountries',
  'DataImporter/DynamicTypes/Transformer/Gallery': 'DataImporter/DynamicTypes/Transformer/Gallery',
  'DataImporter/DynamicTypes/Transformer/ImageAdvanced': 'DataImporter/DynamicTypes/Transformer/ImageAdvanced',
  'DataImporter/DynamicTypes/Transformer/QuantityValue': 'DataImporter/DynamicTypes/Transformer/QuantityValue',
  'DataImporter/DynamicTypes/Transformer/QuantityValueArray': 'DataImporter/DynamicTypes/Transformer/QuantityValueArray',
  'DataImporter/DynamicTypes/Transformer/InputQuantityValue': 'DataImporter/DynamicTypes/Transformer/InputQuantityValue',
  'DataImporter/DynamicTypes/Transformer/InputQuantityValueArray': 'DataImporter/DynamicTypes/Transformer/InputQuantityValueArray',
  'DataImporter/DynamicTypes/Transformer/AsGeobounds': 'DataImporter/DynamicTypes/Transformer/AsGeobounds',
  'DataImporter/DynamicTypes/Transformer/AsGeopoint': 'DataImporter/DynamicTypes/Transformer/AsGeopoint',
  'DataImporter/DynamicTypes/Transformer/AsGeopolygon': 'DataImporter/DynamicTypes/Transformer/AsGeopolygon',
  'DataImporter/DynamicTypes/Transformer/AsGeopolyline': 'DataImporter/DynamicTypes/Transformer/AsGeopolyline',
  'DataImporter/DynamicTypes/Transformer/LoadDataObject': 'DataImporter/DynamicTypes/Transformer/LoadDataObject',
  'DataImporter/DynamicTypes/Transformer/ImportAsset': 'DataImporter/DynamicTypes/Transformer/ImportAsset'
} as const
