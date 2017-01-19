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
class CRM_Ocrimporter_Test extends \PHPUnit_Framework_TestCase implements HeadlessInterface  {

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
   * Below is the test definition.
   *
   * Array of OCR files we are going to test
   * Per file indicate the filename (the file it self is stored in tests/fies),
   * indicate whether this file should be imported correctly or not (should_succeed)
   * and indicate the message for when the test fails.
   *
   * @var array
   */
  private $files = array(
    array(
      'file' => 'OCR.D200116',
      'should_succeed' => true,
      'message' => 'The file should succeeed.',
      'test_transactions' => true,
      'transaction_count' => 20,
      'transactions_to_test' => array(
        array(
          'assignment_number' => 1,
          'transaction_number' => 1,
          'expected_amount_in_ore' => 102000,
          'expected_bank_account' => '99990512341',
          'create_contact' => array(
            'first_name' => 'Stephan',
            'last_name' => 'Moss',
            'contact_type' => 'Individual',
          ),
        ),
        array(
          'assignment_number' => 1,
          'transaction_number' => 6,
          'expected_amount_in_ore' => 56000,
          'expected_kid' => '0165867',
        ),
        array(
          'assignment_number' => 1,
          'transaction_number' => 8,
          'expected_bank_account' => '99999545528',
        ),
      )
    ),

    array(
      'file' => 'OCR.D200116.wrongamountofrecords.fails',
      'should_succeed' => false,
      'message' => 'The file should fail because of the wrong number of records in the file.',
    ),

    array(
      'file' => 'OCR.D200116.wrongamountofassignmentrecords.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong number of records in the assignment.',
    ),

    array(
      'file' => 'OCR.D200116.wrongtotalamount.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong total amount in ore in the file',
    ),

    array(
      'file' => 'OCR.D200116.wrongtotalamountassignment.fails',
      'should_succeed' => false,
      'message' => 'The file should have failed because of the wrong total amount in ore in the assignment section of the file',
    ),

    array(
      'file' => 'OCR.D200116.noendrecord.fails',
      'should_succeed' => false,
      'message' => 'The file does not have an end transmission record',
    ),

    array(
      'file' => 'OCR.D200116.randomtextfile.fails',
      'should_succeed' => false,
      'message' => 'The file is a random text file and does not contain any OCR information.'
    )
  );

  private $createdContactIds = array();

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

    $builder = \Civi\Test::headless();
    $builder->install(array('org.project60.banking'));
    $builder->installMe(__dir__);
    $builder->apply();

