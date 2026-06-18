/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.noChange');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.noChange = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'noChange',

    buildSettingsForm: function() {
        return null;
    }

});