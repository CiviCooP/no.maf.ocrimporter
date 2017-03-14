<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * OCR Importer
 * @package org.project60.banking
 */
class CRM_Banking_PluginImpl_Importer_OCR extends CRM_Banking_PluginModel_Importer {

  /**
   * @var array
   *  Array of assignments ordered by filename of the imported file.
   */
  private $assignments = array();

  /**
   * Report if the plugin is capable of importing files
   *
   * @return bool
   */
  static function does_import_files() {
    return true;
  }

  /**
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   *
   * @return bool
   */
  static function does_import_stream() {
    return false;
  }


  /**
   * Test if the given file can be imported.
   * Returns true when it can and return false when the file fails.
   *
   * @var string $file_path
   * @var array $import_parameters
   * @return bool
   */
  function probe_file($file_path, $params) {
    if (!file_exists($file_path)) {
      return false;
    }

    // Set up variables to test the file
    $startTransmission = false;
    $endTransmission = false;
    $startAssignment = false;
    $doesContainAFullAssignment = false;
    $lineNumber = 0;
    $transactionCount = 0;
    $linesInAssignment = 0;
    $assignments = array();
    $amountItem1 = false;
    $transmissionTotalAmount = 0;

    // Open te file and convert to Record objects
    $file = fopen($file_path, 'r');
    while($line = fgets($file)) {
      $record = CRM_Ocrimporter_Record::getRecord($line);

      if ($startAssignment) {
        $linesInAssignment++;
      }
      $lineNumber++;

      if ($record instanceof CRM_Ocrimporter_Record_StartTransmission) {
        $startTransmission = $record;
      }
      elseif ($record instanceof CRM_Ocrimporter_Record_EndTransmission) {
        $endTransmission = $record;
        if ($startTransmission) {
          $startTransmission->setEndTransmissionRecord($endTransmission);
        }
      }
      elseif ($record instanceof CRM_Ocrimporter_Record_StartAssignment) {
        if ($startTransmission) {
          $record->setStartTransmissionRecord($startTransmission);
        }
        $assignments[] = $record;
        $startAssignment = $record;
        $linesInAssignment = 1;
      }
      elseif ($record instanceof CRM_Ocrimporter_Record_EndAssignment) {
        $endAssignment = $record;
        if (!$startAssignment) {
          return false;
        }

        if ($endAssignment->getNumberOfRecords() != $linesInAssignment) {
          CRM_Core_Session::setStatus('Expected '.$endAssignment->getNumberOfRecords().' records got '.$linesInAssignment);
          return false;
        }

        if ($endAssignment->getNumberOfTransactions() != count($startAssignment->getTransactions())) {
          CRM_Core_Session::setStatus('Expected '.$endAssignment->getNumberOfTransactions().' transaction in assigment got '.count($startAssignment->getTransactions()));
          return false;
        }

        if ($endAssignment->getTotalAmount() != $startAssignment->getTotalTransactionAmount()) {
          return false;
        }

        $doesContainAFullAssignment = true;
        $startAssignment->setEndAssignmentRecord($record);
      } elseif ($record instanceof CRM_Ocrimporter_Record_AmountItem1) {
        if ($startAssignment) {
          $startAssignment->addTransaction($record);
        }
        $amountItem1 = $record;
      } elseif ($record instanceof CRM_Ocrimporter_Record_AmountItem2 && $amountItem1) {
        $record->setAmountItem1($amountItem1);
        $amountItem1->setAmountItem2($record);
      } elseif ($record instanceof CRM_Ocrimporter_Record_AmountItem3 && $amountItem1) {
        $record->setAmountItem1($amountItem1);
        $amountItem1->setAmountItem3($record);
      } elseif ($record instanceof CRM_Ocrimporter_Record_StandingOrder) {
        if ($startAssignment) {
          $startAssignment->addTransaction($record);
        }
      }

      if ($record instanceof CRM_Ocrimporter_Record_Transaction && $startAssignment) {
        $record->setStartAssignmentRecord($startAssignment);
      }
    }
    fclose($file);

    foreach($assignments as $assignment) {
      $transactionCount = $transactionCount + count($assignment->getTransactions());
      $transmissionTotalAmount += $assignment->getTotalTransactionAmount();
    }

    if (!$startTransmission) {
      return false;
    }
    if (!$endTransmission) {
      return false;
    }
    if ($endTransmission->getNumberOfRecords() != $lineNumber) {
      CRM_Core_Session::setStatus('Expected '.$endTransmission->getNumberOfRecords().' records got '.$lineNumber);
      return false;
    }

    if ($endTransmission->getNumberOfTransactions() != $transactionCount) {
      CRM_Core_Session::setStatus('Expected '.$endTransmission->getNumberOfTransactions().' transactions got '.$transactionCount);
      return false;
    }
    if ($endTransmission->getTotalAmount() != $transmissionTotalAmount) {
      return false;
    }
    if (!$doesContainAFullAssignment) {
      return false;
    }

    $this->assignments[$file_path] = $assignments;

    return true;
  }



