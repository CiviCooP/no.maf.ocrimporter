<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_StartAssignment extends CRM_Ocrimporter_Record {

  private $agreementId;

  private $assignmentNumber;

  private $assignmentAccount;

  private $transactions = array();

  /**
   * @var CRM_Ocrimporter_Record_EndAssignment
   *  The end assignment record belonging to this start transaction.
   */
  private $endAssignmentRecord = false;

  /**
   * @var CRM_Ocrimporter_Record_StartTransmission
   *  The start transmission record
   */
  private $startTransmissionRecord = false;

  public function __construct($line) {
    parent::__construct($line);

    $this->agreementId = (int) substr($line, 8, 9);
    $this->assignmentNumber = (int) substr($line, 17,7);
    $this->assignmentAccount = (int) substr($line, 24, 11);
  }

  public function getAgreementId() {
    return $this->agreementId;
  }

  public function getAssignmentNumber() {
    return $this->assignmentNumber;
  }

  public function getAssignmentAccount() {
    return $this->assignmentAccount;
  }

  public function setEndAssignmentRecord(CRM_Ocrimporter_Record_EndAssignment $record) {
    $this->endAssignmentRecord = $record;
  }

  public function getEndAssignmentRecord() {
    return $this->endAssignmentRecord;
  }

  public function setStartTransmissionRecord(CRM_Ocrimporter_Record_StartTransmission $record) {
    $this->startTransmissionRecord = $record;
  }

  public function getStartTransmissionRecord() {
    return $this->startTransmissionRecord;
  }

  public function addTransaction(CRM_Ocrimporter_Record_Transaction $transaction) {
    $this->transactions[$transaction->getTransactionNumber()] = $transaction;
  }

  public function getTransactions() {
    return $this->transactions;
  }

  /**
   * Returns the total amount in ore of all the transactions in this assignment
   *
   * @return int
   */
  public function getTotalTransactionAmount() {
    $total = 0;
    foreach($this->transactions as $transaction) {
      $total += $transaction->getTransactionAmount();
    }
    return $total;
  }



}