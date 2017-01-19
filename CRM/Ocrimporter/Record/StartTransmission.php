<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record_StartTransmission extends CRM_Ocrimporter_Record {

  private $transmissionNumber;

  private $dataRecipient;

  /**
   * @var CRM_Ocrimporter_Record_StartTransmission
   *  The end transmission record for this start transmission.
   */
  private $endTransmissionRecord;

  public function __construct($line) {
    parent::__construct($line);
    $this->transmissionNumber = substr($line, 16, 7);
    $this->dataRecipient = substr($line, 23, 8);
  }

  public function getTransmissionNumber() {
    return $this->transmissionNumber;
  }

  public function getDataRecipient() {
    return $this->dataRecipient;
  }

  public function setEndTransmissionRecord(CRM_Ocrimporter_Record_EndTransmission $endTransmissionRecord) {
    $this->endTransmissionRecord = $endTransmissionRecord;
  }

  public function getEndTransmissionRecord() {
    return $this->endTransmissionRecord;
  }

}