  /**
   * Import the given file
   *
   * @return TODO: data format?
   */
  function import_file($file_path, $params) {
    // Get the assignments
    $assignments = $this->assignments[$file_path];
    $transactionCount = 0;
    foreach($assignments as $assignment) {
      $transactionCount = $transactionCount + count($assignment->getTransactions());
    }

    $this->reportProgress(0.0, "Importing ".$transactionCount." transactions.");

    // now create <$count> entries
    foreach($assignments as $assignment) {
      $batchReference = '';
      if ($assignment->getStartTransmissionRecord()) {
        $batchReference = $assignment->getStartTransmissionRecord()->getTransmissionNumber().'-'.$assignment->getAssignmentNumber();
      }

      // create batch
      $this->openTransactionBatch();
      $ba_id = $this->getBankAccountId($assignment->getAssignmentAccount(), true);
      $this->_current_transaction_batch->reference = $batchReference;
      $this->_current_transaction_batch_attributes['references'] = $batchReference;

      $transactions = $assignment->getTransactions();
      $i=1;
      foreach($transactions as $transaction) {
        if ($transaction instanceof CRM_Ocrimporter_Record_AmountItem1) {
          $amountInOre = $transaction->getTransactionAmount();
          $amountInNRK = ($amountInOre === 0 ? 0.00 : ($amountInOre / 100));
          $parity_ba_id = '';
          if ($transaction->getAmountItem2()) {
            $debitorAccount = $transaction->getAmountItem2()->getDebitAccount();
            // Convert debitor account into bank account id when bank account already exist in the system.
            $debitorAccount = $this->getBankAccountId($debitorAccount, false);
            if ($debitorAccount) {
              $parity_ba_id = $debitorAccount;
            }
          }

          $btx = array(
            'version' => 3,
            'amount' => $amountInNRK,
            'bank_reference' => $batchReference.'-'.$transaction->getKid(),
            'value_date' => $transaction->getNetsDate()->format('YmdHis'),
            'booking_date' => $transaction->getNetsDate()->format('YmdHis'),
            'currency' => 'NOK',
            'type_id' => 0, // @TODO lookup the financial type id
            'status_id' => 0, // @TODO lookup the contribution status id
            'data_raw' => $transaction->getRawData(),
            'data_parsed' => json_encode($transaction->getParsedData()),
            'ba_id' => $ba_id,
            'party_ba_id' => $parity_ba_id,
            'tx_batch_id' => NULL,
            'sequence' => $transaction->getTransactionNumber(),
          );

          // and finally write it into the DB
          $this->checkAndStoreBTX($btx, $i / $transactionCount, $params);
        } elseif ($transaction instanceof CRM_Ocrimporter_Record_StandingOrder) {
          $date = '';
          if ($assignment->getStarttransmissionRecord()->getEndTransmissionRecord()) {
            $date = $assignment->getStarttransmissionRecord()->getEndTransmissionRecord()->getNetsDate()->format('YmdHis');
          }
          $btx = array(
            'version' => 3,
            'amount' => 0,
            'bank_reference' => $batchReference.'-'.$transaction->getKid(),
            'value_date' => $date,
            'booking_date' => $date,
            'currency' => 'NOK',
            'type_id' => 0, // @TODO lookup the financial type id
            'status_id' => 0, // @TODO lookup the contribution status id
            'data_raw' => $transaction->getRawData(),
            'data_parsed' => json_encode($transaction->getParsedData()),
            'ba_id' => $ba_id,
            'party_ba_id' => '',
            'tx_batch_id' => NULL,
            'sequence' => $transaction->getTransactionNumber(),
          );

          // and finally write it into the DB
          $this->checkAndStoreBTX($btx, $i / $transactionCount, $params);
        }
        $i++;
      }
      $this->closeTransactionBatch();
    }

    $this->reportDone();

  }

  /**
   * Try to find a bank account if $createNewOne is set to true a new Bank Account is created
   * for contact with ID 1 (Default organisation)
   *
   * @ToDo Make the default organisation configurable
   *
   * @param $bank_account
   * @param bool $createNewOne
   * @return array
   */
  private function getBankAccountId($bank_account, $createNewOne=false) {
    try {
      $ba_id = civicrm_api3('BankingAccountReference', 'getvalue', array('return' => 'ba_id', 'reference' => $bank_account));
      return $ba_id;
    } catch (Exception $e) {
      // Do nothing
    }

    if ($createNewOne) {
      $ba_params['contact_id'] = 1; // Default Organisation
      $ba_params['data_parsed'] = json_encode(array(
        'name' => $bank_account
      ));
      $ba_params['data_raw'] = $bank_account;
      $ba_params['description'] = $bank_account;
      $result = civicrm_api3('BankingAccount', 'create', $ba_params);

      $ba_ref_params['ba_id'] = $result['id'];
      $ba_ref_params['reference'] = $bank_account;
      $ba_ref_params['reference_type_id'] = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => 'id',
        'name' => 'ocr',
        'option_group_id' => 'civicrm_banking.reference_types'
      ));
      civicrm_api3('BankingAccountReference', 'create', $ba_ref_params);
      return $result['id'];
    }

    return false;
  }

  /**
   * Test if the configured source is available and ready
   *
   * @var
   * @return bool
   */
  function probe_stream($params) {
    return false;
  }

  /**
   * Import from the configured source
   *
   * @return bool
   */
  function import_stream($params) {
    return false;
  }

  /**
   * Returns an array of CRM_Ocrimporter_Record_StartAssignments for a certain file.
   * @param $file_path
   * @return mixed
   */
  public function getAssignments($file_path) {
    return $this->assignments[$file_path];
  }

}