    return $builder;
  }

  public function setUp() {
    parent::setUp();

    // Clear the CiviBanking transactions and the CiviBanking bank accounts
    \Civi\Test::execute("DELETE FROM `civicrm_bank_tx`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_tx_batch`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_account_reference`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_account`");

    // Create contacts and bank accounts for those contacts.
    foreach($this->files as $f => $file) {
      if (isset($file['transactions_to_test'])) {
        foreach ($file['transactions_to_test'] as $t => $transactionToTest) {
          if (isset($transactionToTest['create_contact'])) {
            $contact = civicrm_api3('Contact', 'create', $transactionToTest['create_contact']);
            $this->files[$f]['transactions_to_test'][$t]['contact_id'] = $contact['id'];
          }
          if (isset($this->files[$f]['transactions_to_test'][$t]['contact_id']) && isset($transactionToTest['expected_bank_account'])) {
            $ba_params['contact_id'] = $this->files[$f]['transactions_to_test'][$t]['contact_id'];
            $ba_params['data_parsed'] = json_encode(array(
              'name' => $transactionToTest['expected_bank_account']
            ));
            $ba_params['data_raw'] = $transactionToTest['expected_bank_account'];
            $ba_params['description'] = $transactionToTest['expected_bank_account'];
            $ba = civicrm_api3('BankingAccount', 'create', $ba_params);

            $ba_ref_params['ba_id'] = $ba['id'];
            $ba_ref_params['reference'] = $transactionToTest['expected_bank_account'];
            $ba_ref_params['reference_type_id'] = civicrm_api3('OptionValue', 'getvalue', array(
              'return' => 'id',
              'name' => 'ocr',
              'option_group_id' => 'civicrm_banking.reference_types'
            ));
            civicrm_api3('BankingAccountReference', 'create', $ba_ref_params);
          }
        }
      }
    }
  }

  public function tearDown() {
    parent::tearDown();

    // Clear the CiviBanking transactions and the CiviBanking bank accounts
    \Civi\Test::execute("DELETE FROM `civicrm_bank_tx`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_tx_batch`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_account_reference`");
    \Civi\Test::execute("DELETE FROM `civicrm_bank_account`");

    // Delete contacts
    foreach($this->files as $f => $file) {
      if (isset($file['transactions_to_test'])) {
        foreach ($file['transactions_to_test'] as $t => $transactionToTest) {
          if (isset($transactionToTest['contact_id'])) {
            civicrm_api3('Contact', 'delete', array(
              'id' => $transactionToTest['contact_id'],
              'skip_undelete' => true
            ));
          }
        }
      }
    }
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

    // Find a plugin instance for this plugin.
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
      $this->checkTheFile($this->files[$i], $plugin);
    }
  }

  /**
   * Test the file whether it could be imported and should be or not.
   * If so test also all the transactions in the file.
   *
   * @param $file
   * @param \CRM_Banking_PluginImpl_Importer_OCR $plugin
   */
  private function checkTheFile($file, CRM_Banking_PluginImpl_Importer_OCR $plugin) {
    $import_parameters = array(
      'dry_run' => false,
      'source' => 'Test '.$file['file'],
    );
    $file_path = $this->extensionDir.'/tests/files/'.$file['file'];
    $this->assertFileExists($file_path, 'File '.$file['file'].' does not exist.');

    $probe_result = $plugin->probe_file($file_path, $import_parameters);
    if ($file['should_succeed']) {
      $this->assertNotFalse($probe_result, 'Probe result for '.$file['file'].' should succeed but did not. '.$file['message']);
      if (isset($file['test_transactions']) && $file['test_transactions']) {
        $plugin->import_file($file_path, $import_parameters);
        $this->checkTransactionInFile($file, $plugin);
      }
    } else {
      $this->assertFalse($probe_result, 'Probe result for '.$file['file'].' should not succeed but it succeeded. '.$file['message']);
    }
  }

  /**
   * Test the file for each assignment and then each transaction.
   *
   * @param $file
   * @param \CRM_Banking_PluginImpl_Importer_OCR $plugin
   */
  private function checkTransactionInFile($file, CRM_Banking_PluginImpl_Importer_OCR $plugin) {
    $file_path = $this->extensionDir.'/tests/files/'.$file['file'];
    $assignments = $plugin->getAssignments($file_path);

    // Count the number of transactions in the file and the number of transactions per assignment
    $transactionCount = 0;
    $amountItemTransactionsPerAssignment = array();
    foreach($assignments as $assignment) {
      $transactionCount = $transactionCount + count($assignment->getTransactions());
      $amountItemTransactionsPerAssignment[$assignment->getAssignmentNumber()] = 0;
      foreach($assignment->getTransactions() as $transaction) {
        $amountItemTransactionsPerAssignment[$assignment->getAssignmentNumber()]++;
      }
    }

    // Test the number of transactions and number of records.
    $this->assertEquals($file['transaction_count'], $transactionCount, $file['file'].' should have '.$file['transaction_count'].' transactions we found '.$transactionCount.' transactions');

    // Read all the lines from the file and loop through all the assignments in the file
    // and test each assignment.
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    $currentLine = 0;
    foreach($assignments as $assignment) {
      // Go to the line of the start assignment in the file
      $currentLine ++;

      //Check properties of assignment
      $this->assertInstanceOf('CRM_Ocrimporter_Record_EndAssignment', $assignment->getEndAssignmentRecord());
      $this->assertInstanceOf('CRM_Ocrimporter_Record_StartTransmission', $assignment->getStartTransmissionRecord());
      $this->assertInstanceOf('CRM_Ocrimporter_Record_EndTransmission', $assignment->getStartTransmissionRecord()->getEndTransmissionRecord());

      $batchReference = '';
      if ($assignment->getStartTransmissionRecord()) {
        $batchReference = $assignment->getStartTransmissionRecord()->getTransmissionNumber().'-'.$assignment->getAssignmentNumber();
      }

      // Check the batch
      $batchParams['reference'] = $batchReference;
      $batchParams['version'] = $this->apiversion;
      $batch = civicrm_api('BankingTransactionBatch', 'getsingle', $batchParams);
      $this->assertArrayHasKey('id', $batch, "Expected one batch for ".$batchReference);
      $this->assertGreaterThan(0, $batch['id'], "Expected one batch for ".$batchReference);

      // Test the amount of transactions in this assignment.
      $this->assertEquals($amountItemTransactionsPerAssignment[$assignment->getAssignmentNumber()], $batch['tx_count'], "Transaction count in batch does not match");

      // Test all the transactions in the assignment.
      $currentLine = $this->checkAssignmentTransactions($assignment, $batch, $currentLine, $lines, $file);

      // Go to the line of the end assignment in the file.
      $currentLine++;
    }
  }

  /**
   * Check the transactions in an assignment.
   *
   * @param \CRM_Ocrimporter_Record_StartAssignment $assignment
   * @param $banking_batch
   * @param $lineNumber
   * @param $lines
   * @param $fileName
   * @return mixed
   */
  function checkAssignmentTransactions(CRM_Ocrimporter_Record_StartAssignment $assignment, $banking_batch, $lineNumber, $lines, $file) {
    $currentLine = $lineNumber;
    foreach($assignment->getTransactions() as $transaction) {
      $rawTransactionLines = explode("\n", $transaction->getRawData());
      $transactionLineNumber=$currentLine;
      foreach($rawTransactionLines as $transactionLine) {
        $transactionLineNumber++;
        $this->assertEquals($lines[$transactionLineNumber], $transactionLine, 'The transaction data does not match');
      }

      $bankingTransactionParams = array();
      $bankingTransactionParams['tx_batch_id'] = $banking_batch['id'];
      $bankingTransactionParams['sequence'] = $transaction->getTransactionNumber();
      $bankingTransactionParams['version'] = $this->apiversion;
      $bankingTransaction = civicrm_api('BankingTransaction', 'getsingle', $bankingTransactionParams);

      $this->assertArrayHasKey('id', $bankingTransaction, "Expected one banking transaction");
      $this->assertGreaterThan(0, $bankingTransaction['id'], "Expected one banking transaction");

      $this->checkTransactionToTest($assignment, $transaction, $bankingTransaction, $currentLine+1, $lines, $file);

      $currentLine += count($rawTransactionLines);
    }
    return $currentLine;
  }

  /**
   * Check the transactions for test for this record.
   *
   * @param \CRM_Ocrimporter_Record_StartAssignment $assignment
   * @param \CRM_Ocrimporter_Record_AmountItem1 $amountItem1
   * @param $bankingTransaction
   * @param $lineNumber
   * @param $lines
   * @param $fileName
   */
  private function checkTransactionToTest(CRM_Ocrimporter_Record_StartAssignment $assignment, CRM_Ocrimporter_Record_Transaction $transaction, $bankingTransaction, $lineNumber, $lines, $file) {
    if (isset($file['transactions_to_test']) && is_array($file['transactions_to_test'])) {
      foreach($file['transactions_to_test'] as $transactionToTest) {
        if ($transactionToTest['assignment_number'] == $assignment->getAssignmentNumber() && $transactionToTest['transaction_number'] == $transaction->getTransactionNumber()) {
          // Do testing of this transaction
          $this->checkAmount($transaction, $bankingTransaction, $transactionToTest, $lineNumber+1, $lines[$lineNumber], $file['file']);
          $this->checkKid($transaction, $bankingTransaction, $transactionToTest, $lineNumber+1, $lines[$lineNumber], $file['file']);
          $this->checkBankAccount($transaction, $bankingTransaction, $transactionToTest, $lineNumber+2, $lines[$lineNumber+1], $file['file']);
        }
      }
    }
  }

  /**
   * Check whether the amount is present in the file and in the transaction.
   *
   * @param \CRM_Ocrimporter_Record_AmountItem1 $amountItem1
   * @param $bankingTransaction
   * @param $transactionToTest
   * @param $lineNumber
   * @param $lineInFile
   * @param $fileName
   */
  private function checkAmount(CRM_Ocrimporter_Record_AmountItem1 $amountItem1, $bankingTransaction, $transactionToTest, $lineNumber, $lineInFile, $fileName) {
    if (isset($transactionToTest['expected_amount_in_ore'])) {
      $expected_amount_in_nrk = $transactionToTest['expected_amount_in_ore'] / 100;
      $amount_in_file = (int) substr($lineInFile, 31,18);
      $this->assertEquals($transactionToTest['expected_amount_in_ore'], $amount_in_file, "Expected amount did not match on line ".$lineNumber." in ".$fileName);
      $this->assertEquals($transactionToTest['expected_amount_in_ore'], $amountItem1->getAmount(), "Expected amount did not match in CRM_Ocrimporter_Record_AmountItem1 on line ".$lineNumber." in ".$fileName);
      $this->assertEquals($expected_amount_in_nrk, $bankingTransaction['amount'], "Expected amount did not match in civicrm_bank_tx on line ".$lineNumber." in ".$fileName);
    }
  }

  /**
   * Chceck whether the KID is present in the file and transaction.
   *
   * @param \CRM_Ocrimporter_Record_AmountItem1 $amountItem1
   * @param $bankingTransaction
   * @param $transactionToTest
   * @param $lineNumber
   * @param $lineInFile
   * @param $fileName
   */
  private function checkKid(CRM_Ocrimporter_Record_AmountItem1 $amountItem1, $bankingTransaction, $transactionToTest, $lineNumber, $lineInFile, $fileName) {
    if (isset($transactionToTest['expected_kid'])) {
      $kid_in_file = ltrim(substr($lineInFile, 49,25));
      $data_parsed = json_decode($bankingTransaction['data_parsed'], true);
      $this->assertEquals($transactionToTest['expected_kid'], $kid_in_file, "Expected KID did not match on line ".$lineNumber." in ".$fileName);
      $this->assertEquals($transactionToTest['expected_kid'], $amountItem1->getKid(), "Expected KID did not match in CRM_Ocrimporter_Record_AmountItem1 on line ".$lineNumber." in ".$fileName);
      $this->assertArrayHasKey('kid', $data_parsed, "KID number not present civicrm_bank_tx on line ".$lineNumber." in ".$fileName);
      $this->assertSame($transactionToTest['expected_kid'], $data_parsed['kid'], "Expected KID did not match in civicrm_bank_tx on line ".$lineNumber." in ".$fileName);
    }
  }

  /**
   * Check whether the current record has the right banking account.
   *
   * @param \CRM_Ocrimporter_Record_AmountItem1 $amountItem1
   * @param $bankingTransaction
   * @param $transactionToTest
   * @param $lineNumber
   * @param $lineInFile
   * @param $fileName
   */
  private function checkBankAccount(CRM_Ocrimporter_Record_AmountItem1 $amountItem1, $bankingTransaction, $transactionToTest, $lineNumber, $lineInFile, $fileName) {
    if (isset($transactionToTest['expected_bank_account'])) {
      $amountItem2 = $amountItem1->getAmountItem2();
      $this->assertInstanceOf('CRM_Ocrimporter_Record_AmountItem2', $amountItem2, "Line with the bank account was not imported on line ".$lineNumber." in ".$fileName);
      $recordType = substr($lineInFile,6,2);
      $this->assertEquals(31, $recordType, "Line with the bank account was not in the file ".$fileName);
      $bank_account_in_file = substr($lineInFile,47,11);
      $data_parsed = json_decode($bankingTransaction['data_parsed'], true);
      $this->assertEquals($transactionToTest['expected_bank_account'], $bank_account_in_file, "Bank account is not present in the file on line".$lineNumber." in ".$fileName);
      $this->assertEquals($transactionToTest['expected_bank_account'], $amountItem2->getDebitAccount(), "Bank account is not present in the file on line".$lineNumber." in ".$fileName);
      $this->assertArrayHasKey('debitAccount', $data_parsed, "Bank account not present civicrm_bank_tx on line ".$lineNumber." in ".$fileName);
      $this->assertSame($transactionToTest['expected_bank_account'], $data_parsed['debitAccount'], "Bank account did not match in civicrm_bank_tx on line ".$lineNumber." in ".$fileName);
      if (isset($transactionToTest['contact_id']) && $transactionToTest['contact_id']) {
        $this->assertArrayHasKey('party_ba_id', $bankingTransaction, "The civicrm_bank_tx does nat have party_ba_id set");
        $ba_id = $bankingTransaction['party_ba_id'];
        $this->assertNotEmpty($ba_id, "The civicrm_bank_tx does nat have party_ba_id set");
        $bank_account = civicrm_api('BankingAccount', 'getsingle', array(
          'id' => $ba_id,
          'version' => $this->apiversion,
        ));
        $this->assertArrayHasKey('id', $bank_account, "The civicrm_bank_account could not be found");
        $this->assertEquals($ba_id, $bank_account['id'], "The civicrm_bank_account could not be found");
        $this->assertEquals($transactionToTest['contact_id'], $bank_account['contact_id'], "Bank account does not belong to the right contact");
      }
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
