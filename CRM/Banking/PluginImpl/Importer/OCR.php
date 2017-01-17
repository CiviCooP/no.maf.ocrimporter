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
      }
      elseif ($record instanceof CRM_Ocrimporter_Record_StartAssignment) {
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
          return false;
        }

        if ($endAssignment->getNumberOfTransactions() != count($startAssignment->getTransactions())) {
          return false;
        }

        if ($endAssignment->getTotalAmount() != $startAssignment->getTotalTransactionAmount()) {
          return false;
        }

        $doesContainAFullAssignment = true;
      } elseif ($record instanceof CRM_Ocrimporter_Record_AmountItem1) {
        if ($startAssignment) {
          $startAssignment->addTransaction($record);
        }
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
      return false;
    }

    if ($endTransmission->getNumberOfTransactions() != $transactionCount) {
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
      // create batch
      $this->openTransactionBatch();

      $transactions = $assignment->getTransactions();
      for($i=0; $i<count($transactions); $i++) {
        $transaction = $transactions[$i];

        if ($transaction instanceof CRM_Ocrimporter_Record_AmountItem1) {
          $amountInOre = $transaction->getTransactionAmount();
          $amountInNRK = ($amountInOre === 0 ? 0.00 : ($amountInOre / 100));


          $btx = array(
            'version' => 3,
            'amount' => $amountInNRK,
            'bank_reference' => $transaction->getKid(),
            'value_date' => $transaction->getNetsDate()->format('YmdHis'),
            'booking_date' => $transaction->getNetsDate()->format('YmdHis'),
            'currency' => 'NRK',
            'type_id' => 0, // @TODO lookup the financial type id
            'status_id' => 0, // @TODO lookup the contribution status id
            'data_raw' => $transaction->getRawData(),
            'data_parsed' => json_encode($transaction->getParsedData()),
            'ba_id' => '',
            'party_ba_id' => '',
            'tx_batch_id' => NULL,
            'sequence' => $i,
          );

          // and finally write it into the DB
          $duplicate = $this->checkAndStoreBTX($btx, ($i / $transactionCount), $params);
        }
      }
      $this->closeTransactionBatch();
    }

    $this->reportDone();

  }

  /**
   * Test if the configured source is available and ready
   *
   * @var
   * @return TODO: data format?
   */
  function probe_stream($params) {
    return false;
  }

  /**
   * Import from the configured source
   *
   * @return TODO: data format?
   */
  function import_stream($params) {
    return false;
  }

}