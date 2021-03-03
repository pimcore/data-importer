/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS("pimcore.plugin.PimcoreDataImporterBundle");

pimcore.plugin.PimcoreDataImporterBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return 'pimcore.plugin.PimcoreDataImporterBundle';
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    }

});

var PimcoreDataImporterBundlePlugin = new pimcore.plugin.PimcoreDataImporterBundle();
