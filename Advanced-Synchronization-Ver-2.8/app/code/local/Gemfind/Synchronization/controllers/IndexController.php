<?php
	/**
	* GemFind
	*
	* NOTICE OF LICENSE
	*
	* This source file is subject to the Open Software License (OSL 3.0)
	* that is bundled with this package in the file LICENSE.txt.
	* It is also available through the world-wide-web at this URL:
	* http://opensource.org/licenses/osl-3.0.php
	* If you did not receive a copy of the license and are unable to
	* obtain it through the world-wide-web, please send an email
	* to license@magentocommerce.com so we can send you a copy immediately.
	*
	* DISCLAIMER
	*
	* Do not edit or add to this file if you wish to upgrade Magento to newer
	* versions in the future. If you wish to customize Magento for your
	* needs please refer to http://www.magentocommerce.com for more information.
	*
	* @category    GemFind
	* @package     Gemfind_Synchronization
	* @copyright   Copyright (c) 2013 Prashant Latkar
	* @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
	*/
	class Gemfind_Synchronization_IndexController extends Mage_Core_Controller_Front_Action
	{
		/**
		*	Set predispatch even while on unauthorize request
		**/
		public function preDispatch() {  
			parent::preDispatch();
			//(Uncomment below source code if want to run process through URL)
			/*$token = $this->getRequest()->getParam("token"); // Token must needed to run sync
			$enable = Mage::getStoreConfig('synchronization/basic/enable'); // Is module enable?
			
			if ($token != "gemfind" || !$enable) {
				$this->setFlag('', 'no-dispatch', true);
				$this->norouteAction();
				return;
			}*/
			
			$baseDir = $this->getRequest()->getParam("basedir"); // Base Directory return
			if ($baseDir == 'true') {
				echo Mage::getBaseDir();
				exit;
			}
			
			$version = $this->getRequest()->getParam("version"); // Base Directory return
			if ($version == 'true') {
				echo "Version - 2.5";
				exit;
			}

			// Disable module through URL access (Comment below source code if want to run process through URL)
			$this->setFlag('', 'no-dispatch', true);
			$this->norouteAction();
			return;
			
		}
		
		/**
		 * Main Action
		 */
		public function indexAction() {			
			// Run synchronization process
			$synchronization = Mage::getModel("synchronization/synchronization");
			$synchronization->runSynchronization();
			
			$this->loadLayout();
			$this->renderLayout();
		}
	}