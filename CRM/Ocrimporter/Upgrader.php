<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Ocrimporter_Upgrader extends CRM_Ocrimporter_Upgrader_Base {


  /**
   * Add OCR Importer to the option group of plugins of CiviBaking
   */
  public function install() {
    $manager = \CRM_Extension_System::singleton()->getManager();
    if ($manager->getStatus('org.project60.banking') != 'installed') {
      throw new Exception("no.maf.ocrimporter requires the extension org.project60.banking to be installed");
    }

    try {
      $importer_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => "importer_ocr",
      ));
      civicrm_api3('OptionValue', 'delete', array('id' => $importer_id));
    } catch (Exception $e) {
      // doesn't exist yet
      $result = civicrm_api3('OptionValue', 'create', array(
        'option_group_id'  => "civicrm_banking.plugin_types",
        'name'             => 'importer_ocr',
        'label'            => 'OCR Importer',
        'value'            => 'CRM_Banking_PluginImpl_Importer_OCR',
        'is_default'       => 0
      ));
      $importer_id = $result['id'];
    }

    // then, find the correct plugin type
    $import_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'import', 'group_id' => 'civicrm_banking.plugin_class'));

    // Plugin type and plugin class are switched around
    // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
    $params['plugin_type_id'] = $import_plugin_class['id'];
    $params['plugin_class_id'] = $importer_id;
    $params['name'] = 'OCR Importer';
    $params['enabled'] = 1;
    civicrm_api3('BankingPluginInstance', 'create', $params);
  }

  /**
   * Remove OCR Importer from the option group of plugins of CiviBanking
   */
  public function uninstall() {
    try {
      $import_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'import', 'group_id' => 'civicrm_banking.plugin_class'));
      $importer_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => "importer_ocr",
      ));

      // Plugin type and plugin class are switched around
      // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
      $params['plugin_type_id'] = $import_plugin_class['id'];
      $params['plugin_class_id'] = $importer_id;
      $importerPluginInstances = civicrm_api3('BankingPluginInstance', 'get', $params);
      foreach($importerPluginInstances['values'] as $importerPluginInstance) {
        civicrm_api3('BankingPluginInstance', 'delete', array('id' => $importerPluginInstance['id']));
      }

      civicrm_api3('OptionValue', 'delete', array('id' => $importer_id));
    } catch (Exception $e) {
      // Do nothing
    }
  }

}
