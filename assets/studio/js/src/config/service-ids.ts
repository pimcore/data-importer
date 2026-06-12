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

  // Interpreter registry
  'DataImporter/DynamicTypes/Interpreter/Registry': 'DataImporter/DynamicTypes/Interpreter/Registry',

  // Interpreter types
  'DataImporter/DynamicTypes/Interpreter/Csv': 'DataImporter/DynamicTypes/Interpreter/Csv',
  'DataImporter/DynamicTypes/Interpreter/Json': 'DataImporter/DynamicTypes/Interpreter/Json',
  'DataImporter/DynamicTypes/Interpreter/Sql': 'DataImporter/DynamicTypes/Interpreter/Sql',
  'DataImporter/DynamicTypes/Interpreter/Xlsx': 'DataImporter/DynamicTypes/Interpreter/Xlsx',
  'DataImporter/DynamicTypes/Interpreter/Xml': 'DataImporter/DynamicTypes/Interpreter/Xml',

  // Loader registry
  'DataImporter/DynamicTypes/Loader/Registry': 'DataImporter/DynamicTypes/Loader/Registry',

  // Loader types
  'DataImporter/DynamicTypes/Loader/Asset': 'DataImporter/DynamicTypes/Loader/Asset',
  'DataImporter/DynamicTypes/Loader/Upload': 'DataImporter/DynamicTypes/Loader/Upload',
  'DataImporter/DynamicTypes/Loader/Http': 'DataImporter/DynamicTypes/Loader/Http',
  'DataImporter/DynamicTypes/Loader/Sftp': 'DataImporter/DynamicTypes/Loader/Sftp',
  'DataImporter/DynamicTypes/Loader/Push': 'DataImporter/DynamicTypes/Loader/Push',
  'DataImporter/DynamicTypes/Loader/Sql': 'DataImporter/DynamicTypes/Loader/Sql',

  // Resolver registry
  'DataImporter/DynamicTypes/Resolver/Registry': 'DataImporter/DynamicTypes/Resolver/Registry',

  // Resolver loading types
  'DataImporter/DynamicTypes/Resolver/Loading/NotLoad': 'DataImporter/DynamicTypes/Resolver/Loading/NotLoad',
  'DataImporter/DynamicTypes/Resolver/Loading/Id': 'DataImporter/DynamicTypes/Resolver/Loading/Id',
  'DataImporter/DynamicTypes/Resolver/Loading/Path': 'DataImporter/DynamicTypes/Resolver/Loading/Path',
  'DataImporter/DynamicTypes/Resolver/Loading/Attribute': 'DataImporter/DynamicTypes/Resolver/Loading/Attribute',

  // Resolver create location types
  'DataImporter/DynamicTypes/Resolver/Location/Creation/StaticPath': 'DataImporter/DynamicTypes/Resolver/Location/Creation/StaticPath',
  'DataImporter/DynamicTypes/Resolver/Location/Creation/FindOrCreateFolder': 'DataImporter/DynamicTypes/Resolver/Location/Creation/FindOrCreateFolder',
  'DataImporter/DynamicTypes/Resolver/Location/Creation/FindParent': 'DataImporter/DynamicTypes/Resolver/Location/Creation/FindParent',
  'DataImporter/DynamicTypes/Resolver/Location/Creation/DoNotCreate': 'DataImporter/DynamicTypes/Resolver/Location/Creation/DoNotCreate',

  // Resolver update location types
  'DataImporter/DynamicTypes/Resolver/Location/Update/NoChange': 'DataImporter/DynamicTypes/Resolver/Location/Update/NoChange',
  'DataImporter/DynamicTypes/Resolver/Location/Update/StaticPath': 'DataImporter/DynamicTypes/Resolver/Location/Update/StaticPath',
  'DataImporter/DynamicTypes/Resolver/Location/Update/FindOrCreateFolder': 'DataImporter/DynamicTypes/Resolver/Location/Update/FindOrCreateFolder',
  'DataImporter/DynamicTypes/Resolver/Location/Update/FindParent': 'DataImporter/DynamicTypes/Resolver/Location/Update/FindParent',

  // Resolver publishing types
  'DataImporter/DynamicTypes/Resolver/Publishing/NoChangeUnpublishNew': 'DataImporter/DynamicTypes/Resolver/Publishing/NoChangeUnpublishNew',
  'DataImporter/DynamicTypes/Resolver/Publishing/NoChangePublishNew': 'DataImporter/DynamicTypes/Resolver/Publishing/NoChangePublishNew',
  'DataImporter/DynamicTypes/Resolver/Publishing/AlwaysPublish': 'DataImporter/DynamicTypes/Resolver/Publishing/AlwaysPublish',
  'DataImporter/DynamicTypes/Resolver/Publishing/AttributeBased': 'DataImporter/DynamicTypes/Resolver/Publishing/AttributeBased',

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
  'DataImporter/DynamicTypes/Transformer/ImportAsset': 'DataImporter/DynamicTypes/Transformer/ImportAsset',

  // Data Target registry
  'DataImporter/DynamicTypes/DataTarget/Registry': 'DataImporter/DynamicTypes/DataTarget/Registry',

  // Data Target types
  'DataImporter/DynamicTypes/DataTarget/Direct': 'DataImporter/DynamicTypes/DataTarget/Direct',
  'DataImporter/DynamicTypes/DataTarget/Classificationstore': 'DataImporter/DynamicTypes/DataTarget/Classificationstore',
  'DataImporter/DynamicTypes/DataTarget/ClassificationStoreBatch': 'DataImporter/DynamicTypes/DataTarget/ClassificationStoreBatch',
  'DataImporter/DynamicTypes/DataTarget/ManyToManyRelation': 'DataImporter/DynamicTypes/DataTarget/ManyToManyRelation'
} as const
