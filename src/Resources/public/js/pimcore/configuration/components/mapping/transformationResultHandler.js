/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.transformationResultHandler');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.transformationResultHandler = Class.create({

    currentPreviewRecord: 0,
    configItemRootContainer: null,
    configItemInstance: null,
    configName: '',
    transformationResultPreviews: [],

    initialize: function(configName, configItemInstance) {
        this.configName = configName;
        this.configItemRootContainer = configItemInstance.tab;
        this.configItemInstance = configItemInstance;
    },

    setCurrentPreviewRecord: function(currentPreviewRecord) {
        this.currentPreviewRecord = currentPreviewRecord;
    },

    updateData: function(fireUpdateEvent, callback) {

        //loads transformation results (data type & preview) for all mappings and stores it in class variable
        Ext.Ajax.request({
            url: Routing.generate('pimcore_datahubbatchimport_configdataobject_loadtransformationresultpreviews'),
            method: 'POST',
            params: {
                config_name: this.configName,
                current_preview_record: this.currentPreviewRecord,
                current_config: this.configItemInstance.getSaveData()
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                this.transformationResultPreviews = data.transformationResultPreviews;

                if(fireUpdateEvent) {
                    //fire event so that elements can update themselves
                    this.configItemRootContainer.fireEvent(
                        pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.events.transformationResultPreviewUpdated,
                        this
                    );
                }

                //call callback after update is finished
                if(callback) {
                    callback();
                }

            }.bind(this)
        });

    },

    getTransformationResultPreview: function(mappingIndex) {

        return this.transformationResultPreviews[mappingIndex];

        //TODO load data
        //load from class variable and return transformation result for given mapping index
        return {
            'transformationResultType': 'default',
            'transformationResultPreview': 'some data'
        };

    }


});
