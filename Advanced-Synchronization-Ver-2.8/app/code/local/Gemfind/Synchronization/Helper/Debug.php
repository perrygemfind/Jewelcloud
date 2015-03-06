<?php
class Gemfind_Synchronization_Helper_Debug extends Mage_Core_Helper_Url
{
	public function initiateLogXML($directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$file = $directory.".xml";
			$progress_file = $directory."_progress.xml";
	    	$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
	    	
	    	// Generate dynamic xml file path to store log
	    	$directory_path =  $path;
			// Create object to generate xml
			$xmlDoc = new DOMDocument('1.0', 'UTF-8');				
			// Create main sync element
			$syncElement = $xmlDoc->createElement("sync");
			$xmlDoc->appendChild($syncElement);
			
			// Process - Initiated At
			$start_at = $xmlDoc->createElement( "StartAt" );
			$start_at->appendChild(
					$xmlDoc->createTextNode( time() )
			);
			$syncElement->appendChild( $start_at );
			
			// Product sync node
			$productsync = $xmlDoc->createElement("productsync");
			$xmlDoc->appendChild($productsync);
			$syncElement->appendChild($productsync);
			
			// Category sync node
			$categorysync = $xmlDoc->createElement("categorysync"); 
			$xmlDoc->appendChild($categorysync);
			$syncElement->appendChild($categorysync);
			
			// Product image sync
			$prodimgsync = $xmlDoc->createElement("prodimgsync");
			$xmlDoc->appendChild($prodimgsync);
			$syncElement->appendChild($prodimgsync);
			
			// Product delete sync
			$proddelsync = $xmlDoc->createElement("proddelsync");
			$xmlDoc->appendChild($proddelsync);
			$syncElement->appendChild($proddelsync);
			
			// Save your xml log document
			$xmlDoc->Save($directory_path."/". $file);
			
			
			/********************************************/
			// Generate progress log file						
			$xmlProgress = new DOMDocument('1.0', 'UTF-8');				
			// Create main sync element
			$syncProgressElement = $xmlProgress->createElement("sync");
			$xmlProgress->appendChild($syncProgressElement);
			
			// Process - Initiated At
			$progress_start_at = $xmlProgress->createElement( "StartAt" );
			$progress_start_at->appendChild(
					$xmlProgress->createTextNode( time() )
			);
			$syncProgressElement->appendChild( $progress_start_at );
			
			// Product sync node
			$productProgresssync = $xmlProgress->createElement("productsync");
			$xmlProgress->appendChild($productProgresssync);
			$syncProgressElement->appendChild($productProgresssync);
			
			// Product sync node
			$proddelsyncProgresssync = $xmlProgress->createElement("proddelsync");
			$xmlProgress->appendChild($proddelsyncProgresssync);
			$syncProgressElement->appendChild($proddelsyncProgresssync);
			
			// Save your xml log document
			$xmlProgress->Save($directory_path."/". $progress_file);
			/************************************************/
			
			/************************************************/
			// Create synchronization.xml
			$sync_file = Mage::getStoreConfig('synchronization/gemfind_sync_log/sync_file');
			$sync_file_path = $directory_path."/". $sync_file;
			if (!file_exists($sync_file_path)) { // append existing xml file
				$xmlSyncDoc = new DOMDocument('1.0', 'UTF-8');				
				// Create main sync element
				$syncFileElement = $xmlSyncDoc->createElement("syncs");
				$xmlSyncDoc->appendChild($syncFileElement);
				// Save your xml log document
				$xmlSyncDoc->Save($directory_path."/". $sync_file);
			}
			/***********************************************/
		}
		return;
	}
	
	/**
	*	Function to stop log file
	*
	*	@param string $directory
	**/
	public function stopLogXML($directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$xmlDoc = new DOMDocument();
			$file = $directory.".xml";
			$progress_file = $directory."_progress.xml";
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
			$xmlDoc->load($xml_file_path);
			// All stop tiime to xml log file
			$root = $xmlDoc->getElementsByTagName("sync")->item(0);
			$stop_at = $xmlDoc->createElement("StopAt");
			$stop_at->appendChild(
							$xmlDoc->createTextNode( time() )
					);		
			$root->appendChild( $stop_at );
			$xmlDoc->Save($xml_file_path);
			
			// Stop progress log xml file
			$xmlProgressDoc = new DOMDocument();
			$progress_file_path = $path."/". $progress_file;
			$xmlProgressDoc->load($progress_file_path);
			// All stop tiime to xml log file
			$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
			$progress_stop_at = $xmlProgressDoc->createElement("StopAt");
			$progress_stop_at->appendChild(
							$xmlProgressDoc->createTextNode( time() )
					);		
			$progress_root->appendChild( $progress_stop_at );
			$xmlProgressDoc->Save($progress_file_path);
			
			
			
		}
	}
	
	/**
	*	Function to send sync count to xml log file
	*
	*	@param string $entity_text
	*	@param int $count
	*	@param string $directory
	**/
	public function syncCountLogXML($entity_text, $count, $directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$xmlDoc = new DOMDocument();
			$file = $directory.".xml";
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
			$xmlDoc->load($xml_file_path);
			// Count of csv to xml log file
			$root = $xmlDoc->getElementsByTagName("sync")->item(0);
			$entity = $xmlDoc->createElement($entity_text);
			$entity->appendChild(
							$xmlDoc->createTextNode( $count )
					);		
			$root->appendChild( $entity );
			$xmlDoc->Save($xml_file_path);
		}
	}
	
	/**
	*	Function to send sync count to xml log file
	*
	*	@param string $entity_text
	*	@param int $count
	*	@param string $directory
	**/
	public function syncProgressCountLogXML($entity_text, $count, $directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$xmlDoc = new DOMDocument();
			$file = $directory."_progress.xml";
			
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
			$xmlDoc->load($xml_file_path);
			// Count of csv to xml log file
			$root = $xmlDoc->getElementsByTagName("sync")->item(0);
			$entity = $xmlDoc->createElement($entity_text);
			$entity->appendChild(
							$xmlDoc->createTextNode( $count )
					);		
			$root->appendChild( $entity );
			$xmlDoc->Save($xml_file_path);
		}
	}
	
	/**
	*	Function to collect and append all logs
	*
	*	@param array $log_files
	**/
	public function collectionLogXML($log_files) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$file = Mage::getStoreConfig('synchronization/gemfind_sync_log/sync_file');
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
			
			if (file_exists($xml_file_path)) { // append existing xml file
				$xmlDoc = new DOMDocument();				
				$xmlDoc->load($xml_file_path);
				$root = $xmlDoc->getElementsByTagName("syncs")->item(0);
				if (is_array($log_files)) {
					foreach ($log_files as $log_file) {
						// Process - Initiated At
						$sync = $xmlDoc->createElement( "sync" );
						$sync->appendChild(
								$xmlDoc->createTextNode( $log_file )
						);
						$root->appendChild( $sync );
					}
				}
				else {					
					// Process - Initiated At
					$sync = $xmlDoc->createElement( "sync" );
					$sync->appendChild(
							$xmlDoc->createTextNode( $log_files )
					);
					$root->appendChild( $sync );
				
				}
				
				// Save your xml log document
				$xmlDoc->Save($xml_file_path);
			}
		}
	}
	
	/**
	*	Function to set log while on empty folders
	**/
	public function emptyLogXML($directory, $exception="") {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$file = Mage::getStoreConfig('synchronization/gemfind_sync_log/sync_file');
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
			
			
			if (file_exists($xml_file_path)) { // append existing xml file
				// Create object to generate xml
				$xmlDoc = new DOMDocument('1.0', 'UTF-8');
				$xmlDoc->load($xml_file_path);
				$root = $xmlDoc->getElementsByTagName("syncs")->item(0);
				// Process - Initiated At
				$sync = $xmlDoc->createElement( "error" );
				
				if (empty($exception)) {				
					$sync->appendChild(
							$xmlDoc->createTextNode( $directory." Folder is already imported." )
					);
				}
				else {
					$sync->appendChild(
							$xmlDoc->createTextNode( $exception )
					);
				}
				$root->appendChild( $sync );
			}
			else {
				// Create object to generate xml
				$xmlDoc = new DOMDocument('1.0', 'UTF-8');				
				// Create main sync element
				$syncElement = $xmlDoc->createElement("syncs");
				$xmlDoc->appendChild($syncElement);
				
				// Process - Initiated At
				$sync = $xmlDoc->createElement( "error" );
				if (empty($exception)) {
					$sync->appendChild(
							$xmlDoc->createTextNode( $directory." Folder is already imported." )
					);
				}
				else {
					$sync->appendChild(
							$xmlDoc->createTextNode( $exception )
					);
				}
				$syncElement->appendChild( $sync );
			}
			
			
			// Save your xml log document
			$xmlDoc->Save($xml_file_path);
		}
	}
	
	/**
	*	Function to set abort when already process running
	**/
	public function abortLogXML() {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$file = Mage::getStoreConfig('synchronization/gemfind_sync_log/abort_file');
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$xml_file_path = $path."/". $file;
						
			// Create object to generate xml
			$xmlDoc = new DOMDocument('1.0', 'UTF-8');				
			// Create main sync element
			$syncElement = $xmlDoc->createElement("syncs");
			$xmlDoc->appendChild($syncElement);
			
			// Process - Initiated At
			$sync = $xmlDoc->createElement( "error" );
			$sync->appendChild(
					$xmlDoc->createTextNode("Another process is running! Abort.")
			);
			$syncElement->appendChild( $sync );
			
			// Save your xml log document
			$xmlDoc->Save($xml_file_path);
		}
	}
	
	/**
	*	Function to set number of sync data	
	*/
	public function syncDataCountLogXML($node, $sync_count, $directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$progress_file_path = $path."/". $directory."_progress.xml";
			
			// Progress file path 
			if (file_exists($progress_file_path)) { // append existing xml file
				$xmlProgressDoc = new DOMDocument();				
				$xmlProgressDoc->load($progress_file_path);
				$progress_root = $xmlProgressDoc->getElementsByTagName($node)->item(0);
			
				// Create sub category element
    			$logElement = $xmlProgressDoc->createElement("log");
    			$xmlProgressDoc->appendChild($logElement);
    			$progress_root->appendChild( $logElement );
    			     			
				// Node -  Message
    			$xml_type = $xmlProgressDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlProgressDoc->createTextNode( "message" )
    			);
    			$logElement->appendChild( $xml_type );
				
				// Node -  Message
    			$timestamp = $xmlProgressDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlProgressDoc->createTextNode( time() )
    			);
    			$logElement->appendChild( $timestamp );
				
    			// Node -  Message
    			$message = $xmlProgressDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlProgressDoc->createTextNode( "SYNC_".$sync_count )
    			);
    			$logElement->appendChild( $message );
    			
				// Save your xml log document
				$xmlProgressDoc->Save($progress_file_path);
			}			
		}
	}
	
	
	/**
	*	Function trace reindexing progress
	*
	*	@param string $directory
	**/
	public function reindexingProgressXML($directory, $mode) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
		if ($logActive) {
			// Initilize xml object
			$xmlProgressDoc = new DOMDocument();
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$progress_file = $directory."_progress.xml";
			$progress_file_path = $path."/". $progress_file;
			$xmlProgressDoc->load($progress_file_path);
			// All stop tiime to xml log file
			$progress_root = $xmlProgressDoc->getElementsByTagName("sync")->item(0);
			$progress_mode_at = $xmlProgressDoc->createElement($mode);
			$progress_mode_at->appendChild(
							$xmlProgressDoc->createTextNode( time() )
					);		
			$progress_root->appendChild( $progress_mode_at );
			$xmlProgressDoc->Save($progress_file_path);			
		}
	}
	
	/**
	 * Function to generate log during category sync
	 *
	 * @param string $set_sku
	 * @param string $category
	 * @param string $message
	 * @param string $directory
	 */
    public function syncCategoryBinding($set_sku, $category, $msg, $directory, $type) {
    	try {
    		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');    		
    		if ($logActive) {
    			$file = $directory.".xml";
				$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
				$xml_file_path = $path."/". $file;
				$xmlDoc = new DOMDocument();				
    			$xmlDoc->load($xml_file_path);
    			 
    			// Refer main sync element    			
				$root = $xmlDoc->getElementsByTagName("categorysync")->item(0);
    			
    			// Create sub category element
    			$categoryElement = $xmlDoc->createElement("category");
    			$xmlDoc->appendChild($categoryElement);
    			$root->appendChild( $categoryElement );
    			 
    			// Node -  Sku
    			$sku = $xmlDoc->createElement( "sku" );
    			$sku->appendChild(
    					$xmlDoc->createTextNode( $set_sku )
    			);
    			$categoryElement->appendChild( $sku );
    			 
    			// Node -  Categories
    			$categories = $xmlDoc->createElement( "categories" );
    			$categories->appendChild(
    					$xmlDoc->createTextNode( $category )
    			);
    			$categoryElement->appendChild( $categories );
    			
				// Node -  Message
    			$xml_type = $xmlDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlDoc->createTextNode( $type )
    			);
    			$categoryElement->appendChild( $xml_type );
				
				// Node -  Message
    			/*$timestamp = $xmlDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlDoc->createTextNode( time() )
    			);
    			$categoryElement->appendChild( $timestamp );
				
    			// Node -  Message
    			$message = $xmlDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlDoc->createTextNode( $msg )
    			);
    			$categoryElement->appendChild( $message );*/
    			
    			// Save file with updated data
    			$xmlDoc->Save($xml_file_path);
    		}
    	}
    	catch (Exception $e) {
    	}
    }
    
	/**
	 * Function to generate log during category sync
	 *
	 * @param string $set_sku
	 * @param string $message
	 * @param string $directory
	 */
    public function syncProductBinding($set_sku,  $msg, $directory, $type) {    	 
    	try {
    		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
    
    		if ($logActive) {
    			$file = $directory.".xml";
				$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
				$xml_file_path = $path."/". $file;
				
				$xmlDoc = new DOMDocument();				
    			$xmlDoc->load($xml_file_path);
    
    			// Refer main sync element
    			$root = $xmlDoc->getElementsByTagName("productsync")->item(0);
    			 
    			// Create sub category element
    			$productElement = $xmlDoc->createElement("product");
    			$xmlDoc->appendChild($productElement);
    			$root->appendChild( $productElement );
    			
    			// Node -  Sku
    			$sku = $xmlDoc->createElement( "sku" );
    			$sku->appendChild(
    					$xmlDoc->createTextNode( $set_sku )
    			);
    			$productElement->appendChild( $sku );
        		
				// Node -  Type of message
    			$xml_type = $xmlDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlDoc->createTextNode( $type )
    			);
    			$productElement->appendChild( $xml_type );
				
				// Node -  Timestamp
    			/*$timestamp = $xmlDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlDoc->createTextNode( time() )
    			);
    			$productElement->appendChild( $timestamp );*/
				
    			// Node -  Message
    			$message = $xmlDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlDoc->createTextNode( $msg )
    			);
    			$productElement->appendChild( $message );
    			 
    			// Save file with updated data
    			$xmlDoc->Save($xml_file_path);
    		}
    	}
    	catch (Exception $e) {
    	}
    }
    
	/**
	 * Function to generate log during product del
	 *
	 * @param string $set_sku
	 * @param string $message
	 * @param string $directory
	 */
    public function delProduct($set_sku, $msg, $directory, $type) {
    	try {
    		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
    
    		if ($logActive) {
    			$file = $directory.".xml";
				$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
				$xml_file_path = $path."/". $file;
				$xmlDoc = new DOMDocument();				
    			$xmlDoc->load($xml_file_path);
    
    			// Refer main sync element
    			$root = $xmlDoc->getElementsByTagName("proddelsync")->item(0);
    			 
    			// Create sub category element
    			$productElement = $xmlDoc->createElement("product");
    			$xmlDoc->appendChild($productElement);
    			$root->appendChild( $productElement );
    			
    			// Node -  Sku
    			$sku = $xmlDoc->createElement( "sku" );
    			$sku->appendChild(
    					$xmlDoc->createTextNode( $set_sku )
    			);
    			$productElement->appendChild( $sku );
				
				// Node - Message type
    			$xml_type = $xmlDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlDoc->createTextNode( $type )
    			);
    			$productElement->appendChild( $xml_type );
				
				// Node -  Timestamp
    			/*$timestamp = $xmlDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlDoc->createTextNode( time() )
    			);
    			$productElement->appendChild( $timestamp );
				
    			// Node -  Message
    			$message = $xmlDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlDoc->createTextNode( $msg )
    			);
    			$productElement->appendChild( $message );*/
     
    			// Save file with updated data
    			$xmlDoc->Save($xml_file_path);
    		}
    	}
    	catch (Exception $e) {
    	}
    }
    
	/**
	 * Function to generate log during product image import
	 *
	 * @param string $set_sku
	 * @param string $message
	 * @param string $directory
	 */
    public function imgProduct($set_sku, $msg, $directory, $type) {
    	try {
    		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');
    
    		if ($logActive) {
    			$file = $directory.".xml";
				$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
				$xml_file_path = $path."/". $file;
				
    			
				$xmlDoc = new DOMDocument();				
    			$xmlDoc->load($xml_file_path);
    
    			// Refer main sync element
				$root = $xmlDoc->getElementsByTagName("prodimgsync")->item(0);
    
    			// Create sub category element
    			$productElement = $xmlDoc->createElement("product_image");
    			$xmlDoc->appendChild($productElement);
    			$root->appendChild( $productElement );
    
    			// Node -  Sku
    			$sku = $xmlDoc->createElement( "sku" );
    			$sku->appendChild(
    					$xmlDoc->createTextNode( $set_sku )
    			);
    			$productElement->appendChild( $sku );
				
				// Node -  Message Type
    			$xml_type = $xmlDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlDoc->createTextNode( $type )
    			);
    			$productElement->appendChild( $xml_type );
				
				
				// Node -  Timestamp
    			/*$timestamp = $xmlDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlDoc->createTextNode( time() )
    			);
    			$productElement->appendChild( $timestamp );
				
    			// Node -  Message
    			$message = $xmlDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlDoc->createTextNode( $msg )
    			);
    			$productElement->appendChild( $message );*/
    
    			// Save file with updated data
    			$xmlDoc->Save($xml_file_path);
    		}
    	}
    	catch (Exception $e) {
    	}
    }
	
	
	/**
	*	Function to set number of sync data	
	*/
	public function syncErrorCountLogXML($node, $text_message, $directory) {
		$logActive = Mage::getStoreConfig('synchronization/gemfind_sync_log/active');		
		if ($logActive) {
			$path = Mage::getBaseDir() . DS . Mage::getStoreConfig('synchronization/gemfind_sync_log/path');
			$progress_file_path = $path."/". $directory."_progress.xml";
			
			// Progress file path 
			if (file_exists($progress_file_path)) { // append existing xml file
				$xmlProgressDoc = new DOMDocument();				
				$xmlProgressDoc->load($progress_file_path);
				$progress_root = $xmlProgressDoc->getElementsByTagName($node)->item(0);
			
				// Create sub category element
    			$logElement = $xmlProgressDoc->createElement("log");
    			$xmlProgressDoc->appendChild($logElement);
    			$progress_root->appendChild( $logElement );
    			     			
				// Node -  Message
    			$xml_type = $xmlProgressDoc->createElement( "type" );
    			$xml_type->appendChild(
    					$xmlProgressDoc->createTextNode( "error" )
    			);
    			$logElement->appendChild( $xml_type );
				
				// Node -  Message
    			$timestamp = $xmlProgressDoc->createElement( "timestamp" );
    			$timestamp->appendChild(
    					$xmlProgressDoc->createTextNode( time() )
    			);
    			$logElement->appendChild( $timestamp );
				
    			// Node -  Message
    			$message = $xmlProgressDoc->createElement( "message" );
    			$message->appendChild(
    					$xmlProgressDoc->createTextNode( $text_message )
    			);
    			$logElement->appendChild( $message );
    			
				// Save your xml log document
				$xmlProgressDoc->Save($progress_file_path);
			}			
		}
	}
}