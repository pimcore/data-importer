<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataImporterBundle;

use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\CleanupStrategyConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\InterpreterConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\LoaderConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\MappingConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\ResolverConfigurationFactoryPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcoreDataImporterBundle extends AbstractPimcoreBundle
{
    const LOGGER_COMPONENT_PREFIX = 'DATA-IMPORTER ';

    public function getCssPaths()
    {
        return [
            '/bundles/pimcoredataimporter/css/icons.css'
        ];
    }

    public function getJsPaths()
    {
        return [
            '/bundles/pimcoredataimporter/js/pimcore/startup.js',
            '/bundles/pimcoredataimporter/js/pimcore/helper/ext_extensions.js',
            '/bundles/pimcoredataimporter/js/pimcore/helper/abstractOptionType.js',
            '/bundles/pimcoredataimporter/js/pimcore/adapter/dataImporterDataObject.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/configEvents.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/configItemDataObject.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/loader/sftp.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/loader/http.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/loader/asset.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/loader/push.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/interpreter/csv.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/interpreter/json.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/interpreter/xlsx.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/interpreter/xml.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/cleanup/unpublish.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/cleanup/delete.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/importSettings.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/importPreview.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/load/id.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/load/path.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/load/attribute.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/load/notLoad.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/location/staticPath.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/location/findParent.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/location/noChange.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/alwaysPublish.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/attributeBased.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/noChangePublishNew.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/noChangeUnpublishNew.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/mappingConfiguration.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/mappingConfigurationItem.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/transformationResultHandler.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/datatarget/direct.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/datatarget/classificationstore.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/datatarget/classificationstoreBatch.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/tools/classificationStoreKeySearchWindow.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/abstractOperator.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/trim.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/numeric.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/asArray.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/explode.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/combine.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/htmlDecode.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/quantityValue.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/inputQuantityValue.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/boolean.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/date.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/importAsset.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/loadAsset.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/gallery.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/imageAdvanced.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/loadDataObject.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/reduceArrayKeyValuePairs.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/flattenArray.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/execution.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/logTab.js',
        ];
    }

    public function build(ContainerBuilder $container)
    {
        $container
            ->addCompilerPass(new MappingConfigurationFactoryPass())
            ->addCompilerPass(new ResolverConfigurationFactoryPass())
            ->addCompilerPass(new LoaderConfigurationFactoryPass())
            ->addCompilerPass(new InterpreterConfigurationFactoryPass())
            ->addCompilerPass(new CleanupStrategyConfigurationFactoryPass())
        ;
    }
}
