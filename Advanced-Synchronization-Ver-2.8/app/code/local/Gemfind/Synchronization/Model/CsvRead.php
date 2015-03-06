<?php

/**
* Implementation of CsvInterface
*/
class Gemfind_Synchronization_Model_CsvRead implements Gemfind_Synchronization_Model_CsvInterface
{
    /**
    * CSV filename with path
    * @type string
    */
    protected $filename;

    /**
    * CSV separator
    * @type string
    */
    protected $separator;

    /**
    * CSV file resource link
    * @type resource
    */
    protected $csvH;
	
	/**
	* To collect current directory name
	*/
	private $cur_directory;
	
	/**
	* To collect current node - like product sync or delete sync
	*/
	private $xml_node;


    public function __construct(/*string*/ $filename, /*string*/ $separator = ",")
    {
		$csv = Mage::getModel("synchronization/csv");
		$this->cur_directory = $csv->cur_directory; 
		$this->xml_node = $csv->xml_node;
		
        if (!is_string($filename)) {
            //throw new Exception("Illegal parameter filename. Must be string.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter filename. Must be string.", $this->cur_directory);
			Mage::log("Illegal parameter filename. Must be string.", null, 'gemfind_csv.log');
        }
        if (!is_string($separator)) {
            //throw new Exception("Illegal parameter separator. Must be string.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter separator. Must be string.", $this->cur_directory);
			Mage::log("Illegal parameter separator. Must be string.", null,  'gemfind_csv.log');
        }		
        $this->filename = $filename;
        $this->separator = $separator;
    }

    public function __destruct()
    {
        if (is_resource($this->csvH)) {
            fclose($this->csvH);
        }
    }

    public function read(/*integer*/ $limit = 9999)
    {
        if (!is_integer($limit)) {
            //throw new Exception("Illegal parameter limit. Must be integer.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter limit. Must be integer.", $this->cur_directory);
			Mage::log("Illegal parameter limit. Must be integer.", null,  'gemfind_csv.log');
        }
        try {
			//$rows = array_map('str_getcsv', file($this->filename));
			//$header = array_shift($rows);
            while(empty($row)) {
                $row = fgetcsv($this->getCsvH(), $limit, $this->separator);
				//$row = array_combine($header, $row);
                if (!$row) {
                    return false;
                }
            }
        }
        catch (Exception $e) {
            //throw $e;
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Empty Read - ".$e->getMessage(), $this->cur_directory);
			Mage::log("Empty Read - ".$e->getMessage(), null, 'gemfind_csv.log');
        }
        return $row;
    }

	public function readAll()
    {
        try {
            $this->rewind();
			$y = 0;
			$fields = $this->read();	
			
            while($row = $this->read()) {				
				$x = 0;
				foreach($row as $value) {
				  $csv[$y][$fields[$x]] = $value;				  
					$x++;
				}
				$y++;                
            }
        }
        catch (Exception $e) {
            // Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Empty ReadAll - ".$e->getMessage(), $this->cur_directory);
			Mage::log("Empty ReadAll - ".$e->getMessage(), null, 'gemfind_csv.log');
        }
        return $csv;
    }

    public function write(/*array*/ $add, /*boolean*/ $toEnd = true)
    {
        if (!is_bool($toEnd)) {
            //throw new Exception("Illegal parameter toEnd. Must be boolean.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter toEnd. Must be boolean.", $this->cur_directory);
			Mage::log("Illegal parameter toEnd. Must be boolean.", null, 'gemfind_csv.log');
        }
        if (!is_array($add)) {
            //throw new Exception("Illegal parameter add. Must be array.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter add. Must be array.", $this->cur_directory);
			Mage::log("Illegal parameter add. Must be array.", null, 'gemfind_csv.log');
        }
        try {
            if ($toEnd) {
                $this->toEnd();
            }
            fwrite($this->getCsvH(), implode($this->separator, $add));
        }
        catch (Exception $e) {
            //throw $e;
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Empty CSV write - ".$e->getMessage(), $this->cur_directory);
			Mage::log("Empty CSV write - ".$e->getMessage(), null,  'gemfind_csv.log');
        }
    }

