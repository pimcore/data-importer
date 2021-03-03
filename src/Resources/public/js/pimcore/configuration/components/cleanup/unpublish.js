/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.cleanup.unpublish");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.cleanup.unpublish = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'unpublish',

    buildSettingsForm: function() {
        return null;
    }

});