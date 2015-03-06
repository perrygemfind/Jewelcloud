<?php
	/**
	 * Gemfind Synchronization Profiler
	 *
	 * @category   Gemfind
	 * @package    Gemfind_Synchronization
	 * @author     Gemfind Team
	 */
	class Gemfind_Synchronization_Model_Profiler {		
		/**
		* Log the profiler statistics plus some information that helps identify the request
		*
		* @param Varien_Event_Observer $observer
		* @return void
		*/
		public function controllerFrontSendResponseAfter($entity, Varien_Event_Observer $observer) {
			// Only write to log if the profiler is enabled
			$timers = $this->_getSortedTimers();
			if ($timers) {
				$data = $this->_getTableHeadData($entity);
				$data = array_merge($data, $timers);
				 
				$out = $this->_getDataAsTextTable($data);
				$f = fopen('var/log/profiler.log', 'a');
				fwrite($f, $out);
				fclose($f);
			}
		}
	 
		/**
		* Return the table head data in an array
		*
		* @return array
		*/
		protected function _getTableHeadData($entity) {
			$data = array();
			$data[] = array();
			$data[] = array('Request URI: ' . Mage::app()->getRequest()->getServer('REQUEST_URI'));
			$data[] = array('Request Date: ' . date('Y-m-d H:i:s'));
			$data[] = array('Memory usage: real: ' . memory_get_usage(true) . ', emalloc: ' . memory_get_usage());
			$data[] = array('Entity: '. $entity);
			$data[] = array('Code Profiler', 'Time', 'Count', 'Emalloc', 'RealMem');
			 
			return $data;
		}
		
		/**
		* Sort by time and return the Varien_Profiler timers array
		*
		* @return array
		*/
		protected function _getSortedTimers() {
			$timers = array();		 
			foreach (Varien_Profiler::getTimers() as $name => $timer) {
				$sum = number_format(Varien_Profiler::fetch($name, 'sum'), 4);
				$count = Varien_Profiler::fetch($name, 'count');
				$emalloc = Varien_Profiler::fetch($name, 'emalloc');
				$realmem = Varien_Profiler::fetch($name, 'realmem');
				 
				// Filter out entries of little relevance
				if ($sum < .0010 && $count < 10 && $emalloc < 10000)
				{
				continue;
				}
				 
				$row = array($name, number_format($sum, 4), $count, number_format($emalloc), number_format($realmem));
				$timers[] = $row;
			}
			usort($timers, array($this, '_sortTimers'));
			return $timers;
		}
	 
		/**
		* Method to compare timer aray entries by timer sum to sort descending
		*
		* @param array $a
		* @param array $b
		* @return int
		*/
		protected function _sortTimers(array $a, array $b) {
			if ($a[1] === $b[1])
			{
			return 0;
			}
			return $a[1] < $b[1] ? 1 : -1;
		}
	 
		/**
		* Return the passed in timers data array as a text table
		*
		* @param array $data
		* @return string
		*/
		protected function _getDataAsTextTable(array $data) {
			// Dummy widths
			$table = new Zend_Text_Table(array('columnWidths' => array(1)));
			$widths = array();
			foreach ($data as $rowData) {
				$row = new Zend_Text_Table_Row();
				foreach ($rowData as $idx => $cell) {
					$width = mb_strlen($cell);
					if (!isset($widths[$idx]) || $widths[$idx] < $width) {
						$widths[$idx] = $width;
					}
					$row->appendColumn(new Zend_Text_Table_Column(strval($cell)));
				}
				$table->appendRow($row);
			}
			$table->setColumnWidths($widths);		 
			return $table->render();
		}
	}
?>