/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.delete');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.delete = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'delete',

    buildSettingsForm: function() {
        return null;
    }

});