<?php

/**
* deals with CSV files
* @author Michal Palma <palmic at centrum dot cz>
* @copyleft (l) 2005  Michal Palma
* @package csv
* @version 1.0
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @date 2005-08-01
*/
interface Gemfind_Synchronization_Model_CsvInterface
{

    public function __construct(/*string*/ $filename, /*string*/ $separator = ";");

    /**
    * read 1row from opened CSV file
    * @return array
    */
    public function read($limit = 1000);

    /**
    * read all from opened CSV file
    * @return array
    */
    public function readAll();

    /**
    * write 1row to opened CSV file
    * @parameter array add - content of row (all_row_as_cols_in_array) - prior ordered, its no mather of indexnames
    * @return void
    */
    public function write($add, $atend = true);

    /**
    * to seeking in file
    * @parameter -1, 0, 1 position - seeking position (start + offset, current + offset, end + offset)
    * @parameter integer offset - fine seeking position to add to $position value
    * @return void
    */
    public function seek($position = 0, $offset = 0);

    /**
    * rewind CSV file to start
    * @return void
    */
    public function rewind();
}

?>