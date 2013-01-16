<?php
namespace Change\Db;

/**
 * @name \Change\Db\DbProvider
 * @method \Change\Db\DbProvider getInstance()
 */
abstract class DbProvider
{	
	/**
	 * @var integer
	 */
	protected $id;	
	
	/**
	 * @var array
	 */
	protected $connectionInfos;
	
	/**
	 * @var array
	 */
	protected $timers;
	
	/**
	 * @var \Change\Logging\Logging
	 */
	protected $logging;
	
	/**
	 * @var \Change\Db\SqlMapping
	 */
	protected $sqlMapping;
		
	/**
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}	
	
	/**
	 * @return string
	 */
	public abstract function getType();
	
	/**
	 * @param \Change\Configuration\Configuration $config
	 * @param \Change\Logging\Logging $logging
	 * @throws \RuntimeException
	 * @return \Change\Db\DbProvider
	 */
	public static function newInstance(\Change\Configuration\Configuration $config, \Change\Logging\Logging $logging)
	{
		$connectionInfos = $config->getEntry('databases/default', array());
		if (!isset($connectionInfos['dbprovider']))
		{
			throw new \RuntimeException('Missing or incomplete database configuration');
		}
		$className = $connectionInfos['dbprovider'];
		return new $className($connectionInfos, $logging);
	}
	
	/**
	 * @param array $connectionInfos
	 * @param \Change\Logging\Logging $logging
	 */
	public function __construct(array $connectionInfos, \Change\Logging\Logging $logging)
	{
		$this->connectionInfos = $connectionInfos;
		$this->logging = $logging;
		$this->timers = array('init' => microtime(true), 'longTransaction' => isset($connectionInfos['longTransaction']) ? floatval($connectionInfos['longTransaction']) : 0.2);
	}	
	
	public function __destruct()
	{
		if ($this->inTransaction())
		{
			$this->logging->warn(__METHOD__ . ' called while active transaction (' . $this->transactionCount . ')');
		}
	}
	
	/**
	 * @return array
	 */
	public function getConnectionInfos()
	{
		return $this->connectionInfos;
	}
	
	/**
	 * @return void
	 */
	public abstract function closeConnection();
	
	/**
	 * @return \Change\Db\InterfaceSchemaManager
	 */
	public abstract function getSchemaManager();
		
	/**
	 * @return \Change\Db\SqlMapping
	 */
	public function getSqlMapping()
	{
		if ($this->sqlMapping === null)
		{
			$this->sqlMapping = new SqlMapping();
		}
		return $this->sqlMapping;
	}
	
	/**
	 * @return void
	 */
	public abstract function beginTransaction();
	
	/**
	 * @return void
	 */
	public abstract function commit();
	
	/**
	 * @return void
	 */
	public abstract function rollBack();
	
	/**
	 * @return boolean
	 */
	public abstract function inTransaction();
	
	/**
	 * @param string $tableName
	 * @return integer
	 */
	public abstract function getLastInsertId($tableName);
	
	/**
	 * @api
	 * @param \Change\Db\Query\InterfaceSQLFragment $fragment
	 * @return string
	 */
	public function buildSQLFragment(\Change\Db\Query\InterfaceSQLFragment $fragment)
	{
		return $fragment->toSQL92String();
	}
	
	/**
	 * @param \Change\Db\Query\SelectQuery $selectQuery
	 * @return array
	 */
	public abstract function getQueryResultsArray(\Change\Db\Query\SelectQuery $selectQuery);
		
	/**
	 * @param \Change\Db\Query\AbstractQuery $query
	 * @return integer
	 */
	public abstract function executeQuery(\Change\Db\Query\AbstractQuery $query);
	
	/**
	 * @param mixed $value
	 * @param integer $scalarType \Change\Db\ScalarType::*
	 * @return mixed
	 */
	public abstract function phpToDB($value, $scalarType);
	
	/**
	 * @param mixed $value
	 * @param integer $scalarType \Change\Db\ScalarType::*
	 * @return mixed
	 */
	public abstract function dbToPhp($value, $scalarType);
		
}