<?php

namespace App\baseClasses;
use Exception;
use mysql_xdevapi\Result;

/**
 * Abstract class which has helper functions to get data from the database
 */
abstract class KCModel
{
	/**
	 * The current table name
	 *
	 * @var boolean
	 */
	private $tableName = false;

	/**
	 * Constructor for the database class to inject the table name
	 *
	 * @param String $tableName - The current table name
	 */
	public function __construct($tableName)
	{
		global $wpdb;
		$this->tableName = $wpdb->prefix. 'kc_' . $tableName;
	}

	public function get_table_name(){
		return $this->tableName;
	}

	/**
	 * Insert data into the current data
	 *
	 * @param  array  $data - Data to enter into the database table
	 *
	 * @return Object|int
	 */
	public function insert(array $data)
	{
		global $wpdb;

		if(empty($data))
		{
			return false;
		}

		$wpdb->insert($this->tableName, $data);

		return $wpdb->insert_id;
	}

	/**
	 * Update a table record in the database
	 *
	 * @param array $data - Array of data to be updated
	 * @param array $conditionValue - Key value pair for the where clause of the query
	 *
	 * @return bool|false|int object
	 */
	public function update(array $data, array $conditionValue)
	{
		global $wpdb;

		if(empty($data))
		{
			return false;
		}

		return $wpdb->update( $this->tableName, $data, $conditionValue);
	}


	/**
	 * Delete row on the database table
	 *
	 * @param  array  $conditionValue - Key value pair for the where clause of the query
	 *
	 * @return Int - Num rows deleted
	 */
	public function delete(array $conditionValue)
	{
		global $wpdb;

		return $wpdb->delete( $this->tableName, $conditionValue );
	}


	/**
	 * Get all from the selected table
	 *
	 * @param  String $orderBy - Order by column name
	 *
	 * @return array|object
	 */

	public function get_all( $orderBy = NULL,$column='*' )
	{
		global $wpdb;

		$sql = "SELECT  {$column}  FROM {$this->tableName} ";

		if(!empty($orderBy))
		{
			$sql .= ' ORDER BY ' . esc_sql($orderBy);
		}

		$all = $wpdb->get_results($sql);

		return $all;
	}

	/**
	 * Get a value by a condition
	 *
	 * @param array $conditionValue - A key value pair of the conditions you want to search on
	 * @param String $condition - A string value for the condition of the query default to equals
	 *
	 * @param bool $returnSingleRow
	 *
	 * @return bool|result
	 */
	public function get_by(array $conditionValue, $condition = '=', $returnSingleRow = FALSE)
	{
		global $wpdb;

		try
		{
			$sql = 'SELECT * FROM `'.$this->tableName.'` WHERE ';

			$conditionCounter = 1;
			foreach ($conditionValue as $field => $value)
			{
				if($conditionCounter > 1)
				{
					$sql .= ' AND ';
				}

				switch(strtolower($condition))
				{
					case 'in':
						if(!is_array($value) || empty($value))
						{
							wp_send_json(kcThrowExceptionResponse(__("Values for IN query must be an array.",'kc-lang'), 1));
						}

						$sql .= $wpdb->prepare('`%s` IN (%s)', $field, implode(',', $value));
						break;

					default:
						$sql .= $wpdb->prepare('`'.$field.'` '.$condition.' %s', $value);
						break;
				}

				$conditionCounter++;
			}

			// As this will always return an array of results if you only want to return one record make $returnSingleRow TRUE
			if( $returnSingleRow)
			{
				$result = $wpdb->get_row($sql);

			}else{

                $result = $wpdb->get_results($sql);
            }

			return $result;
		}
		catch(Exception $ex)
		{
			return false;
		}
	}

    public function get_var(array $conditionValue,$column , $is_single=true ){
        global $wpdb;
        $condition = '=';
        try
        {
            $sql = "SELECT {$column} FROM {$this->tableName} WHERE ";

            $conditionCounter = 1;

            foreach ($conditionValue as $field => $value)
            {
                if($conditionCounter > 1)
                {
                    $sql .= ' AND ';
                }

                $sql .= $wpdb->prepare('`'.$field.'` '.$condition.' %s', $value);

                $conditionCounter++;
            }
		
			if($is_single)
            	return $wpdb->get_var($sql);
			else
				return $wpdb->get_col($sql);
			

        }
        catch(Exception $ex)
        {
            return false;
        }
    }

    public function get_db_instance(){
        global $wpdb;
        return $wpdb;
    }
}