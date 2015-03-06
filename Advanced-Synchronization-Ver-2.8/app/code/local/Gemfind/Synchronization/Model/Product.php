<?php 
	Class Gemfind_Synchronization_Model_Product extends Mage_Catalog_Model_Product_Api {
	
		// Current image folder path 
		protected $img_directory_root = "";
		
		// Image uplaod directory name
		protected $image_upload_path = "";
		
		// Sku prefix
		protected $csv_sku_prefix = "";
		
		// Collection of parent sku's of child product
		protected $_attributesSkuArray = array();
		
		// Collection of all errors
		public $_product_errors = array();
		
		// Collection of all warnings
		public $_product_warnings = array();
		
		public function __construct() {
			$this->image_upload_path = Mage::getStoreConfig("synchronization/product/media");
			$this->img_directory_root = Mage::getBaseDir() .DS."media".DS."catalog".DS."product".DS.$this->image_upload_path;
			$this->csv_sku_prefix = Mage::getStoreConfig("synchronization/product/csv_sku_prefix");
			unset($this->_product_errors);
			unset($this->_product_warnings);
		}
		
		/**
		 * Function to create media folder
		 * 
		 * @param string $sku
		 * @return boolean
		 */
		public function createMediaFolder($sku) {
			if (!file_exists($this->img_directory_root)) {
				if (!mkdir($this->img_directory_root, 0777)) {
					// TODO - Log here - Folder does not exist or permission denied
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->imgProduct($sku,  "Media folder does not exist/permission denied", $log, "error");					
				}
			} 
			return;
		}
		
		/**
		 * Get available products on website
		 * 
		 * @param varchar $sku
		 * @param object $directory
		 * @return array products on website
		*/
		public function AvailableWebsiteProducts() {
			try {
				$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
				$table_name = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity");
				if ($this->csv_sku_prefix) {
					$sql = 'SELECT entity_id, sku FROM ' . $table_name . ' WHERE sku LIKE \'' . $this->csv_sku_prefix . '%\'';
				}
				else {
					$sql = 'SELECT entity_id, sku FROM ' . $table_name . '';
				}
				$available_skus = $conn->fetchAll($sql);
				foreach ($available_skus as $product) {
					$products_on_website[] = $product['sku'];
				}
				return $products_on_website;			
			} 
			catch (Exception $e) {
				Mage::log('Failed to determine available website products', null, 'gemfind_csv.log');				
			}
		}
		
		/**
		 * Remove product from database before sync all inventory
		 * 
		 * @param in $product_id
		 * @param object $log
		 * @return object $directory
		*/
		public function allInventoryProductRemove($sku, $directory) {
			try { 
				$_product = Mage::getModel('catalog/product');
				$_product->load($_product->getIdBySku($sku));
				
				$prod_del_flag = true;
				// Category id's to avoid product to delete
				$prod_to_del_cat = Mage::getStoreConfig("synchronization/category/cat_prod_no_del");
				if (!empty($prod_to_del_cat)) {
					$category_id = explode(",",$prod_to_del_cat);
					$category_ids = $_product->getCategoryIds();
					foreach($category_ids as $cat_id) {
						if (in_array($cat_id, $category_id)) {
							$prod_del_flag = false; // set flag false to avoid product from delete
						}
					}
				}	
				
				if ($prod_del_flag) { // when flag is true, then only product get delete
					Mage::register('isSecureArea', true);
					$del_status = $_product->delete();
					Mage::unregister('isSecureArea');
					if ($del_status) {
						// log notification to finished of deletion of product
						Mage::log($sku.'Product deleted.', null, 'gemfind_csv.log');
					}
				}				
			}
			catch (Exception $e) {
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->delProduct($sku,  "Product failed to remove", $directory, "error");
				$this->_product_warnings[] = "All inventory products failed to remove. Sku: ".$sku;
				Mage::log("All inventory products failed to remove. Sku: ".$sku, null, 'gemfind_csv.log');
			}
		}
		
		/**
		 * Function - Delete existing images from system
		 * 
		 * @param string $id - product id
		 * @return boolean 
		 */
		protected function removeExistingImages($id) {
			// TODO
			$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
			//$connW = Mage::getSingleton('core/resource')->getConnection('core_write');
			// Prepare for product images
			$tableName = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity_media_gallery");
			$tableName_gallery_value = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity_media_gallery_value");
			
			try { // Remove existing images
				$sql = " SELECT * FROM ". $tableName ." WHERE entity_id = '". $id ."' ";
				$_galleryImgs = $conn->fetchAll($sql);
				if (!empty($_galleryImgs)) {
					$media_path = Mage::getBaseDir() .DS."media".DS."catalog".DS."product";
					foreach ($_galleryImgs as $g_img) {
						//Mage::log("Value Id - ".$g_img['value_id']." : Value - ".$g_img['value'].": Sku - ".$sku, null, 'gemfind_csv.log');							
						if (file_exists($media_path_file_name)) {
							unlink($media_path_file_name);
						}
					}
				}
				// To remove unwanted database entries
				$del_gal_value = "DELETE FROM ". $tableName_gallery_value ." WHERE value_id IN (SELECT value_id FROM ". $tableName ." WHERE entity_id =  '". $id ."') ";
				$conn->query($del_gal_value);
				$del = "DELETE FROM ". $tableName ." WHERE entity_id =  '". $id ."' ";
				$conn->query($del);	
			}
			catch (Exception $e) {
				$this->_product_warnings[] = "Error while deleting existing media gallery images. - Sku: ".$sku;
				Mage::log("Error while deleting existing media gallery image - ".$sku, null, 'gemfind_csv.log');
				return false;
			}			
			return true;
		}
		
		
		/**
		 * Function - Copy/Upload image from remote location
		 * 
		 * @param string $image_url
		 * @param string $image_name
		 * @return boolean 
		 */
		protected function copyImage($image_url, $image_name) {
			try {
				//echo $ImageURL . PHP_EOL;
				$image_url = str_replace(" ", "%20", $image_url);
				$ch = curl_init ($image_url);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
				$rawdata = curl_exec($ch);
				
				
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if($httpCode == 404) {
					return false;
				}
				curl_close ($ch);
				
				$destination_name = urldecode(basename($image_url));
				if($image_name != '') {
					$destination_name = $image_name;
				}
				// Remove already exist image
				if(file_exists($this->img_directory_root .DS. $destination_name)) {
					unlink($this->img_directory_root .DS. $destination_name);
				}
				$fp = fopen($this->img_directory_root .DS. $destination_name,'x');
				fwrite($fp, $rawdata);
				fclose($fp);
				return true;
			} catch (Exception $e) {
				Mage::log('Image not able to copied from -: '.$image_url, null, 'gemfind_csv.log');
				return false;
			}
		}
		
		/**
		 * Function to set media gallery for product images
		 * 
		 * @param int $id
		 * @param string $image_name
		 * @param string $label
		 * @param int $position
		 * @param int $disabled
		 * @reeturn - boolean
		 */
		function setMediaGallery($directory, $id, $sku, $image_name, $label, $position=1, $disabled=0,$additional=1) {
			$connW = Mage::getSingleton('core/resource')->getConnection('core_write');
				
			// Default store id
			$store_id = Mage_Catalog_Model_Category::DEFAULT_STORE_ID;
				
			$entity_type = Mage::getModel('eav/config')->getEntityType('catalog_product');
			$entity_type_id = $entity_type->getEntityTypeId();
		
			$attribute = Mage::getResourceModel('eav/entity_attribute_collection')
			->setCodeFilter('media_gallery')
			->setEntityTypeFilter($entity_type_id)
			->getFirstItem();
			
			// Tables name.
			$tableName = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity_media_gallery");
			$tableName_gallery_value = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity_media_gallery_value");
			$tableName_catalog_varchar = Mage::getSingleton("core/resource")->getTableName("catalog_product_entity_varchar");
			
			try { // Insert new images
				$insert = " INSERT INTO ". $tableName ." "
						." SET "
						." attribute_id = '". $attribute->getAttributeId() ."', "
								." entity_id = '". $id ."' , "
						
										." value = '". $image_name ."' ";
				$connW->query($insert);
				$last_insert_id = $connW->lastInsertId();
				if ($last_insert_id) {
					
					// Insert new entries of gallery value
					$insert_gallery = "INSERT INTO ". $tableName_gallery_value ."
								SET
									value_id = '". $last_insert_id ."',
									store_id = '". $store_id ."',
									label = '". $label ."',
									position = '". $position ."',
									disabled = '". $disabled ."'
									";
					$connW->query($insert_gallery);
				}
				if ($position == 1) {
					// To update images based on appropriate store id
					$attribute_image = Mage::getSingleton("eav/config")->getAttribute('catalog_product', "image")->getData(); 
					$attribute_small_image = Mage::getSingleton("eav/config")->getAttribute('catalog_product', "small_image")->getData(); 
					$attribute_thumb = Mage::getSingleton("eav/config")->getAttribute('catalog_product', "thumbnail")->getData(); 
					$attribute_gallery = Mage::getSingleton("eav/config")->getAttribute('catalog_product', "media_gallery")->getData();
					$update_images = "UPDATE ". $tableName_catalog_varchar ." 
										SET 
										value = '". $image_name ."'
										WHERE attribute_id IN (".$attribute_image["attribute_id"].",".$attribute_small_image["attribute_id"].",".$attribute_thumb["attribute_id"].",".$attribute_gallery["attribute_id"].") 
										AND store_id = '". $store_id ."'
										AND entity_id = ".$id;
					$connW->query($update_images);
				}
			}
			catch (Exception $e) {
				$this->_product_warnings[] = "Error while inserting media gallery images. - Sku: ".$sku;
				Mage::log("Error while inserting media gallery image - ".$sku, null, 'gemfind_csv.log');
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Product Media Gallery");
			}
			return;			
		}
		
		/**
		 * Function to set product related data
		 * 
		 * @param array $product_data
		 * @param object $log
		 * @return boolean
		 */
		public function syncProductImage($product_data, $directory) {
			//MainImage, AdditionalImages, ImagePath
			$_product = Mage::getModel('catalog/product');
			$_product->load($_product->getIdBySku($product_data['sku'])); 
			
			if ($_product->getId()) {	
				// Remove existing product images
				$this->removeExistingImages($_product->getId());
				// Check media folder status otherwise create
				$this->createMediaFolder($product_data['sku']);
								
				// process image from source to destination				
				$data = array();
				// Add require data
				$data["id"] = $_product->getId();
				$data["image"] = "/".$this->image_upload_path."/".urldecode(basename($product_data["MainImage"]));
				$data["image_label"] = $_product->getName();
				$data["small_image"] = "/".$this->image_upload_path."/".urldecode(basename($product_data["MainImage"]));
				$data["small_image_label"] = $_product->getName();
				$data["thumbnail"] = "/".$this->image_upload_path."/".urldecode(basename($product_data["MainImage"]));
				$data["thumbnail_label"] = $_product->getName();
				// Make entry for main image to media galler table
				$this->setMediaGallery($directory, $data["id"], $product_data['sku'], $data["image"], $_product->getName(),1,0,1);
				
				// Insert additional images to media gallery
				if ($product_data["AdditionalImages"] != "") {
					$exp_additional_img = explode(",",$product_data["AdditionalImages"]);
					$pos = 2;
					foreach ($exp_additional_img as $img_value) {
						$add_img_status = $this->copyImage($img_value, urldecode(basename($img_value)));
						if ($add_img_status) {
							$img_name = "/".$this->image_upload_path."/".urldecode(basename($img_value));
							$this->setMediaGallery($directory, $data["id"], $product_data['sku'], $img_name, $_product->getName(), $pos,0,0);
							// Additioanl image success log
							/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
							->imgProduct($product_data["sku"],  "Additional image successfully inserted", $directory, "message");*/
						}
						else {
							// TODO - Additional image does not downloaded
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')
							->imgProduct($product_data["sku"],  "Invalide Additional Source Image", $directory, "error");
							$this->_product_warnings[] = "Invalid Additional Image. Sku: ".$product_data["sku"];
						}
						$pos++;
					}
				}
				else {
					$this->_product_warnings[] = "Additional image is blank. Sku: ".$product_data["sku"];
				}
				
				try {
					$_product->load($data['id'])->addData($data);
					$_product->setId($data['id'])->save();
					/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->imgProduct($product_data["sku"],  "Image successfully inserted", $directory, "message");*/
				}
				catch (Execption $e) {
					// TODO - Handle exception here
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->imgProduct($product_data["sku"],  "Unable to insert image data", $directory, "error");	
					$this->_product_warnings[] = "Failed to insert image. Sku: ".$product_data["sku"];
				}				
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Product Media Gallery");
			}			
		}
		
		/**
		 * Function to create/update product details
		 * 
		 * @param array $product_data
		 * @param array $category_array
		 */
		public function productCreate($product_data, $directory) {
			// log notification to start product sync
			Mage::log("Before product import - ".$product_data['sku'], null, 'gemfind_csv.log');
			
			// copy image from source to destination
			$import_product_missing_img = Mage::getStoreConfig('synchronization/product/import_product_with_images');
			$img_status = $this->copyImage($product_data["MainImage"], urldecode(basename($product_data["MainImage"])));
			$prod_no_update = Mage::getStoreConfig('synchronization/product/prod_no_update');
			$no_update_sku = explode(",",$prod_no_update);
			
			$product_import_flag = false;
			// Apply condition to import products
			if ($import_product_missing_img) {
				$product_import_flag = true;
			}
			elseif ($img_status) {
				$product_import_flag = true;
			}
						
			if ( $product_import_flag ) {				
				// While product type is configurable
				if ($product_data['type'] == 'configurable') {
					$config_prod_sku = $this -> getSkuPrefix($product_data['sku']);
					$config_product_id = $this -> checkProductExist($config_prod_sku);
										
					if (!in_array($product_data['sku'],$no_update_sku)) {
						// Remove existing configurable product
						if ($config_product_id) {
							try {
								// delete configurable product
								$_product = Mage::getModel('catalog/product');
								$_product -> load($config_product_id);
								
								// check if product belongs to featured product
								$categoryIdsExisting = $_product->getCategoryIds();
								$skip_categories = Mage::getStoreConfig("synchronization/category/cat_prod_no_del");
								$skip_categories_array = explode(',', $skip_categories);
								$skip_category_stack = "";
								if (is_array($categoryIdsExisting) and count($categoryIdsExisting) > 1) {
									foreach ($skip_categories_array as $cat_id) {
										$cat_id = trim($cat_id);
										if (in_array($cat_id, $categoryIdsExisting)) {
											$skip_category_stack[] = $cat_id;
										}
									}
								}
								
								Mage::register('isSecureArea', true);
								$del_status = $_product -> delete();
								Mage::unregister('isSecureArea');
							}
							catch (Exception $e) {
								Mage::log("Unable to delete configurable product - ".$product_data['sku'], null, 'gemfind_csv.log');
							}
						}
						// create a new configurable item
						$this -> createConfigurableProduct($config_prod_sku, $product_data, $directory, $skip_category_stack);
						
						// Add profiler log here
						$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
						if ($profiler) {
							Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Configurable Product Import");
						}
					}
					
				}
				else { // While on simple product
					// Collect all require parameters (if provided otherwise set as default)
					$attribute_set_id = Mage::getStoreConfig("synchronization/product/attribute_set_id"); // Attribute set id
					$product_sku_prefix = Mage::getStoreConfig("synchronization/product/sku_prefix"); // Sku prefix if present
					
					// Set respective quantity ***				
					if ($product_data['Quantity'] !== '') {
						$default_qty = $product_data['Quantity'];
					} else {
						$default_qty = empty($product_data['Quantity']) ? Mage::getStoreConfig("synchronization/product/default_qty") : $product_data['Quantity'];
					}
					
					// Tax class id
					$tax_class_id = Mage::getStoreConfig("synchronization/product/tax_class_id"); 
					
					// While product sku prefix is not empty
					$productSku = $this -> getSkuPrefix($product_data['sku']);
					
					// Collection of product data
					$productData = $this ->setProductArray($product_data, $directory);					
					$productId = $this ->checkProductExist($productSku);
					if ($productId) {
						try {
							if (!in_array($product_data['sku'],$no_update_sku)) {
								$updateStatus = $this -> update($productId, $productData);
							}							
							// return true or false
						} catch (Exception $e) {
							// Here exception log
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')
							->syncProductBinding($productSku, "Product update failed", $directory, "error");
							$this->_product_errors[] = "Product update failed".$productSku;
						}
					} else {
						//new product
						try {
							$productId = $this -> create($product_data['type'], $attribute_set_id, $productSku, $productData);
							/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
							->syncProductBinding($productSku, "Product information inserted", $directory, "message");*/
						} catch (Exception $e) {
							// Here exception log
							Mage::getModel('Gemfind_Synchronization_Helper_Debug')
							->syncProductBinding($productSku, "Product insert failed", $directory, "error");
							$this->_product_errors[] = "Failed to insert new product - ".$productSku;
						}
				
					}
					
					// set special price if less than actual price
					if( $product_data['special_price'] != '' && $product_data['special_price'] < $product_data['price'] ) {
					
						//$today = date("Y-m-d H:i:s");
						//$this->setSpecialPrice($productId, $product_data['special_price'], $today);
						$yesterday = date("Y-m-d",strtotime("-1 days"));					
						Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
 
						$productSp = Mage::getModel('catalog/product')->load($productId);
						if ($product_data['special_price'] > 0) {
							$productSp->setSpecialPrice( $product_data['special_price'] );
						}
						 
						$productSp->setSpecialFromDate($yesterday);
						$productSp->setSpecialFromDateIsFormated(true);
						 
						
						
						$productSp->save();
						
					}
					
					// Create object of stock item
					$stockItem = Mage::getModel('cataloginventory/stock_item');
					// Set stock details
					$stockItem -> loadByProduct($productId);
					$stockItem -> setData('use_config_manage_stock', 1);
					$stockItem -> setData('qty', $default_qty);
					$stockItem -> setData('min_qty', 0);
					$stockItem -> setData('use_config_min_qty', 1);
					$stockItem -> setData('min_sale_qty', 0);
					$stockItem -> setData('use_config_max_sale_qty', 1);
					$stockItem -> setData('max_sale_qty', 0);
					$stockItem -> setData('use_config_max_sale_qty', 1);
					$stockItem -> setData('is_qty_decimal', 0);
					$stockItem -> setData('backorders', 0);
					$stockItem -> setData('notify_stock_qty', 0);
					$stockItem -> setData('is_in_stock', 1);
					$stockItem -> setData('manage_stock', 1);
					// should be 1 to make something out of stock
					$stockItem -> setData('tax_class_id', $tax_class_id);
				
					try {
						$stockItem -> save();
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Stock details save", $directory, "message");*/
					} catch (Exception $e) {
						// Here exception log
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Stock update failed", $directory, "error");
						$this->_product_warnings[] = "Failed inventory update. Sku: ".$productSku;
					}
				
					// add custom option if any
					if (!empty($product_data['RingSizeOption'])) {
						// Process Ring Size
						$is_required = 1;
						if (!empty($product_data['parent_sku'])) {
							$is_required = 0;
						}
						$this -> insertProductCustomOptions($productId, $product_data['RingSizeOption'], 'Size', $productSku, $is_required);
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Custom option inserted", $directory, "message");*/
					} else {
						$this -> deleteProductCustomOption($productId, 'Size');
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Custom option removed", $directory, "message");*/
					}
				
					if(!empty($product_data['LengthOption'])) {
						// Process Length
						$is_required = 1;
						if (!empty($product_data['parent_sku'])) {
							$is_required = 0;
						}
						$this -> insertProductCustomOptions($productId, $product_data['LengthOption'], 'Length', $productSku, $is_required);
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Length option inserted", $directory, "message");*/
					} else {
						$this -> deleteProductCustomOption($productId, 'Length');
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Length option removed", $directory, "message");*/
					}
					
					// Add profiler log here
					$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
					if ($profiler) {
						Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Simple Product Import");
					}
				}
				
				if ($img_status) {
					// Call image function to update along with product
					$this->syncProductImage($product_data, $directory); 
				}
				else {
					$this->_product_warnings[] = "Failed to download main image. Sku: ".$product_data['sku'];
				}
				
				if (!empty($product_data['MatchingSKUs'])) {
					$this->assignRelatedProducts($product_data['sku'], $product_data['MatchingSKUs']);
				}
			}
			else {
				// TODO - Write log here to getting empty image
				// Iimage does not exist at source
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')
				->imgProduct($product_data["sku"],  "Missing product Image", $directory, "error");
				
				$mage_product = Mage::getModel("catalog/product");
				$productid = $mage_product->getIdBySku($product_data['sku']);						
				if ($productid) {
					$mage_product->load($productid);
					try {
						Mage::register('isSecureArea', true);
						$mage_product->delete();
						Mage::unregister('isSecureArea');								
					}
					catch (Exception $e) {
						Mage::log('Failed to delete product when mainimage is empty/invalid.', null, 'gemfind_csv.log');
					}
				}
				Mage::log("Invalid product Image - ".$product_data['sku'], null, 'gemfind_csv.log');
				$this->_product_errors[] = "Invalid main source image. Sku: ".$product_data['sku'];
			}
			
			// log notification to end product sync
			Mage::log("After product import - ".$product_data['sku'], null, 'gemfind_csv.log');			
		}
		
		/**
		 * Function to create/update product details
		 * 
		 * @param array $product_data
		 * @param array $category_array
		 */
		public function productPriceUpdate($product_data, $directory) {
			// log notification to start product sync
			Mage::log("Before product import - ".$product_data['sku'], null, 'gemfind_csv.log');			
			
			// Set respective quantity ***					
			if ($product_data['Quantity'] !== '') {
				$default_qty = $product_data['Quantity'];
			} else {
				$default_qty = empty($product_data['Quantity']) ? Mage::getStoreConfig("synchronization/product/default_qty") : $product_data['Quantity'];
			}
									
			// While product sku prefix is not empty
			$productSku = $this -> getSkuPrefix($product_data['sku']);
			
			// Collection of product data			
			$productId = $this ->checkProductExist($productSku);
			if ($productId) {
				try {					
					$productData['price'] = $product_data['price'] ? $product_data['price'] : '0';
					if ($product_data['gf_wholesale_price']) { $productData['gf_wholesale_price'] = $product_data['gf_wholesale_price'];}
					if ($product_data['gf_dealer_stock_number']) {$productData['gf_dealer_stock_number'] = $product_data['gf_dealer_stock_number'];}
					
					$updateStatus = $this -> update($productId, $productData);
					/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->syncProductBinding($productSku, "Product details updated", $directory, "message");*/
					// Create object of stock item
					$stockItem = Mage::getModel('cataloginventory/stock_item');
					// Set stock details
					$stockItem -> loadByProduct($productId);
					$stockItem -> setData('use_config_manage_stock', 1);
					$stockItem -> setData('qty', $default_qty);
					$stockItem -> setData('min_qty', 0);
					$stockItem -> setData('use_config_min_qty', 1);
					$stockItem -> setData('min_sale_qty', 0);
					$stockItem -> setData('use_config_max_sale_qty', 1);
					$stockItem -> setData('max_sale_qty', 0);
					$stockItem -> setData('use_config_max_sale_qty', 1);
					$stockItem -> setData('is_qty_decimal', 0);
					$stockItem -> setData('backorders', 0);
					$stockItem -> setData('notify_stock_qty', 0);
					$stockItem -> setData('is_in_stock', 1);
					$stockItem -> setData('manage_stock', 1);
					try {
						$stockItem -> save();
						/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Stock details save", $directory, "message");*/
					} catch (Exception $e) {
						// Here exception log
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($productSku, "Stock update failed", $directory, "error");
						$this->_product_warnings[] = "Failed to update inventory. Sku: ".$productSku;
					}
					
					// return true or false
				} catch (Exception $e) {
					// Here exception log
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->syncProductBinding($productSku, "Product update failed", $directory, "error");
					$this->_product_warnings[] = "Product update failed during inventory update. Sku: ".$productSku;
				}
			} else {
				// Here exception log
				Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->syncProductBinding($productSku, "Product Sku does not exist.", $directory, "error");
				$this->_product_warnings[] = "Product Sku does not exist. Sku: ".$productSku;	
			}
		
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Simple Product Import");
			}			
			// log notification to end product sync
			Mage::log("After product import - ".$product_data['sku'], null, 'gemfind_csv.log');
		}
		
		/**
		 *  Set product data in an array
		 *
		 *  @param    $product_data - array of data from sheet
		 *  @param    $category_array - category id comma seprated
		 *  @return   array
		 */
		protected function setProductArray($product_data, $directory) {
			$productData = array(); // define empty array
			// Collect and set default parameters
			$google_checkout = Mage::getStoreConfig("synchronization/product/google_checkout");
			$defaultWeight = Mage::getStoreConfig("synchronization/product/default_weight");
			$taxClassId = Mage::getStoreConfig("synchronization/product/tax_class_id");
			$url_key = '';
			$productSku = $this -> getSkuPrefix($product_data['sku']);
			if(!empty($product_data['name'])) {
				$url_key = $this -> getCleanUrl($product_data['name']) . '-' . $productSku;
			}
			$associated_prod_visibility = Mage::getStoreConfig("synchronization/product/visible_simple_config_frontend");
			$productData['status'] = 1;
			
			// Create object of category and product
			$category = Mage::getModel("synchronization/category");
			
			//Category import
			$category_array = $category->categorySync($product_data, $directory);
			// Collect all category errors
			$category_errors = $category->_category_errors;				
			if (!empty($category_errors)) {	
				foreach ($category_errors as $cat_error) {	
					$this->_product_warnings[] = $cat_error;					
				}
			}
				
			// assign category array if not empty
			if (!empty($category_array)) {
				$category_ids = $category_array[$product_data['sku']];
				$category_ids_array = explode(',', $category_ids);
				$productData['category_ids'] = $category_ids_array;
			} else {
				// assign product to root category
				// Parent id of category
				$parent_id = array(Mage::getStoreConfig("synchronization/category/default_parent"));
				$productData['category_ids'] = $parent_id;
			}
			$website_id = array(Mage::app()->getStore(true) -> getWebsite() -> getId()); // website id
			$productData['website_ids'] = (int)$website_id;
			//$productData['meta_title'] = "";
			$productData['name'] = utf8_encode($product_data['name']);
			
			if (!empty($product_data['parent_sku']) && $associated_prod_visibility == 0) {// this is simple associated product
				$productData['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
			} else {
				// set visibility
				if (isset($product_data['visible']) && $product_data['visible'] == 1) {
					$productData['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH;
				} else {
					$productData['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH;
				}
			}
			$productData['description'] = utf8_encode($product_data['description']);
			//$productData['meta_description'] = "";
			$productData['short_description'] = utf8_encode($product_data['short_description']);
			$productData['weight'] = empty($productData['weight']) ? $defaultWeight : $productData['weight'];
			$productData['tax_class_id'] = (int)$taxClassId;
			$productData['price'] = (real)$product_data['price'];
			$productData['enable_googlecheckout'] = (int)$google_checkout;
			//$productData['url_key'] = $url_key; // Disabled for existing websites.
			
			// now
			$tempAttributeConfig = array();
		
			// custom gf_** data
			foreach($product_data as $csvTitle => $rowVal) {
				if (substr($csvTitle, 0, 3) === 'gf_' && !empty($rowVal)) {
		
					//create attribute value if its dropdown
					$attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $csvTitle);
					$inputType = $attribute->getFrontendInput();
					if ($inputType == 'select') {
							
						if ($attribute->usesSource()) {
							$options = $attribute->getSource()->getAllOptions(false);
							$arg_value = utf8_encode($rowVal);
							// determine if this option exists
							$temp_value = '';
							$value_exists = false;
							foreach($options as $option) {
								if ($option['label'] == $arg_value) {
									$value_exists = true;
		
									// set id to temp value variable
									$temp_value = $option['value'];
		
									break;
								}
							}
								
							// if this option does not exist, add it.
							if (!$value_exists) {
								$attribute->setData('option', array(
										'value' => array(
												'option' => array($arg_value,$arg_value)
										)
								));
								$attribute->save();									
								$option_id = $this->getAttributeIdByOption($csvTitle, $arg_value);									
								if ($option_id) {
									$temp_value = $option_id;
								}
							}
						}
							
						// assign select option to product
						if ($temp_value != '') {
							$productData[$csvTitle] = $temp_value;
						}
						// set $attributesSkuArray array
						if (!empty($product_data['parent_sku'])) {
							$tempAttributeConfig[$csvTitle] = array("attribute_label" => $attribute -> getFrontendLabel(), "attribute_id" => $attribute -> getAttributeId(), "label" => $arg_value, "value_index" => $temp_value, "is_percent" => 0, "pricing_value" => "", );
						}
							
					}
					elseif ($inputType == 'multiselect') {
						// If attr is multiselect

						$optionsArray = explode(',',$rowVal);
						$finalData = array();
						//$finalDataTemp = array();
						if (count($optionsArray) > 0 ) {
							foreach ($optionsArray as $arg_value) {
								$options = $attribute->getSource()->getAllOptions(false);
								// determine if this option exists
								$temp_value = '';
								$value_exists = false;
								foreach($options as $option) {
									if ($option['label'] == $arg_value) {
										$value_exists = true;
										// set id to temp value variable
										$temp_value = $option['value'];

										break;
									}
								}

								// if this option does not exist, add it.
								if (!$value_exists) {

									$value['option'] = array($arg_value,$arg_value);
									$result = array('value' => $value);
									$attribute->setData('option', $result);

									$attribute->save();
									$option_id = $this->getAttributeIdByOption($csvTitle, $arg_value);
									if ($option_id) {
										$temp_value = $option_id;
									}
								}
								//$finalDataTemp[$temp_value] = array(0=>$arg_value);
								$finalData[] = $temp_value;
							}
							$productData[$csvTitle] = $finalData;
						}
					}
					else {
						// set other data
						$productData[$csvTitle] = $rowVal;
					}
				}
			}
			if (!empty($tempAttributeConfig)) {
				$this -> _attributesSkuArray[$product_data['parent_sku']][$product_data['sku']] = $tempAttributeConfig;
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Collection of product data into an array");
			}
			
			return $productData;
		}
		
		
		/**
		 *  check product sku exist or not
		 *
		 *  @param    $sku 	     *
		 *  @return   int
		 */
		protected function checkProductExist($sku) {
			$id = Mage::getModel('catalog/product') -> getIdBySku($sku);
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Check product exist or not");
			}
			
			if (!$id) {
				return false;
			} else {
				return $id;
			}
		}
		
		
		/**
		 * Function To insert product custom option
		 * 
		 * @param int $product_id
		 * @param array $option_data
		 * @param string $option_title
		 * @param string $sku
		 */
		public function insertProductCustomOptions($product_id, $option_data, $option_title, $sku, $is_require) {		
			// check if product option is already there
			$product = Mage::getModel('catalog/product') -> load($product_id);
			$options = $product -> getProductOptionsCollection();
			// Set option array
			$allOptionsArray = $options->getData();
			$flag = 0;
			
			// Collect all options values
			foreach ($allOptionsArray as $allOpKey => $allOpVal) {
				if($allOpVal['title'] == $option_title) {
					$flag = 1;
					break;
				}
			}		
			if ($product -> getHasOptions() && $flag == 1) {
				$optionValues = explode(';', $option_data);
				$options_arr = array();
				$cos = $co = array();
				foreach ($optionValues as $optionValue) {
					$optionValueData = explode(':', $optionValue);
					if (count($optionValueData) >= 3) {
						$optionValueDataLabel = $optionValueData[0];
						$optionValueDataPriceType = $optionValueData[1];
						$optionValueDataPrice = $optionValueData[2];
						$optionValueDataSortOrder = 1;
						if (count($optionValueData) >= 4) {
							$optionValueDataSortOrder = $optionValueData[3];
						}		
						$option_sku = $sku . ' ' . $optionValueDataLabel;		
						$options_arr[$option_sku] = array('title' => $optionValueDataLabel, 'price' => $optionValueDataPrice, 'price_type' => $optionValueDataPriceType, 'sku' => $sku . ' ' . $optionValueDataLabel, 'sort_order' => $optionValueDataSortOrder, 'exist_flag' => 0, );		
					}		
				}
				// Get all options
				foreach ($options as $o) {
					if ($o -> getTitle() == $option_title) {
						$values = $o -> getValuesCollection();		
						foreach ($values as $k => $v) {
							$option_sku_existing = $v -> getSku();
							if (array_key_exists($option_sku_existing, $options_arr)) {
								$v -> setTitle($options_arr[$option_sku_existing]['title']) -> setSku($option_sku_existing) -> setPriceType($options_arr[$option_sku_existing]['price_type']) -> setSortOrder($options_arr[$option_sku_existing]['sort_order']) -> setPrice(floatval($options_arr[$option_sku_existing]['price']));		
								// set exist flasg to 1
								$options_arr[$option_sku_existing]['exist_flag'] = 1;		
								$v -> setOption($o) -> save();		
								$cos[] = $v -> toArray($co);
							} else {
								// remove all other skus which are not present
								$v -> delete();		
							}		
						}
						// Option array
						foreach ($options_arr as $osku => $oval) {
							if ($oval['exist_flag'] == 0) {
								// add those values which are not there already
								$value = Mage::getModel('catalog/product_option_value');
								$value -> setOption($o) -> setTitle($oval['title']) -> setSku($oval['sku']) -> setPriceType($oval['price_type']) -> setSortOrder($oval['sort_order']) -> setPrice(floatval($oval['price'])) -> setOptionId($o -> getId());
		
								$value -> save();
								$cos[] = $value -> toArray($co);
		
							}
						}	
						// Set data	
						$o -> setData("values", $cos) -> save();						
					}		
				}
					
			} else {
				// add all new attributes		
				$options_arr[$sku] = array('title' => $option_title, 'type' => 'drop_down', 'is_require' => $is_require, 'sort_order' => 0, 'values' => array());		
				$optionValues = explode(';', $option_data);		
				foreach ($optionValues as $optionValue) {
					$optionValueData = explode(':', $optionValue);
					if (count($optionValueData) >= 3) {
						$optionValueDataLabel = $optionValueData[0];
						$optionValueDataPriceType = $optionValueData[1];
						$optionValueDataPrice = $optionValueData[2];
						$optionValueDataSortOrder = 0;
						if (count($optionValueData) >= 4) {
							$optionValueDataSortOrder = $optionValueData[3];
						}		
						$options_arr[$sku]['values'][] = array('title' => $optionValueDataLabel, 'price' => $optionValueDataPrice, 'price_type' => $optionValueDataPriceType, 'sku' => $sku . ' ' . $optionValueDataLabel, 'sort_order' => $optionValueDataSortOrder);		
					}
		
				}
		
				foreach ($options_arr as $sku => $option) {
					if (!$product -> getOptionsReadonly()) {
						$product -> setProductOptions(array($option));
						$product -> setCanSaveCustomOptions(true);
						$product -> setHasOptions(1);
						$product -> save();
					}
				}
		
				unset($options_arr);
				unset($optionValues);
				Mage::getSingleton('catalog/product_option') -> unsetOptions();
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Product custom option import");
			}
		}
		
		/**
		 * 
		 * @param int $product_id
		 * @param string $option_title
		 */
		public function deleteProductCustomOption($product_id, $option_title) {
			$product = Mage::getModel('catalog/product')->load($product_id);
			$options = $product->getProductOptionsCollection();
			if (isset($options)) {
				foreach ($options as $o) {
					if($o->getTitle() == $option_title) {
						$o->delete();
					}
				}
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Delete product custom options");
			}
		}
		
		
		/**
		 * Function set or add options attribute
		 * 
		 * @param array $product
		 * @param string $arg_attribute
		 * @param string $arg_value
		 */
		public function setOrAddOptionAttribute($product, $arg_attribute, $arg_value) {
			$attribute_model = Mage::getModel('eav/entity_attribute');
			$attribute_options_model = Mage::getModel('eav/entity_attribute_source_table');
		
			$attribute_code = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
			$attribute = $attribute_model->load($attribute_code);
		
			$attribute_options_model->setAttribute($attribute);
			$options = $attribute_options_model->getAllOptions(false);
		
			// determine if this option exists
			$value_exists = false;
			foreach($options as $option) {
				if ($option['label'] == $arg_value) {
					$value_exists = true;
					break;
				}
			}
		
			// if this option does not exist, add it.
			if (!$value_exists) {
				$attribute->setData('option', array(
						'value' => array(
								'option' => array($arg_value,$arg_value)
						)
				));
				$attribute->save();
			}		
			$product->setData($arg_attribute, $arg_value);
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Set product option attributes");
			}
		}
		
		
		/**
		 * Function to get attribute id by option
		 * 
		 * @param array $attr_code
		 * @param string $option_value
		 * @return boolean
		 */
		public function getAttributeIdByOption($attr_code, $option_value) {
			$attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attr_code);
			$options = $attribute->getSource()->getAllOptions(false);
		
			foreach($options as $option) {
				if ($option['label'] == $option_value) {
					return $option_id = $option['value'];
				}
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Get attribute id by option");
			}
			return false;
		}
		
		/**
		 * Function - To get sku prefix
		 * 
		 * @param string $sku
		 * @return string $sku
		 */
		public function getSkuPrefix($sku) {
			$product_sku_prefix = Mage::getStoreConfig("synchronization/product/sku_prefix");
			if (!empty($product_sku_prefix)) {
				$productSku = $product_sku_prefix . '-' . $sku;
			} else {
				$productSku = $sku;
			}
			
			// Add profiler log here
			$profiler = Mage::getStoreConfig('synchronization/profiler/enable');
			if ($profiler) {
				Mage::getModel("synchronization/profiler")->controllerFrontSendResponseAfter("Get product sku prefix");
			}
			return $productSku;
		}
		
		/**
		 * 
		 * @param string $config_prod_sku
		 * @param array $product_data
		 * @param array $category_array
		 */
		public function createConfigurableProduct($config_prod_sku, $product_data, $directory, $skip_category_stack) {
			$defaultWeight = Mage::getStoreConfig("synchronization/product/default_weight");
			// create configurable product
			if (!empty($product_data['SelectedAttributes'])) {
				try {
					$config_use_attribute = explode(',', $product_data['SelectedAttributes']);
			
					// For existing sku
					if (array_key_exists($product_data['sku'], $this -> _attributesSkuArray)) {
						$attribute_simple_products_copy = $attribute_simple_products = $this -> _attributesSkuArray[$product_data['sku']];
						// array of attributes in simple product
						foreach ($attribute_simple_products as $simple_sku => $simple_attr_data_array) {
							foreach ($simple_attr_data_array as $att_code => $attr_prod_data) {
								if (!in_array($att_code, $config_use_attribute)) {
									unset($attribute_simple_products_copy[$simple_sku][$att_code]);
								}
							}
						}		
					}
			
					// Configurable product data
					$configurable_products_data = array();		
					foreach ($attribute_simple_products_copy as $prod_sku => $prod_attr_arr) {
						$prod_sku = $this -> getSkuPrefix($prod_sku);
						$id = Mage::getModel('catalog/product') -> getIdBySku($prod_sku);
						foreach ($prod_attr_arr as $attr_code => $attr_code_arr) {
							unset($attr_code_arr['attribute_label']);
							$configurable_products_data[$id][] = $attr_code_arr;
						}
					}
			
					// Configurable attribute data
					$configurable_attributes_data = array();
					$i = 0;
					foreach ($config_use_attribute as $k => $attr_code) {
						$value_index_queue = array();
						$values_array = array();
						$attribute_id = $attribute_label = '';		
						foreach ($attribute_simple_products_copy as $prod_sku => $prod_attr_arr) {		
							if (array_key_exists($attr_code, $prod_attr_arr)) {		
								if ($attribute_id == '') {
									$attribute_id = $prod_attr_arr[$attr_code]['attribute_id'];
									$attribute_label = $prod_attr_arr[$attr_code]['attribute_label'];
								}		
								if (!in_array($prod_attr_arr[$attr_code]['value_index'], $value_index_queue)) {
									unset($prod_attr_arr[$attr_code]['attribute_label']);
									$values_array[] = $prod_attr_arr[$attr_code];
									$value_index_queue[] = $prod_attr_arr[$attr_code]['value_index'];
								}		
							}		
						}
						// Make configurable attributes data
						$configurable_attributes_data[] = array('id' => NULL, 'label' => $attribute_label, 'position' => NULL, 'values' => $values_array, 'attribute_id' => $attribute_id, 'attribute_code' => $attr_code, 'frontend_label' => $attribute_label, 'store_label' => $attribute_label, 'html_id' => "configurable__attribute_" . $i++, );			
					}
					
					foreach ($configurable_attributes_data as $key => $attr_array) {
						if ($attr_array['attribute_id'] == '') {
							throw new Exception('attr_error');							
						}
					}
					
					// Set url key
					$url_key = '';
					if(!empty($product_data['name'])) {
						$url_key = $this -> getCleanUrl($product_data['name']) . '-' . $config_prod_sku;
					}
			
					// Collect default data
					$attribute_set_id = Mage::getStoreConfig("synchronization/product/attribute_set_id");
					$taxClassId = Mage::getStoreConfig("synchronization/product/tax_class_id");
					$google_checkout = Mage::getStoreConfig("synchronization/product/google_checkout");
					$website_id = array(Mage::app() -> getStore(true) -> getWebsite() -> getId());
					
					// Create product object
					$product = Mage::getModel('catalog/product');
					$product -> setTypeId('configurable');
					$product -> setTaxClassId($taxClassId);
					$product -> setWebsiteIds($website_id);
					
					// store id
					$product -> setAttributeSetId($attribute_set_id);
					$product -> setSku(ereg_replace("\n", "", $config_prod_sku));
					$product -> setName(ereg_replace("\n", "", $product_data['name']));
					
					$product -> setDescription(ereg_replace("\n", "", $product_data['description']));
					$product -> setShortDescription(ereg_replace("\n", "", $product_data['short_description']));
					$product -> setStatus(1);
					$weight = empty($product_data['weight']) ? $defaultWeight : $product_data['weight'];
					$product -> setWeight($weight);
					
					//$product->setUrlKey($url_key); // Disabled for existing websites.
					//enabled
			
					// set meta tags for seo
					//$product -> setMetaDescription(ereg_replace("\n", "", $product_data['short_description']));
					//$product -> setMetaTitle(ereg_replace("\n", "", $product_data['name']));
					
					// Set product visibility
					$product -> setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH); // 4
					//catalog and search
					$product -> setPrice($product_data['price']);
					$product -> setEnableGooglecheckout($google_checkout);
					
					// Create object of category and product
					$category = Mage::getModel("synchronization/category");
					
					//Category import
					$category_array = $category->categorySync($product_data, $directory);
					
					// Collect all category errors
					$category_errors = $category->_category_errors;				
					if (!empty($category_errors)) {	
						foreach ($category_errors as $cat_error) {	
							$this->_product_warnings[] = $cat_error;					
						}
					}
					
					// assign category array if not empty
					if (!empty($category_array)) {
						$category_ids = $category_array[$product_data['sku']];
						$category_ids_array = explode(',', $category_ids);
						if (is_array($skip_category_stack) and count($skip_category_stack) > 0) { 
							$category_ids_array = array_merge($skip_category_stack, $category_ids_array);
						}
						$product -> setCategoryIds($category_ids_array);
					} else {
						// assign product to root category
						$parent_id = array(Mage::getStoreConfig("synchronization/category/default_parent"));
						$product -> setCategoryIds($parent_id);
					}
			
					// assign other gf_* attributes
					// custom gf_** data
					$productData = array();
					foreach ($product_data as $csvTitle => $rowVal) {
						if (substr($csvTitle, 0, 3) === 'gf_' && !empty($rowVal)) {
			
							//create attribute value if its dropdown
							$attribute = Mage::getSingleton('eav/config') -> getAttribute('catalog_product', $csvTitle);		
							$inputType = $attribute -> getFrontendInput();
							if ($inputType == 'select') { // while on attribute input is select
								// user source
								if ($attribute -> usesSource()) {
									$options = $attribute -> getSource() -> getAllOptions(false);
									$arg_value = utf8_encode($rowVal);
									
									// determine if this option exists		
									$temp_value = '';		
									$value_exists = false;
									foreach ($options as $option) {
										if ($option['label'] == $arg_value) {
											$value_exists = true;
			
											// set id to temp value variable
											$temp_value = $option['value'];		
											break;
										}
									}
			
									// if this option does not exist, add it.
									if (!$value_exists) {
										$attribute -> setData('option', array('value' => array('option' => array($arg_value, $arg_value))));
										$attribute -> save();		
										$option_id = $this -> getAttributeIdByOption($csvTitle, $arg_value);		
										if ($option_id) {
											$temp_value = $option_id;
										}
			
									}
								}
			
								// assign select option to product
								if ($temp_value != '') {
									$productData[$csvTitle] = $temp_value;
								}
			
							} else {
								// set other data
								$productData[$csvTitle] = $rowVal;
							}
			
						}
					}
					// When product data not empty
					if (!empty($productData)) {
						$product -> addData($productData);
					}
					// Set Product stock data
					$product->setStockData(array(
							'use_config_manage_stock' => 1,
							'is_in_stock' => 1,
							'is_salable' => 1,
					));				
			
					/* configuration attribute array settings */
					$product -> setConfigurableProductsData($configurable_products_data);
					$product -> setConfigurableAttributesData($configurable_attributes_data);
					$product -> setCanSaveConfigurableAttributes(1);		
					/* end here */
					
					// Save configurable product along with associated data					
					$product -> save();					
					/*Mage::getModel('Gemfind_Synchronization_Helper_Debug')
					->syncProductBinding($config_prod_sku, "Configurable product information inserted", $directory, "message");*/
				} catch (Exception $e) {
					// TODO Here write log for exception
					if ($e->getMessage() == 'attr_error') {
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($config_prod_sku, "Attribute does not present.", $directory, "error");	
						$this->_product_warnings[] = "Configurable product attributes not present. Sku: ".$config_prod_sku;
					} else {
						Mage::getModel('Gemfind_Synchronization_Helper_Debug')
						->syncProductBinding($config_prod_sku, "Configurable product failed to insert. Sku: ", $directory, "error");
						$this->_product_errors[] = "Configurable product failed to insert. SKU: ".$config_prod_sku;
					}					
				}
			}
			else {
				$this->_product_warnings[] = "SelectedAttributes (Configurable attributes) column is empty. Sku: ".$config_prod_sku;
			}
		}
		
		public function getCleanUrl($name) {			
			$name = trim($name);
			$string = strtolower(preg_replace(array('#[\\s-]+#', '#[^A-Za-z0-9\. -]+#'), array('-', ''), urldecode($name)));					
			return preg_replace('/-+/', '-', $string);
		}
		
		public function assignRelatedProducts($sku, $matching_sku) {
			try {
				$aRelatedProducts = explode(',', $matching_sku);
				$aParams = array();
				$nRelatedCounter = 1;
				$aProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
				$aMainProduct = Mage::getModel('catalog/product')->load($aProduct['entity_id']);
						
				foreach($aRelatedProducts as $sSku) {
					$aRelatedProductId = $this->checkProductExist($sSku);
					if ($aRelatedProductId) {
						$aParams[$aRelatedProductId] = array('position' => $nRelatedCounter);
						$nRelatedCounter++;
					}
				}
				// Collect existing assigned related product ids
				$related = $aMainProduct->getRelatedProductIds();
				foreach ($related as $increment => $r_id) {
					$nRelatedCounter += $increment;
					$aParams[$r_id] = array('position' => $nRelatedCounter);
				}

				$aMainProduct->setRelatedLinkData($aParams);
				$aMainProduct->save();
			}
			catch (Exception $e) {
				$this->_product_warnings[] = "Failed to assign related products. Sku: ".$sku;
				// log notification to end product sync
				Mage::log("Failed to assign related products. Sku:".$sku, null, 'gemfind_csv.log');
			}			
		}
		
	}
	