<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_StandingOrder extends CRM_Ocrimporter_Record implements CRM_Ocrimporter_Record_Transaction{

  private $runNumber;
  private $registrationType;
  private $kid;
  private $wantsNotification;

  public function __construct($line) {
    parent::__construct($line);
    $this->runNumber = (int) substr($line, 8, 7);
    $this->registrationType = (int) substr($line, 15, 1);
    $this->kid = substr($line, 16 ,25);
    $this->wantsNotification = substr($line, 41, 1);

  }

  public function getRawData() {
    $rawData = $this->getRecordLine();
    return $rawData;
  }

  /**
   * Returns an array with all the parsed data.
   *
   * @return array
   */
  public function getParsedData() {
    $data = array();
    $data['runNumber'] = $this->runNumber;
    $data['registrationType'] = $this->registrationType;
    $data['kid'] = $this->kid;
    $data['wantsNotification'] = $this->wantsNotification;
    return $data;
  }

  public function getRunNumber() {
    return $this->runNumber;
  }

  public function getRegistrationType() {
    return $this->registrationType;
  }

  public function getKid() {
    return ltrim($this->kid);
  }

  public function getWantsNotification() {
    if ($this->wantsNotification == 'J') {
      return true;
    }
    return false;
  }

  /**
   * Returns the total amount in ore of the transaction.
   * If the transaction is a credit transaction the total amount is negative.
   *
   * @return int
   */
  public function getTransactionAmount() {
    return 0;
  }

}