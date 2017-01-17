<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_AmountItem3 extends CRM_Ocrimporter_Record {

  private $transactionType;
  private $transactionNumber;
  private $textMessage;

  /**
   * @var CRM_Ocrimporter_Record_AmountItem1
   *  Link to the amount item 2 object.
   */
  private $amountItem1 = false;

  public function __construct($line) {
    parent::__construct($line);
    $this->transactionType = substr($line, 4, 2);
    $this->transactionNumber = (int) substr($line, 8, 7);
    $this->textMessage = substr($line, 15, 40);
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  public function getTransactionNumber() {
    return $this->transactionNumber;
  }

  public function getTextMessage() {
    return $this->textMessage;
  }

  public function setAmountItem1(CRM_Ocrimporter_Record_AmountItem1 $record) {
    $this->amountItem1 = $record;
  }

  public function getAmountItem1() {
    return $this->amountItem1;
  }

}