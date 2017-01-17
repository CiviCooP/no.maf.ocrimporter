<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * UnitTests for the OCR importer for CiviBanking
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Ocrimporter_Test extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * @var String
   *  The name of the extension. We will deduct this from the directory name.
   */
  private $extensionName;

  /**
   * @var String
   *  The directory of the current extension.
   */
  private $extensionDir;

  /**
   * @var int
   *  API version to use.
   */
  private $apiversion = 3;

  /**
   * Array of OCR files we are going to test
   * Per file indicate the filename (the file it self is stored in tests/fies),
   * indicate whether this file should be imported correctly or not (should_succeed)
   * and indicate the message for when the test fails.
   *
   * @var array
   */
  private $files = array(
    array(
      'file' => 'OCR.D250913',
      'should_succeed' => true,
      'message' => 'The file should succeeed.',
    ),

    array(
      'file' => 'OCR.D260913',
      'should_succeed' => true,
      'message' => 'The file should succeeed.',
    ),

    array(
      'file' => 'OCR.D270913',
      'should_succeed' => true,
      'message' => 'The file should succeeed.',
    ),

    array(
      'file' => 'OCR.D250913.wrongamountofrecords.fails',
      'should_succeed' => false,
      'message' => 'The file should fail because of the wrong number of records in the file.',
    ),

    array(
      'file' => 'OCR.D250913.wrongamountofassignmentrecords.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong number of records in the assignment.',
    ),

    array(
      'file' => 'OCR.D250913.wrongtotalamount.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong total amount in ore in the file',
    ),

    array(
      'file' => 'OCR.D250913.wrongtotalamountassignment.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong total amount in ore in the assignment section of the file',
    ),

    array(
      'file' => 'OCR.D250913.noendrecord.fails',
      'should_succeed' => false,
      'message' => 'The file does not have an end transmission record',
    ),

    array(
      'file' => 'OCR.D250913.randomtextfile.fails',
      'should_succeed' => false,
      'message' => 'The file is a random text file and does not contain any OCR information.'
    )
  );

  public function setUpHeadless() {
    // Get our extension name.
    $this->extensionName = $this->whoAmI(__DIR__);
    $container = \CRM_Extension_System::singleton()->getFullContainer();
    // Get the directory of the extension based on the name.
    $this->extensionDir = $container->getPath($this->extensionName);

    // Download the latest CiviBanking extension
    $manager = \CRM_Extension_System::singleton()->getManager();
    if ($manager->getStatus('org.project60.banking') == 'unknown') {
      $downloader = \CRM_Extension_System::singleton()->getDownloader();
      $downloader->download('org.project60.banking', 'https://github.com/Project60/org.project60.banking/archive/master.zip');
    }

    // Install civibanking and this extension.
    $builder = \Civi\Test::headless()
      ->install(array('org.project60.banking'))
      ->installMe(__dir__)
      ->apply();

    return $builder;
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * This function tetsts whether the OCR example file exists
   */
  public function testOCRFiles() {
    // First testing whether the extension is succesfully installed.
    // Meaning an OCR Importer is available through CiviBanking functionality.
    $importer_id = civicrm_api('OptionValue', 'getvalue', array(
      'return' => "id",
      'option_group_id' => "civicrm_banking.plugin_types",
      'name' => "importer_ocr",
      'version' => $this->apiversion,
    ));

    $this->assertTrue(is_numeric($importer_id), "Expected a numeric value for option value id (civicrm_banking.plugin_types:importer_ocr) but got " . print_r($importer_id, 1));

    $import_plugin_class = civicrm_api('OptionValue', 'getsingle', array('version' => 3, 'name' => 'import', 'group_id' => 'civicrm_banking.plugin_class', 'version' => $this->apiversion));
    $this->assertArrayHasKey('id', $import_plugin_class, "Expected one plugin class for CiviBanking Importer Plugin");
    $this->assertGreaterThan(0, $import_plugin_class['id'], "Expected one plugin class for CiviBanking Importer Plugin");

    // Plugin type and plugin class are switched around
    // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
    $params['plugin_type_id'] = $import_plugin_class['id'];
    $params['plugin_class_id'] = $importer_id;
    $params['version'] = $this->apiversion;
    $importerPluginInstances = civicrm_api('BankingPluginInstance', 'get', $params);

    $this->assertEquals(1, $importerPluginInstances['count'], "Expected one instance of the OCR importer plugin");


    $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');
    $plugin = false;
    foreach($plugin_list as $pluginInstance) {
      if ($pluginInstance->plugin_class_id == $importer_id) {
        $plugin = $pluginInstance->getInstance();
      }
    }
    $this->assertInstanceOf('CRM_Banking_PluginImpl_Importer_OCR', $plugin, 'Could not find OCR importer plugin instance');

    // Ok the extension is installed successfully lets now test for each file whether it is handled correctly
    for($i=0; $i < count($this->files); $i++) {
      $this->fileTesting($this->files[$i], $plugin);
    }
  }

  public function fileTesting($file, $plugin) {
    $import_parameters = array(
      'dry_run' => false,
      'source' => 'Test '.$file['file'],
    );
    $file_path = $this->extensionDir.'/tests/files/'.$file['file'];
    $this->assertFileExists($file_path, 'File '.$file['file'].' does not exist.');

    $probe_result = $plugin->probe_file($file_path, $import_parameters);
    if ($file['should_succeed']) {
      $this->assertNotFalse($probe_result, 'Probe result for '.$file['file'].' should succeed but did not. '.$file['message']);
    } else {
      $this->assertFalse($probe_result, 'Probe result for '.$file['file'].' should not succeed but it succeeded. '.$file['message']);
    }
  }


  /**
   * @param $dir
   * @return null
   * @throws \CRM_Extension_Exception_ParseException
   */
  protected function whoAmI($dir) {
    $name = '';
    while ($dir && dirname($dir) !== $dir && !file_exists("$dir/info.xml")) {
      $dir = dirname($dir);
    }
    if (file_exists("$dir/info.xml")) {
      $info = \CRM_Extension_Info::loadFromFile("$dir/info.xml");
      $name = $info->key;
      return $name;
    }
    return $name;
  }

}
