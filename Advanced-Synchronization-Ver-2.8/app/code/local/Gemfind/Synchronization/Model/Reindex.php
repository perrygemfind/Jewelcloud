<?php 
	Class Gemfind_Synchronization_Model_Reindex {
	
		protected $_indexlist="catalog_product_attribute,catalog_product_price,catalog_product_flat,catalog_category_flat,catalog_category_product,cataloginventory_stock,catalog_url,catalogsearch_fulltext,tag_summary";
		
		/**
		 * Initiate category object
		 * 
		 * @param store id
		 * @return object - category
		*/
		public function afterImport() {
			//$this->fixFlat();
			//$this->OptimEav();
			Mage::log("running indexer", null, 'gemfind_csv.log');
			$this->updateIndexes();
			$this->flushCache();
			return true;
		}
		
		public function resourceObject() {
			return Mage::getSingleton('core/resource')->getConnection('core_read');
		}
		
		public function OptimEav() {
			$tables=array("catalog_product_entity_varchar",
						   "catalog_product_entity_int",
						   "catalog_product_entity_text",
						   "catalog_product_entity_decimal",
						   "catalog_product_entity_datetime",
						   "catalog_product_entity_media_gallery",
						   "catalog_product_entity_tier_price");
			$cpe = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity");			
			Mage::log("Optmizing EAV Tables...", null, 'gemfind_csv.log');
			foreach($tables as $t) {
				Mage::log("Optmizing $t....", null, 'gemfind_csv.log');
				$sql = "DELETE ta.* FROM ". Mage::getSingleton("core/resource")->getTableName($t) ." as ta
				LEFT JOIN $cpe as cpe on cpe.entity_id=ta.entity_id 
				WHERE ta.store_id=0 AND cpe.entity_id IS NULL";
				$this->resourceObject()->query($sql);
				Mage::log("$t optimized", null, 'gemfind_csv.log');
			}	
		}
		
		public function fixFlat() {
			Mage::log("Cleaning flat tables before reindex...", null, 'gemfind_csv.log');
			$stmt = "SHOW TABLES LIKE '". Mage::getSingleton("core/resource")->getTableName('catalog_product_flat') ."%'";
			$rows = $this->resourceObject()->fetchAll($stmt);
			
			while($row = $this->resourceObject()->fetchAll($stmt)) {
				$tname = $row[0];
				//removing records in flat tables that are no more linked to entries in catalog_product_entity table
				//for some reasons, this seem to happen
				$sql = "DELETE cpf.* FROM $tname as cpf
				LEFT JOIN ". Mage::getSingleton("core/resource")->getTableName('catalog_product_entity') ." as cpe ON cpe.entity_id=cpf.entity_id 
				WHERE cpe.entity_id IS NULL";
				//$this->resourceObject()->query($sql); 				
			}
			
		}
		
		public function getIndexList() {
			return $this->_indexlist;
		}
		
		public function updateIndexes() {			
			Mage::log("Stared update reindexing...", null, 'gemfind_csv.log');
			$cl = "php ". Mage::getBaseDir() . DS ."shell/indexer.php";
			$idxlstr = $this->getIndexList();
			$idxlist = explode(",",$idxlstr);
			
			if(count($idxlist)==0)
			{
				Mage::log("No indexes selected , skipping reindexing...", null, 'gemfind_csv.log');
				return true;
			}
			foreach($idxlist as $idx) {
				$tstart = microtime(true);
				Mage::log("Reindexing $idx....", null, 'gemfind_csv.log');
				
				// Execute Reindex command, and specify that it should be ran from Magento directory
				$out = shell_exec($cl." --reindex $idx");
				Mage::log($out, null, 'gemfind_csv.log');
				$tend = microtime(true);
				Mage::log("done in ". round($tend-$tstart,2) ." secs", null, 'gemfind_csv.log');				
				flush();
			}
		}
		
		public function flushCache() {
			Mage::log("running Cache Cleanup", null, 'gemfind_csv.log');
			Mage::app()->getCacheInstance()->flush();
			Mage::log("Finished Cache Cleanup", null, 'gemfind_csv.log'); 
		}
	}
	