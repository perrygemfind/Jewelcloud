<?php 
	Class Gemfind_Synchronization_Helper_Customization extends Mage_Core_Helper_Abstract {
		/**
		 * 
		 * @param array $Product
		 * @return array modified $Product
		 */
		public function customizeSpecialCharData($Product=null, $product_object=null, $directory=null) {
			$enable = Mage::getStoreConfig("synchronization/customization/enable");
			if ($enable) {
				try {
					// Apply special char filter to Name
					if (isset($Product["name"])) {
						$Product["name"] = $this->filterText(utf8_encode($Product["name"]));
					}
					// Apply special char filter to Description
					if (isset($Product["description"])) {
						$Product["description"] = $this->filterText(utf8_encode($Product["description"]));
					}
					// Apply special char filter to Short Description
					if (isset($Product["short_description"])) {
						$Product["short_description"] = $this->filterText(utf8_encode($Product["short_description"]));
					}
					// Apply special char filter to Designer Name
					if (isset($Product["gf_designer_name"])) {
						$Product["gf_designer_name"] = $this->filterText(utf8_encode($Product["gf_designer_name"]));
					}
					// Apply special char filter to Designer Collection Name
					if (isset($Product["gf_collection_name"])) {
						$Product["gf_collection_name"] = $this->filterText(utf8_encode($Product["gf_collection_name"]));
					}
				}
				catch (Exception $e) {
					$product_object->_product_warnings[] = "Failed to apply customization changes. ".$e->getMessage();
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML("productsync","Failed to apply customization changes. ".$e->getMessage(), $directory);
					Mage::log("Failed to apply customization changes. ".$e->getMessage(), null, 'gemfind_csv.log');
				}
			}
			return $Product;
		}
		
		public function filterText($text) {
			// 1) convert á ô => a o
			$text = preg_replace("/[Â]/u","REGISTERED",$text);
			$text = preg_replace("/[Ã¢]/u","TRADEMARK",$text);
		
			//2) Translation CP1252. &ndash; => -
			$trans = get_html_translation_table(HTML_ENTITIES); 
			$trans[chr(130)] = '&sbquo;';    // Single Low-9 Quotation Mark 
			$trans[chr(131)] = '&fnof;';    // Latin Small Letter F With Hook 
			$trans[chr(132)] = '&bdquo;';    // Double Low-9 Quotation Mark 
			$trans[chr(133)] = '&hellip;';    // Horizontal Ellipsis 
			$trans[chr(134)] = '&dagger;';    // Dagger 
			$trans[chr(135)] = '&Dagger;';    // Double Dagger 
			$trans[chr(136)] = '&circ;';    // Modifier Letter Circumflex Accent 
			$trans[chr(137)] = '&permil;';    // Per Mille Sign 
			$trans[chr(138)] = '&Scaron;';    // Latin Capital Letter S With Caron 
			$trans[chr(139)] = '&lsaquo;';    // Single Left-Pointing Angle Quotation Mark 
			$trans[chr(140)] = '&OElig;';    // Latin Capital Ligature OE 
			$trans[chr(145)] = '&lsquo;';    // Left Single Quotation Mark 
			$trans[chr(146)] = '&rsquo;';    // Right Single Quotation Mark 
			$trans[chr(147)] = '&ldquo;';    // Left Double Quotation Mark 
			$trans[chr(148)] = '&rdquo;';    // Right Double Quotation Mark 
			$trans[chr(149)] = '&bull;';    // Bullet 
			$trans[chr(150)] = '&ndash;';    // En Dash 
			$trans[chr(151)] = '&mdash;';    // Em Dash 
			$trans[chr(152)] = '&tilde;';    // Small Tilde 
			$trans[chr(153)] = '&trade;';    // Trade Mark Sign 
			$trans[chr(154)] = '&scaron;';    // Latin Small Letter S With Caron 
			$trans[chr(155)] = '&rsaquo;';    // Single Right-Pointing Angle Quotation Mark 
			$trans[chr(156)] = '&oelig;';    // Latin Small Ligature OE 
			$trans[chr(159)] = '&Yuml;';    // Latin Capital Letter Y With Diaeresis 
			$trans['euro'] = '&euro;';    // euro currency symbol 
			ksort($trans); 
			 
			foreach ($trans as $k => $v) {
				$text = str_replace($v, $k, $text);
			}
		 
			// 3) remove <p>, <br/> ...
			$text = strip_tags($text); 
			 
			// 4) &amp; => & &quot; => '
			$text = html_entity_decode($text);
			 
			// 5) remove Windows-1252 symbols like "TradeMark", "Euro"...
			$text = preg_replace('/[^(\x20-\x7F)]*/','', $text); 
			 
			$targets=array('\r\n','\n','\r','\t',"REGISTERED","TRADEMARK");
			$results=array(" "," "," ","","&reg;","&#8482;");
			$text = str_replace($targets,$results,$text);
		 
			return ($text);
		}
	}
