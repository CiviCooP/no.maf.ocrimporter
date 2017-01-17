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
    return $this->getAssignmentNumber();
  }

  public function getAssignmentAccount() {
    return $this->getAssignmentAccount();
  }

  public function addTransaction(CRM_Ocrimporter_Record_Transaction $transaction) {
    $this->transactions[] = $transaction;
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
    for($i=0; $i < count($this->transactions); $i++) {
      $total += $this->transactions[$i]->getTransactionAmount();
    }
    return $total;
  }



}