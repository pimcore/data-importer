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

namespace Pimcore\Bundle\DataHubBatchImportBundle;

use Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass\CleanupStrategyConfigurationFactoryPass;
use Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass\InterpreterConfigurationFactoryPass;
use Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass\LoaderConfigurationFactoryPass;
use Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass\MappingConfigurationFactoryPass;
use Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass\ResolverConfigurationFactoryPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcoreDataHubBatchImportBundle extends AbstractPimcoreBundle
{
    const LOGGER_COMPONENT_PREFIX = 'BATCH-IMPORT ';

    public function getCssPaths()
    {
        return [
            '/bundles/pimcoredatahubbatchimport/css/icons.css'
        ];
    }

    public function getJsPaths()
    {
        return [
            '/bundles/pimcoredatahubbatchimport/js/pimcore/startup.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/helper/ext_extensions.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/helper/abstractOptionType.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/adapter/batchImportDataObject.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/configEvents.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/configItemDataObject.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/loader/sftp.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/loader/http.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/loader/asset.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/loader/push.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/interpreter/csv.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/interpreter/json.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/interpreter/xlsx.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/interpreter/xml.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/cleanup/unpublish.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/cleanup/delete.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/importSettings.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/importPreview.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/load/id.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/load/path.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/load/attribute.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/load/notLoad.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/location/staticPath.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/location/findParent.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/location/noChange.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/publish/alwaysPublish.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/publish/attributeBased.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/publish/noChangePublishNew.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/resolver/publish/noChangeUnpublishNew.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/mappingConfiguration.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/mappingConfigurationItem.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/transformationResultHandler.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/datatarget/direct.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/datatarget/classificationstore.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/datatarget/classificationstoreBatch.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/tools/classificationStoreKeySearchWindow.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/abstractOperator.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/trim.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/numeric.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/asArray.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/explode.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/combine.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/htmlDecode.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/quantityValue.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/inputQuantityValue.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/boolean.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/date.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/importAsset.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/loadAsset.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/gallery.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/imageAdvanced.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/loadDataObject.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/reduceArrayKeyValuePairs.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/mapping/operator/flattenArray.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/execution.js',
            '/bundles/pimcoredatahubbatchimport/js/pimcore/configuration/components/logTab.js',
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
