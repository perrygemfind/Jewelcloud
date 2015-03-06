<?php 
	Class Gemfind_Synchronization_Model_Category extends Mage_Catalog_Model_Category_Api {		
		// Comman error message variable
		public $_category_errors = array();
		/**
		 * Initiate category object
		 * 
		 * @param store id
		 * @return object - category
		*/
		public function initiateCategory($store_id) {
			return Mage::getModel('catalog/category')->setStoreId($this->_getStoreId($store_id));
		}
		
		/**
		 * Set default category entity type id
		 *
		 * @return int - entity type id
		 */
		public function getEntityTypeId() {
			return Mage::getModel('eav/entity')->setType('catalog_category')->getTypeId();
		}
		
		/**
		 * Create/Update categories
		 *
		 * @param array - category data		 
		 * @return category ids along with product sku
		*/
		public function categorySync($category_sync_data = null, $directory) {
			// Return category data
			$return_category_data = array();
						
			// Default parameters
			$level = Mage::getStoreConfig("synchronization/category/default_level");
			$is_active = Mage::getStoreConfig("synchronization/category/category_status");
			$include_in_menu = Mage::getStoreConfig("synchronization/category/include_in_menu");
			$sort_by = Mage::getStoreConfig("synchronization/category/sort_by");
			$page_layout = Mage::getStoreConfig("synchronization/category/page_layout");
			$is_anchor = Mage::getStoreConfig("synchronization/category/is_anchor");
			
			// Default store id
			$store_id = Mage_Catalog_Model_Category::DEFAULT_STORE_ID;
			
			// Default parent id
			$parent_id = Mage::getStoreConfig("synchronization/category/default_parent");
			if (!$parent_id) {
				if ($store_id) {
					$parent_id = Mage::app()->getStore($store_id)->getRootCategoryId();
				}
				else {
					$parent_id = Mage::getStoreConfig("synchronization/category/default_parent");
				}
			}
			
			//Default category parameters
			$category = $this->initiateCategory($store_id);
			
			// Category entity type id
			$category_entity_type_id = $this->getEntityTypeId();
			
			if (isset($category_sync_data["CategoryName"]) && !empty($category_sync_data["CategoryName"])) {
				// Collect default category parameters
				$category_data = array('is_active' => $is_active, /*'position' => 1,*/ 'attribute_set_id' => $category->getDefaultAttributeSetId(),
				'entity_type_id' => $category_entity_type_id, 'updated_at' => now(), 'available_sort_by' => array($sort_by),
				'custom_design' => null, 'custom_apply_to_products' => null, 'custom_design_from' => null,
				'custom_design_to' => null, 'custom_layout_update' => null, 'default_sort_by' => $sort_by,
				'display_mode' => null, 'is_anchor' => $is_anchor, 'landing_page' => null, /*'meta_description' => '',
				'meta_keywords' => '', 'meta_title' => '',*/ 'page_layout' => $page_layout, 'url_key' => '',
				'include_in_menu' => $include_in_menu,);
				
				// Manage categories according to their levels - First Level
				$cat_name_level1 = explode(",",$category_sync_data["CategoryName"]);				
				foreach (array_unique($cat_name_level1) as $cat_name_level2) {
					$refresh_parent_id = $parent_id;
					$actual_cat_level = $level;
					
					// Second level of category
					$explode_level1 = explode("/",$cat_name_level2);
					
					foreach ($explode_level1 as $key => $level_cat_name) {						
						$category = Mage::getResourceModel('catalog/category_collection')
						->addAttributeToSelect('entity_id')
						->addAttributeToFilter('name', array('eq' => $level_cat_name))
						->addAttributeToFilter('parent_id', array('eq' => $refresh_parent_id))
						->addAttributeToFilter('level', array('eq' => $actual_cat_level));
						
						// Get category and set name
						$category_id_data = $category->getData();						
						if (is_array($category_id_data) && !empty($category_id_data)) {							
							foreach ($category_id_data as $ret_cat_data) {							
								$category_id = $ret_cat_data['entity_id'];								
								$category_data["level"] = $ret_cat_data['level'];
								$collect_category_ids[] = $category_id;
								$refresh_parent_id = $category_id;								
							}
						}
						else { // New category	
							Mage::log("New Category: SKU - ".$category_sync_data['sku'].": Category -: ".$category_data["name"], null, 'gemfind_csv.log');
							$category_data["name"] = $level_cat_name;
							$category_data["description"] = "";//$level_cat_name;
							try {
								$category_data['created_at'] = now();
								$category_data["level"] = $key + $level;
								// Get current position from db
								$current_position = $this->getHightCategoryPosition($category_data["level"]);
								$category_data["position"] = empty($current_position) ? 0 : $current_position+1;								
								$recent_category_id = $this->create($refresh_parent_id, $category_data, $store_id);
								$refresh_parent_id = $recent_category_id;
								$collect_category_ids[] = $recent_category_id;
							}
							catch (Exception $e) {
								$this->_category_errors[] = "Failed to add new category. ". $e->getMessage() ." Sku: ".$category_sync_data["sku"];
							}
						}																							
						$actual_cat_level++;	
					}					
				}
				// Collection of category against their SKU
				$return_category_data[$category_sync_data["sku"]] = implode(",",array_unique($collect_category_ids));
				// End of log
				/*$category_logs = Mage::getModel('Gemfind_Synchronization_Helper_Debug')
				->syncCategoryBinding($category_sync_data["sku"],$category_sync_data["CategoryName"], "Success", $directory, $error_type);*/
			}
			else {
				// TODO - Write log here about empty category field
				$category_logs = Mage::getModel('Gemfind_Synchronization_Helper_Debug')
				->syncCategoryBinding($category_sync_data["sku"],$category_sync_data["CategoryName"], "Empty", $directory, "error");
				$this->_category_errors[] = "Empty category name. Sku: ".$category_sync_data["sku"];
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Category Import");
			}
			
			// Return category ids with respective product sku
			return $return_category_data;
		}
		
		/**
		*	Function to set product count from category.
		*/
		public function setCategoryProductCount() {
			try {
				$connW = Mage::getSingleton('core/resource')->getConnection('core_write');
				$category_table = Mage::getSingleton("core/resource")->getTableName("catalog_category_product");
				$product_table = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity");			
				$sql = " DELETE FROM ". $category_table ." WHERE product_id NOT IN (SELECT entity_id FROM (". $product_table .")) ";
				$connW->query($sql);
			}
			catch (Exception $e) {
				// log notification 
				Mage::log("Failed to refine count of category-product assignment. ".$e->getMessage(), null, 'gemfind_csv.log');
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Set category product count");
			}
		}
		
		/**
		*	Function to get highest position of category based on level.
		*/
		public function getHightCategoryPosition($level) {
			try {
				$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
				$table_name = Mage::getSingleton("core/resource")->getTableName("catalog_category_entity");
				$sql = " SELECT max(position) FROM ". $table_name ." WHERE level = '". $level ."'  ";
				$position = $conn->fetchOne($sql);
				return $position;
			}
			catch (Exception $e) {
				// log notification 
				Mage::log("Failed to find max position of category. ".$e->getMessage(), null, 'gemfind_csv.log');
				return false;
			}
			
		}
	}
	