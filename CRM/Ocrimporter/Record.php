<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Ocrimporter_Record {

  protected $recordType;

  /**
   * @var string
   *  The actual record in the file which is basicly the file.
   */
  protected $recordLine;

  public function __construct($line) {
    $this->recordType = substr($line, 6, 2);
    $this->recordLine = str_replace(array("\r", "\n"), "", $line);
  }

  /**
   * Gets a record
   *
   * @param $line
   * @return bool|\CRM_Ocrimporter_Record_StartTransmission
   */
  public static function getRecord($line) {
    $recordType = substr($line, 6, 2);
    switch ($recordType) {
      case '10':
        return new CRM_Ocrimporter_Record_StartTransmission($line);
        break;
      case '89':
        return new CRM_Ocrimporter_Record_EndTransmission($line);
        break;
      case '20':
        return new CRM_Ocrimporter_Record_StartAssignment($line);
        break;
      case '88':
        return new CRM_Ocrimporter_Record_EndAssignment($line);
        break;
      case '30':
        return new CRM_Ocrimporter_Record_AmountItem1($line);
        break;
      case '31':
        return new CRM_Ocrimporter_Record_AmountItem2($line);
        break;
      case '32':
        return new CRM_Ocrimporter_Record_AmountItem3($line);
        break;
      case '70':
        return new CRM_Ocrimporter_Record_StandingOrder($line);
        break;
    }
    return false;
  }

  public function getRecordType() {
    return $this->recordType;
  }

  public function getRecordLine() {
    return $this->recordLine;
  }

}