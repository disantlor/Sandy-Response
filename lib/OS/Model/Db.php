<?php
class OS_Model_Db extends OS_Model
{
	private $_db;
	protected $_table;
	
	/**
	 * If passed param is an int, assume it's an ID, and fetch data from db
	 * Otherwise, pass param on to parent constructor
	 * @param int|array $idOrData
	 */
	public function __construct($idOrData = false)
	{
		if (is_int($idOrData)) {
			$this->fetchById($idOrData);
		} else {
			parent::__construct($idOrData);
		}
	}
	
	public function getId()
	{
		return $this->get('id');
	}
	
	public function getTableName()
	{
		return $this->_table;
	}
	
	public function getDbConnection()
	{
		if (! is_null($this->_db)) {
			return $this->_db;
		}
	
		$this->_db = new Zend_Db_Adapter_Pdo_Mysql(array(
			'host'     	=> DB_HOST,
			'port'		=> DB_PORT,
			'username' 	=> DB_USER,
			'password' 	=> DB_PASS,
			'dbname'   	=> DB_NAME
		));
		
		return $this->_db;
	}
	
	/**
	 * Populate class data from id
	 * @param int $id
	 * @return boolean
	 */
	public function fetchById($id)
	{
		$result = $this->query(array('id' => $id), 1);
		
		if (! $result) {
			return false;
		}
		
		$this->setDataArray($result->fetch());
		return true;
	}
	
	/**
	 * Helpert/shortcut function to fetch
	 * @param array of $where params 
	 * @param int $offset
	 * @param string $orderBy
	 * @return class object or false 
	 */
	public function findOne($where = array(), $offset = 0, $orderBy = '')
	{
		$result = $this->query($where, 1, $offset, $orderBy);
		$data = $result->fetch();
		return ($data) ? $this->factory($data) : false;
	}
	
	/**
	 * Fetch $limit objects from db
	 * @param array of $where params 
	 * @param int $limit
	 * @param int $offset
	 * @param string $orderBy
	 * @return array of class objects 
	 */
	public function find($where = array(), $limit = 50, $offset = 0, $orderBy = '')
	{
		$results = $this->query($where, $limit, $offset, $orderBy)->fetchAll();

		if (! $results) {
			return false;
		}
		
		$output = array();
		foreach ($results as $result) {
			$output[] = $this->factory($result);			
		}
		
		return $output;
	}
	
	/**
	 * 
	 * Accepts $where in two formats, as a field/value pair or a full where clause (e.g. "field IS NULL")
	 * @param array $where
	 * @param int $limit
	 * @param int $offset
	 * @param string $orderBy
	 * @return Zend_Db_Statement
	 */
	protected function query($where, $limit, $offset = 0, $orderBy = '')
	{
		$db = $this->getDbConnection();
		$select = $db->select()->from($this->getTableName());

		foreach ($where as $field => $value) {
			
			// if $field is a string then we have a field/value pair
			if (! is_int($field)) {
				$select->where("$field = ?", $value);
			
			// if it's an int, then $value is intended to be a full where clase
			} else {
				$select->where($value);	
			}
						
		}
		
		$select->order($orderBy);
		$select->limit($limit, $offset);		

		return $select->query();
	}
	
	/**
	 * Return new object of same type as current context ($this)
	 * Allows for inherited classes to make use of db function in generalized way
	 * @param array $data
	 * @return class of same type as current context
	 */
	protected function factory($data = array())
	{
		$thisClass = get_class($this);
		return new $thisClass($data);
	}
	
	public function delete()
	{
		return $this->getDbConnection()->delete(
			$this->getTableName(),
			array('id = ?' => $this->getId())
		);
	}
	

	/**
	 * Save current object
	 * Perform update if already exists in db (has id)
	 * Otherwise, insert new row
	 * @return boolean
	 */
	public function save()
	{
		// if there's a id, attempt an atomic update
		if ($this->getId()) {
	
			$setArray = $this->toArray();
			unset($setArray['id']); // dont bother updating id field
			
			return (bool)$this->getDbConnection()->update(
				$this->getTableName(),
				$setArray,
				array('id = ?' => $this->getId())
			);
			
		// otherwise create a new document
		} else {
		
			// insert a new document
			$result = (bool)$this->getDbConnection()->insert(
				$this->getTableName(),	
				$this->toArray()
			);
			
			if (! $result) {
				return false;
			}
			
			// update instance with new ID
			$this->set('id', $this->getDbConnection()->lastInsertId());
			return true;
		}
	}
}