    public function seek(/*integer*/ $position = 0, /*integer*/ $offset = 0)
    {
        if (!is_integer($position)) {
            //throw new Exception("Illegal parameter position. Must be integer.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter position. Must be integer.", $this->cur_directory);
			Mage::log("Illegal parameter position. Must be integer.", null, 'gemfind_csv.log');
        }
        if (!is_integer($offset)) {
            //throw new Exception("Illegal parameter offset. Must be integer.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Illegal parameter offset. Must be integer.", $this->cur_directory);
			Mage::log("Illegal parameter offset. Must be integer.", null, 'gemfind_csv.log');
        }
        try {
            if ($position < 0) {
                if (fseek($this->getCsvH(), $offset, SEEK_SET) < 0) {
                    //throw new Exception("Cannot seek cursor in CSV file on '". $offset ."'.");
					// Print log
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot seek cursor in CSV file on '". $offset ."'.", $this->cur_directory);
					Mage::log("Cannot seek cursor in CSV file on '". $offset ."'.", null,  'gemfind_csv.log');
                }
            }
            elseif ($position > 0) {
                if (fseek($this->getCsvH(), $offset, SEEK_END) < 0) {
                    //throw new Exception("Cannot seek cursor in CSV file on END + '". $offset ."'.");
					// Print log
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot seek cursor in CSV file on END + '". $offset ."'.", $this->cur_directory);
					Mage::log("Cannot seek cursor in CSV file on END + '". $offset ."'.", null, 'gemfind_csv.log');
                }
            }
            else {
                if (fseek($this->getCsvH(), $offset, SEEK_CUR) < 0) {
                    //throw new Exception("Cannot seek cursor in CSV file on CURRENT + '". $offset ."'.");
					// Print log
					Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot seek cursor in CSV file on CURRENT + '". $offset ."'.", $this->cur_directory);
					Mage::log("Cannot seek cursor in CSV file on CURRENT + '". $offset ."'.", null, 'gemfind_csv.log');
                }
            }
        }
        catch (Exception $e) {
            //throw $e;
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Empty CSV seek - ".$e->getMessage(), $this->cur_directory);
			Mage::log("Empty CSV seek - ".$e->getMessage(), null, 'gemfind_csv.log');
        }
    }

    public function rewind()
    {
        if (!rewind($this->getCsvH()) === 0) {
            //throw new Exception("Cannot rewind cursor in CSV file.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot rewind cursor in CSV file.", $this->cur_directory);
			Mage::log("Cannot rewind cursor in CSV file.", null, 'gemfind_csv.log');
        }
    }

    /**
    * seek CSV file to end
    * @return void
    */
    protected function toEnd()
    {
        if (!fseek($this->getCsvH(), 0, SEEK_END)){
            //throw new Exception("Cannot seek cursor in CSV file to end.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot seek cursor in CSV file to end.", $this->cur_directory);
			Mage::log("Cannot seek cursor in CSV file to end.", null, 'gemfind_csv.log');
        }
    }

    /**
    * open file defined with filename
    * @return void
    */
    protected function open()
    {
        if (is_resource($this->csvH)) {
            return true;
        }
        if (!strlen($this->filename)) {
            //throw new Exception("There is no filename parameter.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"There is no filename parameter.", $this->cur_directory);
			Mage::log("There is no filename parameter.", null, 'gemfind_csv.log');
        }
        if (!$this->csvH = @fopen($this->filename, "r")) {
            //throw new Exception("Cannot find/open '". $this->filename ."'.");
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Cannot find/open '". $this->filename ."'.", $this->cur_directory);
			Mage::log("Cannot find/open '". $this->filename ."'.", null, 'gemfind_csv.log');
        }
        return true;
    }

    /**
    * Getter of csvH
    * @return resource
    */
    protected function getCsvh()
    {
        try {
            $this->open();
        }
        catch (Exception $e) {
            //throw $e;
			// Print log
			Mage::getModel('Gemfind_Synchronization_Helper_Debug')->syncErrorCountLogXML($this->xml_node,"Empty CSV get header - ".$e->getMessage(), $this->cur_directory);
			Mage::log("Empty CSV get header - ".$e->getMessage(), null, 'gemfind_csv.log');
        }
        return $this->csvH;
    }
}

?>