<?php
class Gemfind_LocalSynchronization_Helper_Synchronization_Customization extends Gemfind_Synchronization_Helper_Customization {
	/**
	* 
	* @param array $Product
	* @return array modified $Product
	*/
	public function customizeProductData($Product=null, $product_object=null, $directory=null) {
	$enable = Mage::getStoreConfig("synchronization/customization/enable");
	if ($enable) {
		try {
		// Sample Code here
		/* if (isset($Product["sku"])) {
			$Product["sku"] = "abc";
		} */
		}
		catch (Exception $e) {
			$product_object->_product_warnings[] = "Failed to apply customization changes. ".$e->getMessage();
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("productsync","Failed to apply customization changes. ".$e->getMessage(), $directory);
			Mage::log("Failed to apply customization changes. ".$e->getMessage(), null, 'gemfind_csv.log');
		}
	}
	return $Product;
	}
}
		