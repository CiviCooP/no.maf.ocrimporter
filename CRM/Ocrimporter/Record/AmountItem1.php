<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_AmountItem1 extends CRM_Ocrimporter_Record implements CRM_Ocrimporter_Record_Transaction{

  private $transactionType;
  private $transactionNumber;
  private $netsDate;
  private $centreId;
  private $dayCode;
  private $partialSettlementCode;
  private $serialNumber;
  private $sign;
  private $amount;
  private $signedAmount;
  private $kid;

  /**
   * @var CRM_Ocrimporter_Record_AmountItem2
   *  Link to the amount item 2 object.
   */
  private $amountItem2 = false;

  /**
   * @var CRM_Ocrimporter_Record_AmountItem3
   *  Link to the amount item 3 object.
   */
  private $amountItem3 = false;

  /**
   * @var CRM_Ocrimporter_Record_StartAssignment
   */
  private $startAssignmentRecord;

  public function __construct($line) {
    parent::__construct($line);

    $this->transactionType = substr($line, 4, 2);
    $this->transactionNumber = (int) substr($line, 8, 7);
    $this->netsDate = substr($line, 15, 6);
    $this->centreId = (int) substr($line, 21 ,2);
    $this->dayCode = (int) substr($line,23 ,2);
    $this->partialSettlementCode = (int) substr($line,25 ,1);
    $this->serialNumber = (int) substr($line,26 , 5);
    $this->sign = substr($line,31 ,1);
    $this->amount = (int) substr($line,32 ,17);
    $this->signedAmount = (int) substr($line, 31, 18);
    $this->kid = ltrim(substr($line, 49, 25));
  }

  public function getRawData() {
    $rawData = $this->getRecordLine();
    if ($this->amountItem2) {
      $rawData .= "\n" . $this->amountItem2->getRecordLine();
    }
    if ($this->amountItem3) {
      $rawData .= "\n" . $this->amountItem3->getRecordLine();
    }
    return $rawData;
  }

  /**
   * Returns an array with all the parsed data.
   *
   * @return array
   */
  public function getParsedData() {
    $data = parent::getParsedData();
    $data['transactionType'] = $this->transactionType;
    $data['transactionNumber'] = $this->transactionNumber;
    $data['netsDate'] = $this->netsDate;
    $data['centreId'] = $this->centreId;
    $data['dayCode'] = $this->dayCode;
    $data['partialSettlementCode'] = $this->partialSettlementCode;
    $data['serialNumber'] = $this->serialNumber;
    $data['sign'] = $this->sign;
    $data['amount'] = $this->getAmount();
    $data['kid'] = $this->kid;

    if ($this->amountItem2) {
      $data['formNumber'] = $this->amountItem2->getFormNumber();
      $data['agreementIdOrArchiveReference'] = $this->amountItem2->getAgreementIdOrArchiveReference();
      $data['bankDate'] = $this->amountItem2->getBankDate()->format('dmy');
      $data['debitAccount'] = $this->amountItem2->getDebitAccount();
    }
    if ($this->amountItem3) {
      $data['textMessage'] = $this->amountItem3->getTextMessage();
    }

    return $data;
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  public function getTransactionNumber() {
    return $this->transactionNumber;
  }

  public function getNetsDate() {
    $d = substr($this->netsDate, 0, 2);
    $m = substr($this->netsDate, 2, 2);
    $y = substr($this->netsDate, 4, 2);
    return new DateTime('20'.$y.'-'.$m.'-'.$d);
  }

  public function getCentreId() {
    return $this->centreId;
  }

  public function getDayCode() {
    return $this->dayCode;
  }

  public function getPartialSettlementCode() {
    return $this->partialSettlementCode;
  }

  public function getSerialNumber() {
    return $this->serialNumber;
  }

  public function getSign() {
    if ($this->sign == '-') {
      return '-';
    } else {
      return '';
    }
  }

  /**
   * Returns the total amount in Ore
   * This returns always a positive number.
   *
   * @return int
   */
  public function getAmount() {
    return $this->amount;
  }

  /**
   * Returns the total amount in Ore
   * If the transaction is a credit transaction then the amount is prefixed with
   * a - (minus) sign.
   *
   * @return int
   */
  public function getSignedAmount() {
    return $this->signedAmount;
  }

  /**
   * Returns the total amount in ore of the transaction.
   * If the transaction is a credit transaction the total amount is negative.
   *
   * @return int
   */
  public function getTransactionAmount() {
    return $this->getSignedAmount();
  }

  public function getKid() {
    return ltrim($this->kid);
  }

  public function setAmountItem2(CRM_Ocrimporter_Record_AmountItem2 $record) {
    $this->amountItem2 = $record;
  }

  public function getAmountItem2() {
    return $this->amountItem2;
  }

  public function setAmountItem3(CRM_Ocrimporter_Record_AmountItem3 $record) {
    $this->amountItem3 = $record;
  }

  public function getAmountItem3() {
    return $this->amountItem3;
  }

  /**
   * @return CRM_Ocrimporter_Record_StartAssignment
   */
  public function getStartAssignmentRecord() {
    return $this->startAssignmentRecord;
  }

  /**
   * Sets the start assignment record.
   *
   * @param \CRM_Ocrimporter_Record_StartAssignment $record
   * @return mixed
   */
  public function setStartAssignmentRecord(CRM_Ocrimporter_Record_StartAssignment $record) {
    $this->startAssignmentRecord = $record;
  }

}