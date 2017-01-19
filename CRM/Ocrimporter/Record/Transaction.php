<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */


interface CRM_Ocrimporter_Record_Transaction {

  /**
   * Returns the total amount in ore of the transaction.
   * If the transaction is a credit transaction the total amount is negative.
   *
   * @return int
   */
  public function getTransactionAmount();

  /**
   * Returns an array with all the parsed data.
   *
   * @return array
   */
  public function getParsedData();

  /**
   * Returns the line(s) from the OCR file which contain this transaction.
   *
   * @return string
   */
  public function getRawData();

  /**
   * @return int the number of the current transaction.
   */
  public function getTransactionNumber();

  /**
   * @return CRM_Ocrimporter_Record_StartAssignment
   */
  public function getStartAssignmentRecord();

  /**
   * Sets the start assignment record.
   *
   * @param \CRM_Ocrimporter_Record_StartAssignment $record
   * @return mixed
   */
  public function setStartAssignmentRecord(CRM_Ocrimporter_Record_StartAssignment $record);

}