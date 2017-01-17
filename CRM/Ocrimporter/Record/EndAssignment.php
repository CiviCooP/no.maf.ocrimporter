<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_EndAssignment extends CRM_Ocrimporter_Record {

  private $numberOfTransactions;

  private $totalAmount;

  private $netsDate;

  private $earliestNetsDate;

  private $latestNetsDate;


  public function __construct($line) {
    parent::__construct($line);

    $this->numberOfTransactions = (int) substr($line, 8, 8);
    $this->numberOfRecords = (int) substr($line, 16, 8);
    $this->totalAmount = (int) substr($line, 24, 17);
    $this->netsDate = substr($line, 41, 6);
    $this->earliestNetsDate = substr($line, 47, 6);
    $this->latestNetsDate = substr($line, 53, 6);
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

  public function getNetsDate() {
    $d = substr($this->netsDate, 0, 2);
    $m = substr($this->netsDate, 2, 2);
    $y = substr($this->netsDate, 4, 2);
    return new DateTime('20'.$y.'-'.$m.'-'.$d);
  }

  public function getEarliestNetsDate() {
    $d = substr($this->earliestNetsDate, 0, 2);
    $m = substr($this->earliestNetsDate, 2, 2);
    $y = substr($this->earliestNetsDate, 4, 2);
    return new DateTime('20'.$y.'-'.$m.'-'.$d);
  }

  public function getLatestNetsDate() {
    $d = substr($this->latestNetsDate, 0, 2);
    $m = substr($this->latestNetsDate, 2, 2);
    $y = substr($this->latestNetsDate, 4, 2);
    return new DateTime('20'.$y.'-'.$m.'-'.$d);
  }

}