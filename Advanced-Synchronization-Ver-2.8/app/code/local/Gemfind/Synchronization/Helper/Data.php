<?php 
	Class Gemfind_Synchronization_Helper_Data extends Mage_Core_Helper_Abstract {
		/**
		*	Function: Send response to JC about status of product import
		*
		*	@param: $FileID - Process file Id
		*	@param:	$Type - Process type
		*	@param: $Errors - Collection of occurred errors - Not using now but for further reference.
		*	@param: $Warnings - Collection of occurred warnings - Not using now but for further reference.
		*	@param: $ProductCount - Product count
		*
		*	Return - Log status of send result.
		*
		**/
		public function sendProcessResponse($FileID, $Type, $ProductCount, $Errors=array(), $Warnings=array()) {			
			try {
				$ResponseText = 'Total Products Processed = ' . $ProductCount . '\n\n\n\n';
				// When errors occur
				if(count($Errors) > 0) {
					$ResponseText .= 'Errors : \n\n';
					foreach($Errors as $ErrorMessage) {
						$ResponseText .= $ErrorMessage . '\n';
					}
				}
				// When warnnigs occur
				if(count($Warnings) > 0) {
					$ResponseText .= 'Warnings : \n\n';
					foreach($Warnings as $WarningMessage) {
						$ResponseText .= $WarningMessage . '\n';
					}
				}
				// JC reference path
				$client = new SoapClient("http://www.gemfind.net/JewelryCsvHistory.asmx?WSDL",
							array(
							  "trace"      => 1,		
							  "exceptions" => 0,		
							  "cache_wsdl" => 0) 		
							);
				
				$ResponseXML = $client->UpdateHistory(array("HistoryID" => $FileID, "Type" => $Type, "Status" => '200', "Response" => $ResponseText));
				if($ResponseXML->UpdateHistoryResult == '1') {
					Mage::log('History result updated to JC successfully.', null, 'gemfind_csv.log');
					//return true;
				} else {
					Mage::log('History result failed to send JC.', null, 'gemfind_csv.log');
					//return false;
				}
				
			} catch (Exception $e) {
				Mage::log('JC response process exception - '.$e->getMessage(), null, 'gemfind_csv.log');
				//return false;
			}
		}
	}
