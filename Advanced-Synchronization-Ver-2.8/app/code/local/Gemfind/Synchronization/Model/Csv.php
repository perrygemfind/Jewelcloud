<?php 
	Class Gemfind_Synchronization_Model_Csv extends Mage_Core_Model_Abstract {	
    /**
	* To collect current directory name
	*/
	public $cur_directory;
	
	/**
	* To collect current node - like product sync or delete sync
	*/
	public $xml_node;
	
	/**
     * data load initialize
     *
     * @param mixed $filename please look at the load() method
     *
     * @access public
     * @see load()
     * @return void
    */
    public function __construct($filename = null, $x_node =  null, $dir = null) {
		// Initilize limit of csv data
		ini_set("memory_limit","-1");
		// Todo
		$this->cur_directory = $dir;
		$this->xml_node = $x_node;
    }
	
    /**
     * csv file loader
     *
     * indicates the object which file is to be loaded
     *
     * @param string $filename the csv filename to load
     *
     * @access public
     * @return boolean true if file was loaded successfully
     * @see isSymmetric(), getAsymmetricRows(), symmetrize()
    */
    public function csvLoad($filename) {				
		try {
			$csv = Mage::getModel("synchronization/CsvRead", $filename);
			//$Csv = new Csv($filename, $separator = ";");
			$csv_data = $csv->readAll();
			/*********** Sample code to test data **************/
			/*echo "<table border='1'>";
			foreach($csv_data as $row) {
				echo "<tr>";
				foreach($row as $col) {
					echo "<td>$col</td>";					
				}
				echo "</tr>";
			}
			echo "</table>";*/
			/*********** Sample code to test data **************/
		}
		catch (Exception $e) {
			// Print log
			Mage::log("Exception code: ". $e->getCode(), null, 'gemfind_csv.log');
			Mage::log("Exception message: ". nl2br($e->getMessage()), null, 'gemfind_csv.log');
			Mage::log("Thrown by: ".$e->getFile(), null, 'gemfind_csv.log');
			Mage::log("on line: ". $e->getLine(), null, 'gemfind_csv.log');
			Mage::log("Stack trace: ". nl2br($e->getTraceAsString()), null, 'gemfind_csv.log');	
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Exception message: ". nl2br($e->getMessage()), $this->cur_directory);
		}
		return $csv_data;		
    }	
}