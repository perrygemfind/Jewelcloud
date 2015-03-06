<?php
/**
 * Gemfind Synchronization Observer
 *
 * @category   Gemfind
 * @package    Gemfind_Synchronization
 * @author     Gemfind Team
 */
class Gemfind_Synchronization_Model_Observer
{
    public function runGemFindSync() {
    	// Run synchronization process
    	$synchronization = Mage::getModel("synchronization/synchronization");
    	$synchronization->runSynchronization();
    }
}
