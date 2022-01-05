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

namespace Pimcore\Bundle\DataImporterBundle;

use League\FlysystemBundle\FlysystemBundle;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\CleanupStrategyConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\InterpreterConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\LoaderConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\MappingConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\ResolverConfigurationFactoryPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcoreDataImporterBundle extends AbstractPimcoreBundle implements DependentBundleInterface
{
    use PackageVersionTrait;

    const LOGGER_COMPONENT_PREFIX = 'DATA-IMPORTER ';

    protected function getComposerPackageName(): string
    {
        return 'pimcore/data-importer';
    }

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
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/loader/upload.js',
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
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/location/doNotCreate.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/alwaysPublish.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/attributeBased.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/noChangePublishNew.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/resolver/publish/noChangeUnpublishNew.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/mappingConfiguration.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/mappingConfigurationItem.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/transformationResultHandler.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/datatarget/direct.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/datatarget/manyToManyRelation.js',
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
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/quantityValueArray.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/inputQuantityValue.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/inputQuantityValueArray.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/boolean.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/date.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/importAsset.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/loadAsset.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/gallery.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/imageAdvanced.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/loadDataObject.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/reduceArrayKeyValuePairs.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/flattenArray.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/staticText.js',
            '/bundles/pimcoredataimporter/js/pimcore/configuration/components/mapping/operator/conditionalConversion.js',
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

    public static function registerDependentBundles(BundleCollection $collection)
    {
        $collection->addBundle(new FlysystemBundle());
    }

    public function getInstaller()
    {
        return $this->container->get(Installer::class);
    }
}
