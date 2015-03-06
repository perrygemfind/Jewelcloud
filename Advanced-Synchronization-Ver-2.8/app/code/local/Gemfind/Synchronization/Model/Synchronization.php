<?php
	Class Gemfind_Synchronization_Model_Synchronization extends Mage_Core_Model_Abstract {
		/**
		* Our process ID.
		*/
		const PROCESS_ID = 'sync_process';
		
		/**
		* Mage_Index_Model_Process will provide us a lock file API.
		*
		* @var Mage_Index_Model_Process $indexProcess
		*/
		protected $indexProcess;
		
		/**
		 * Constructor.  Instantiate the Process model, and set our custom
		 * batch process ID.
		 */
		public function __construct()
		{
			// TODO...
			$lock = Mage::getStoreConfig('synchronization/lock/enable');
			if ($lock) {
				$this->indexProcess = new Mage_Index_Model_Process();
				$this->indexProcess->setId(self::PROCESS_ID);
			}			
		}
		
		/**
		 * To read perticular file and send read data
		 * @param string $file_path
		 * @return array read csv data
		 */
		public function getCSV($file_path, $xml_node="", $current_dir="") {
			try {
				$csv = Mage::getModel("synchronization/csv");
				$csv_data = $csv->csvLoad($file_path, $xml_node, $current_dir);
			}
			catch (Exception $e) {			
				// Print log
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($xml_node,'Failed to collect csv file data.', $current_dir);
				Mage::log('Failed to collect csv file data.', null, 'gemfind_csv.log');				
			}
			return $csv_data;
		}
		
				
		/**
		 * Reindexing of all aspect
		 */
		public function reindexAll($dir_array) {
			try {				
				// Write reindexing progress start status to each progress file				
				if (!empty($dir_array)) {
					$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
					
					if (is_array($dir_array)) {
						$dir_array = array_unique($dir_array);
						// Add node to show indexing started	
						foreach ($dir_array as $dir_name) {
							$progress_file = $dir_name."_progress.xml";																
							$progress_file_path = $path."/". $progress_file;
							if (file_exists($progress_file_path)) { 
								$xmlProgressDoc = new DOMDocument();
								$xmlProgressDoc->load($progress_file_path);
								// All stop tiime to xml log file
								$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
								$progress_start_at = $xmlProgressDoc->createElement("Reindex_Start");
								$progress_start_at->appendChild(
												$xmlProgressDoc->createTextNode( time() )
										);		
								$progress_root->appendChild( $progress_start_at );
								$xmlProgressDoc->Save($progress_file_path);
								unset($xmlProgressDoc);
								unset($progress_file);
							}						
						}
									
						// Apply reindexing to all parameters
						$indexer = Mage::getModel("synchronization/reindex")->afterImport();
						
						// Add node to show indexing finished
						foreach ($dir_array as $dir_name) {
							$progress_file = $dir_name."_progress.xml";																
							$progress_file_path = $path."/". $progress_file;
							if (file_exists($progress_file_path)) { 
								$xmlProgressDoc = new DOMDocument();
								$xmlProgressDoc->load($progress_file_path);
								// All stop tiime to xml log file
								$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
								$progress_end_at = $xmlProgressDoc->createElement("Reindex_Finish");
								$progress_end_at->appendChild(
												$xmlProgressDoc->createTextNode( time() )
										);		
								$progress_root->appendChild( $progress_end_at );
								$xmlProgressDoc->Save($progress_file_path);
								unset($xmlProgressDoc);
								unset($progress_file);
							}						
						}
					}
					else {
						$progress_file = $dir_array."_progress.xml";																
						$progress_file_path = $path."/". $progress_file;
						if (file_exists($progress_file_path)) { 
							$xmlProgressDoc = new DOMDocument();
							$xmlProgressDoc->load($progress_file_path);
							// All stop time to xml log file
							$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
							$progress_start_at = $xmlProgressDoc->createElement("Reindex_Start");
							$progress_start_at->appendChild(
											$xmlProgressDoc->createTextNode( time() )
									);		
							$progress_root->appendChild( $progress_start_at );							
						}

						// Apply reindexing to all parameters
						$indexer = Mage::getModel("synchronization/reindex")->afterImport();
						
						if (file_exists($progress_file_path)) { 
							// All stop tiime to xml log file
							$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
							$progress_end_at = $xmlProgressDoc->createElement("Reindex_Finish");
							$progress_end_at->appendChild(
											$xmlProgressDoc->createTextNode( time() )
									);		
							$progress_root->appendChild( $progress_end_at );
							$xmlProgressDoc->Save($progress_file_path);
							unset($xmlProgressDoc);
							unset($progress_file);
						}
					}						
					Mage::log('Sync & Reindexing Finished \n', null, 'gemfind_csv.log');
				}				
			}
			catch (Exception $e) {
				$exp_error = $e->getMessage()." - Failure re-indexing.";
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')->emptyLogXML($directory_name, $exp_error);
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Reindexing All");
			}
			
		}
		
		/**
		*	Change reindex mode
		*/
		public function changeReindexMode($mode) {
			$reindex = Mage::getSingleton('index/indexer')->getProcessesCollection(); 
			foreach ($reindex as $process) {
				//$process->setMode(Mage_Index_Model_Process::MODE_MANUAL)->save();
				//$process->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();
				if ($mode == "MANUAL") {
					$process->setMode(Mage_Index_Model_Process::MODE_MANUAL)->save();
				}
				else {
					$process->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();
				}			  
			}			
		}
		
		/**
		*	Function to set sync data count
		*
		*	@param $node - name of node
		*	@param $row_number - count of sync data
		*/
		public function syncDataCount($node, $row_number, $directory) {			
			$set_data_count = Mage::getStoreConfig('synchronization/basic/data_sync');;
			if (is_numeric($row_number)) {
				$row_number += 1;
				if ($row_number%$set_data_count == 0) {					
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncDataCountLogXML($node,$row_number, $directory);
				}
			}
			else {
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncDataCountLogXML($node,$row_number, $directory);
			}			
		}
		
		/**
		 * Function to validate product data before import/update
		 * @param $product
		 *
		 * return: boolean - Valid data:True otherwise False.
		 */
		public function importProductValidation($product_data, $product) {
			if(empty($product_data['name'])) {
				$product->_product_errors[] = 'Name is Blank for SKU - ' . $product_data['sku'];
				return false;
			}			
			if(empty($product_data['description'])) {
				$product->_product_errors[] = 'Description is Blank for SKU - ' . $product_data['sku']; 
				return false;
			}
			if(empty($product_data['short_description'])) {
				$product->_product_errors[] = 'Short Description is Blank for SKU - ' . $product_data['sku'];
				return false;
			}
			if(empty($product_data['type'])) {
				$product->_product_errors[] = 'Product Type is Blank for SKU - ' . $product_data['sku'];
				return false;
			}
			if(empty($product_data['MainImage'])) {
				$product->_product_errors[] = 'Product Image is Blank for SKU - ' . $product_data['sku'];
				return false;
			}	
			return true;
		}
		
		/**
		 * Function to delete product
		 * 
		 * Product delete
		 * @param string $file_path
		 */
		public function productDelete($csv_data, $directory) {			
			if (count($csv_data) > 0) {
				// Create object of product
				$mage_product = Mage::getModel("catalog/product");
				foreach($csv_data as $row_number => $csv_row) {
					if ($csv_row['sku']) {
						$product_id = $mage_product->getIdBySku($csv_row['sku']);						
						if ($product_id) {
							$mage_product->load($product_id);
							try {
								Mage::register('isSecureArea', true);
								$del_status = $mage_product->delete();
								Mage::unregister('isSecureArea');								
							}
							catch (Exception $e) {
								$mage_product->_product_errors[] = "Failed to remove product. Sku: ".$csv_row["sku"];
							}
						}
						else {							
							$mage_product->_product_warnings[] = "Invalid Product. Sku: ".$csv_row["sku"];
						}											
					}
					else {
						$mage_product->_product_warnings[] = "Sku does not present -> ".$row_number;
					}
					// Create sync count node into log file
					$this->syncDataCount("proddelsync", $row_number, $directory);
					
					// Add profiler log here
					$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
					if ($profiler) {
						Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Product Delete");
					}					
				}
				return $mage_product;
			}			
		}
		
		/**
		 * Function to create product and respective data
		 * like - category, product details including inventory and price
		 * and product images.
		 * 
		 * @param string $file_path
		 * @param string $directory
		 */
		public function productCreate($csv_data, $directory) {
			// Create object of product
			$product = Mage::getModel("synchronization/product");
			
			// Get customization helper - When Customization require
			$customization = Mage::helper("synchronization/customization");
				
			if (count($csv_data) > 0) {
				// Run loop to read each row of csv file
				foreach($csv_data as $row_number => $csv_row) {
					// Apply customization attributes
					$csv_row = $customization->customizeSpecialCharData($csv_row, $product,$directory);
					$csv_row = $customization->customizeProductData($csv_row, $product,$directory); 
					/********** When Customization require *************/
					
					//@to-do : check sku at this point if not found then dont create category and product					
					if ($csv_row['sku'] != "") {						
						// Validate product data
						if ($this->importProductValidation($csv_row, $product)) {
							// Create product
							$product->productCreate($csv_row, $directory);
							
							// Create sync count node into log file
							$this->syncDataCount("productsync",$row_number, $directory);							
						}
						else {
							$mage_product = Mage::getModel("catalog/product");
							$product_id = $mage_product->getIdBySku($csv_row['sku']);						
							if ($product_id) {
								$mage_product->load($product_id);
								try {
									Mage::register('isSecureArea', true);
									$mage_product->delete();
									Mage::unregister('isSecureArea');								
								}
								catch (Exception $e) {
									Mage::log('Failed to delete product when mandatory parameters are empty/invalid.', null, 'gemfind_csv.log');
								}
							}							
						}
					}
					else {
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($csv_row['sku'], "Sku does not present", $directory, "error");
						$product->_product_warnings[] = "Sku not present -> Row Number: ".($row_number + 2);
					}					
				}				
			}
			return $product;				
		}
		
		/**
		 * Function to create product and respective data
		 * like - category, product details including inventory and price
		 * and product images.
		 * 
		 * @param string $file_path
		 * @param string $directory
		 */
		public function productPriceUpdate($csv_data, $directory) {			
			// Create object of product
			$product = Mage::getModel("synchronization/product");
			
			// Get customization helper - When Customization require
			$customization = Mage::helper("synchronization/customization");
				
			if (count($csv_data) > 0) {
				// Run loop to read each row of csv file
				foreach($csv_data as $row_number => $csv_row) {
					// Apply customization attributes
					$csv_row = $customization->customizeSpecialCharData($csv_row, $product,$directory);
					$csv_row = $customization->customizeProductData($csv_row, $product,$directory); 
					/********** When Customization require *************/
					
					//@to-do : check sku at this point if not found then dont create category and product
					if ($csv_row['sku'] != "") {
						// Import product pricing
						$product->productPriceUpdate($csv_row, $directory);
						
						// Create sync count node into log file
						$this->syncDataCount("productsync",$row_number, $directory);
					}
					else {
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($csv_row['sku'], "Sku does not present", $directory, "error");
						$product->_product_warnings[] = "Sku not present -> Row Number: ". ($row_number + 2);
					}
				}
			}
			return $product;
				
		}
	
		/**
		 * Function to initiate synchronization process
		 */
		public function runSynchronization() {					
			// Check module is enable or disabled
			$enable = Mage::getStoreConfig('synchronization/basic/enable');
			if ($enable) {								
				Mage::log('Sync Started', null, 'gemfind_csv.log');
				
				/***** Enable/Disable Magento system lock ******/
				$lock = Mage::getStoreConfig('synchronization/lock/enable');
				if ($lock) {
					if ($this->indexProcess->isLocked()) {
						// Todo - while on empty source folder
						Mage::log('Process Aborted.', null, 'gemfind_csv.log');
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')->abortLogXML();
						return;
					}
					
					// Set an exclusive lock and proceed				
					$this->indexProcess->lockAndBlock();
				}
				/***** Enable/Disable Magento system lock ******/
				
				// Path of csv file and destination
				$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/csv_path/path');				
				$destination = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/csv_path/imported');
				
				// Collect directories
				$get_directories = glob($path.'/*', GLOB_ONLYDIR);
				$collect_sync_logs = array();
				
				if (count($get_directories) > 0 ) { 		
					// Reach each individual directory including there sub-dir's
					$product_page_error = "";
					$errors = "";
					foreach($get_directories as $directory) {						
						$directory_name = str_replace($path.'/', '', $directory); // Appropriate directory name	
						$directory_tag_name = time()."_".$directory_name; // Tag name to create unique node	

						if (!file_exists($destination."/".$directory_name)) {
							$file = ""; // Define file name as empty							
							$file_list = glob($path.'/' . $directory_name . '/*.csv'); // Get list of available csv files
							$file_path = $path .'/'. $directory_name; // Make path of csv file
							
							// Start logging for current folder
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')->initiateLogXML($directory_tag_name);
						
							// Print name of directory in sync file
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')->collectionLogXML($directory_tag_name);
						
							//////////////////// 3:Delete inventory data ////////////////
							if(in_array( $file_path.'/gemfind_Delete_Inventory.csv', $file_list)) {
								$file = $file_path.'/gemfind_Delete_Inventory.csv';
								
								// Get csv data in array
								$csv_data = $this->getCSV($file,"proddelsync",$directory_tag_name);
				
								// Log count of csv file to log
								$count = count($csv_data);
								if ($count > 0) {
									// Print count of total product to progress file
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncProgressCountLogXML("delete", $count, $directory_tag_name);
									
									// Get call for delete method
									$product_delete = $this->productDelete($csv_data, $directory_tag_name);
									
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncCountLogXML("prod_del_count", $count, $directory_tag_name);
									// Create sync count node into log file
									$this->syncDataCount("proddelsync", "COMPLETE", $directory_tag_name);
									
									// Collect all errors here 								
									$errors = array_unique($product_delete->_product_errors);
									
									// Collect all warning here 								
									$warnings = "";//array_unique($product_delete->_product_warnings);
									
									// Send response to JC
									Mage::getModel('Gemfind_Synchronization_Helper_Data')->sendProcessResponse($directory_name,3,$count,$errors,$warnings);
								}
								else {
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("proddelsync","Empty CSV data - ". $directory_tag_name, $directory_tag_name);
								}
							}
							else {
								// Print count of total product to progress file
								Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncProgressCountLogXML("delete", 0, $directory_tag_name);
							}
						
							//////////////////// 1:All inventory data ////////////////////
							if(in_array($file_path .'/gemfind_All_Inventory.csv', $file_list)) {
								$file = $file_path.'/gemfind_All_Inventory.csv';
								
								// Get csv data in array
								$csv_data = $this->getCSV($file,"productsync",$directory_tag_name);
				
								// Log count of csv file to log
								$count = count($csv_data);
								if ($count > 0) {
									// JewelCloud previous sync remove
									$del_old_sync_data = Mage::getStoreConfig('synchronization/product/del_old_sync');
									if ($del_old_sync_data) {
										// Delete existing product from website before sync
										// Run loop to read each row of csv file
										foreach($csv_data as $row_number => $csv_row) {
											$products_on_file[] = $csv_row['sku'];
										}
										$product = Mage::getModel("synchronization/product");
										$products_on_website = $product->AvailableWebsiteProducts();
										$products_to_del = array_diff($products_on_website, $products_on_file);

										$prod_no_del = Mage::getStoreConfig('synchronization/product/prod_no_update');
										$skip_delete_sku = explode(",",$prod_no_del);
										
										foreach ($products_to_del as $del_product_sku) {
											if (!in_array($del_product_sku, $skip_delete_sku)) {
												$product->allInventoryProductRemove($del_product_sku, $directory_tag_name);
											}
										}
									}
									
									// Print total number of products
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncProgressCountLogXML("insert", $count, $directory_tag_name);
									
									// Create product
									$product_create = $this->productCreate($csv_data, $directory_tag_name);
									
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncCountLogXML("prod_insert_count", $count, $directory_tag_name);
									// Create sync count node into log file
									$this->syncDataCount("productsync","COMPLETE", $directory_tag_name);
									
									// Collect all errors here 								
									$errors = array_unique($product_create->_product_errors);
									
									// Collect all warning here 								
									$warnings = "";//array_unique($product_create->_product_warnings);
									
									// Send response to JC
									Mage::getModel('Gemfind_Synchronization_Helper_Data')->sendProcessResponse($directory_name,1,$count,$errors,$warnings);
								}
								else {
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("productsync","Empty CSV data - ". $directory_tag_name, $directory_tag_name);
								}
							}
							
							//////////////// 2:Updates inventory data //////////////////
							if(in_array($file_path .'/gemfind_Updates_Inventory.csv', $file_list)) {
								$file = $file_path.'/gemfind_Updates_Inventory.csv';
								
								// Get csv data in array
								$csv_data = $this->getCSV($file,"productsync",$directory_tag_name);
								
								// Log count of csv file to log
								$count = count($csv_data);
								if ($count > 0) {
									// Print total number of product count
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncProgressCountLogXML("insert", $count, $directory_tag_name);
									
									// Create product
									$product_create = $this->productCreate($csv_data, $directory_tag_name);
									
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncCountLogXML("prod_update_count", $count, $directory_tag_name);
									
									// Create sync count node into log file
									$this->syncDataCount("productsync", "COMPLETE", $directory_tag_name);
									
									// Collect all errors here 								
									$errors = array_unique($product_create->_product_errors);
									
									// Collect all warning here 								
									$warnings = "";//array_unique($product_create->_product_warnings);
									
									// Send response to JC
									Mage::getModel('Gemfind_Synchronization_Helper_Data')->sendProcessResponse($directory_name,2,$count,$errors,$warnings);
								}
								else {
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("productsync","Empty CSV data - ". $directory_tag_name, $directory_tag_name);
								}
							}
							
							/////////////// 4:Price inventory data ///////////////////
							if(in_array($file_path .'/gemfind_Prices_Inventory.csv', $file_list)) {
								$file = $file_path.'/gemfind_Prices_Inventory.csv';
								
								// Get csv data in array
								$csv_data = $this->getCSV($file,"productsync",$directory_tag_name);
								
								// Log count of csv file to log
								$count = count($csv_data);
								if ($count > 0) {
									// Create product
									$product_price = $this->productPriceUpdate($csv_data, $directory_tag_name);
									
									// Print total number of product count
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncProgressCountLogXML("insert", $count, $directory_tag_name);
									
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncCountLogXML("prod_price_count", $count, $directory_tag_name);
									// Create sync count node into log file
									$this->syncDataCount("productsync","COMPLETE", $directory_tag_name);
									
									// Collect all errors here 								
									$errors = array_unique($product_price->_product_errors);
									
									// Collect all warning here 								
									$warnings = "";//array_unique($product_price->_product_warnings);
									
									// Send response to JC
									Mage::getModel('Gemfind_Synchronization_Helper_Data')->sendProcessResponse($directory_name,4,$count,$errors,$warnings);
								}
								else {
									Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("productsync","Empty CSV data - ". $directory_tag_name, $directory_tag_name);
								}
							}
							
							// Stop logging for current folder
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')->stopLogXML($directory_tag_name);
							
							// Create imported folder to destination						
							mkdir($destination."/".$directory_name);
							
							// Maintain product count into category
							Mage::getModel("synchronization/category")->setCategoryProductCount();
							
							// log notification to finished of sync process
							Mage::log('Sync Finished - '. $directory .', reindexing started..........', null, 'gemfind_csv.log');
							
							// Reindex all the data
							$this->reindexAll($directory_tag_name);
							
							/***** Enable/Disable Magento system lock ******/
							if ($lock) {
								// Remove the lock.
								$this->indexProcess->unlock();
							}
							/***** Enable/Disable Magento system lock ******/
							// log notification to finished of sync process
							Mage::log("Product import process finished for - ".$directory_tag_name, null, "gemfind_csv.log");
							//echo "Product import process finished for - ".$directory_tag_name;
							break;
						}
						else {							
							// Todo - while on empty source folder 
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')->emptyLogXML($directory_name);
						}
					}
				}				
			}
		}
	}