<?php
class OS_Model
{
	protected $_data = array();
	
	public function __construct($dataArray = array())
	{
		if (! is_array($dataArray)) {
			return false;
		}
		
		$this->setDataArray($dataArray);
	}
	
	/**
	 * Raw data accessors
	 */
	public function toArray()
	{
		return $this->_data;
	}
	
	public function setDataArray($data = array())
	{
		$this->_data = $data;
		return $this;
	}

	/**
	 * Get accessor
	 * @param mixed $key an index in the data array
	 */
	public function get($key)
	{
		if (strpos($key, '.')) {
			$eval = '$result = isset($this->_data[\'' . join('\'][\'', preg_split('/\./', $key)) . '\']) ? ' . 
				'$this->_data[\'' . join('\'][\'', preg_split('/\./', $key)) . '\'] : false;';
			eval($eval);
			return $result;
		}
		
		return isset($this->_data[$key]) ? $this->_data[$key] : false;
	}
	
	/**
	 * Set accessor
	 * @param string $key an index in the data array
	 * @param mixed $val a new value or array of values
	 */
	public function set($key, $val = '')
	{
		if (strpos($key, '.')) {
			$eval = '$this->_data[\'' . join('\'][\'', preg_split('/\./', $key)) . '\'] = $val;'; 
			eval($eval);
		} else {
			$this->_data[$key] = $val;
		}
		return $this;
	}

	/**
	 * JSON-encodes the internal data array
	 */
	public function toJson()
	{
		return json_encode($this->toArray());	
	}
}