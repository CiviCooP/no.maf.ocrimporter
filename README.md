# OCR Importer

OCR is a Norwegian bank file format and this extensions adds an importer for
that file format to CiviBanking.

## Documentation of OCR

You can find the documentation of OCR at https://www.nets.eu/no-nb/losninger/inn-og-utbetalinger/ocrgiro/Dokumentasjon%20OCR%20Giro/Pages/default.aspx

##Requirements

* CiviCRM version 4.7
* CiviBanking (https://github.com/Project60/org.project60.banking)

## How the importer works

The OCR file contains of several lines where a transaction is made up of at least one line but up to three lines.
A set of transactions is wrapped up in an *assignment* and *assignments*  are wrapped within one *transmission*

The importer convert each line to a record (which is a subclass of *CRM_Ocrimporter_Record*) and this records are later handled to convert them into Transactions for CiviBanking.

The importer has two main function probe_file and import_file. The probe_file function will
convert the OCR file into a set of *assignments* and eacht *assignment* contains the transactions.

A transaction consists of *CRM_Ocrimporter_Record_StandingOrder* or a *CRM_Ocrimporter_Record_AmountItem1*.
The latter one could be linked to *CRM_Ocrimporter_Record_AmountItem2* and *CRM_Ocrimporter_Record_AmountItem3*

## Unit Tests

The following test classes are providerd

* *tests/phpunit/CRM/Ocrimporter/Test.php* - This class tests the importer functionality by feeding several OCR files into the importer. Some OCR files should fail because of bad content in the file and some should succeed. The test class will assert whether this happens.

**Running the tests**

Setup an test environment with [buildkit](https://github.com/civicrm/civicrm-buildkit)
Open a terminal and change to the directory of this extension and then type the following command:

    phpunit4 tests/phpunit/CRM/Yourmodule/TestClassForTestingSomethingUser.php


