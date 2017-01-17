<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_EndTransmission extends CRM_Ocrimporter_Record {

  private $numberOfTransactions;

  private $numberOfRecords;

  private $totalAmount;

  public function __construct($line) {
    parent::__construct($line);
    $this->numberOfTransactions = (int) substr($line, 8, 8);
    $this->numberOfRecords = (int) substr($line, 16, 8);
    $this->totalAmount = (int) substr($line, 24, 17);
  }

  public function getNumberOfTransactions() {
    return $this->numberOfTransactions;
  }

  public function getNumberOfRecords() {
    return $this->numberOfRecords;
  }

  /**
   * Returns the total amount in Ore
   *
   * @return int
   */
  public function getTotalAmount() {
    return $this->totalAmount;
  }

}