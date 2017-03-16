<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * A Standing Order record is used to updated / stop AvtaleGiro's. The standing order is used
 * to transfer information which the donor has gaven to the bank to our system.
 */
class CRM_Ocrimporter_Record_StandingOrder extends CRM_Ocrimporter_Record implements CRM_Ocrimporter_Record_Transaction{

  private $transactionNumber;
  private $registrationType;
  private $kid;
  private $wantsNotification;

  /**
   * @var CRM_Ocrimporter_Record_StartAssignment
   */
  private $startAssignmentRecord;

  public function __construct($line) {
    parent::__construct($line);
    $this->transactionNumber = (int) substr($line, 8, 7);
    $this->registrationType = (int) substr($line, 15, 1);
    $this->kid = ltrim(substr($line, 16 ,25));
    $this->wantsNotification = substr($line, 41, 1);

  }

  public function getTransactionNumber() {
    return $this->transactionNumber;
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
    $data = parent::getParsedData();
    $data['transaction_number'] = $this->transactionNumber;
    $data['registrationType'] = $this->registrationType;
    $data['registrationTypeExplanation'] = $this->getRegistrationExplanation();
    $data['kid'] = $this->kid;
    $data['wantsNotification'] = $this->wantsNotification;

    return $data;
  }

  /**
   * Registration Type could be one of
   *  0 = All AvtaleGiro's are valid (all recurring contributions are valid)
   *  1 = Something is changed on the AvtaleGiro (which is possibly wants notification or not
   *  2 = Dondor has stopped the AvtaleGiro we should also cancel the recurring contributions.
   * @return int
   */
  public function getRegistrationType() {
    return $this->registrationType;
  }

  public function getRegistrationExplanation() {
    switch ($this->registrationType) {
      case 0:
        return 'Alle faste betalingsoppdrag tilknyttet betalingsmottakers avtale'; // Dutch (google translate): Alle betaaalopdrachten in verband overeenkomst begunstigde
        break;
      case 1:
        return 'Nye /endrede faste betalingsoppdrag'; //Dutch (google translate): Nieuwe / gewijzigde betaalopdracht
        break;
      case 2:
        return 'Slettede faste betalingsoppdrag'; // Dutch (google translate): Verwijderde vaste betalingsoppdrag
        break;
    }
    return '';
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