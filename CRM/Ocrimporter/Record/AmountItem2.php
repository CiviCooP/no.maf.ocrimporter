<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_AmountItem2 extends CRM_Ocrimporter_Record {

  private $transactionType;
  private $transactionNumber;
  private $formNumber;
  private $agreementIdOrArchiveReference;
  private $bankDate;
  private $debitAccount;

  /**
   * @var CRM_Ocrimporter_Record_AmountItem1
   *  Link to the amount item 1 object.
   */
  private $amountItem1 = false;

  public function __construct($line) {
    parent::__construct($line);
    $this->transactionType = substr($line, 4, 2);
    $this->transactionNumber = (int) substr($line, 8, 7);
    $this->formNumber = (int) substr($line, 15, 10);
    $this->agreementIdOrArchiveReference = substr($line, 25, 9);
    $this->bankDate = substr($line, 41,6);
    $this->debitAccount = substr($line, 47,11);
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  public function getTransactionNumber() {
    return $this->transactionNumber;
  }

  public function getBankDate() {
    $d = substr($this->bankDate, 0, 2);
    $m = substr($this->bankDate, 2, 2);
    $y = substr($this->bankDate, 4, 2);
    return new DateTime('20'.$y.'-'.$m.'-'.$d);
  }

  public function getFormNumber() {
    return $this->formNumber;
  }

  public function getAgreementIdOrArchiveReference() {
    return $this->agreementIdOrArchiveReference;
  }

  public function getDebitAccount() {
    return $this->debitAccount;
  }

  public function setAmountItem1(CRM_Ocrimporter_Record_AmountItem1 $record) {
    $this->amountItem1 = $record;
  }

  public function getAmountItem1() {
    return $this->amountItem2;
  }

}