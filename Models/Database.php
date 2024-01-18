<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class database
{

	protected $connection;
	protected $query;
	protected $show_errors = TRUE;
	protected $query_closed = TRUE;
	public $query_count = 0;






	public function checkLogindata($username, $password)
	{
		return ($this->query("select * from Employee where Username=? and Password=?", $username, $password));
	}
	public function getID($username)
	{
		return ($this->query("select id from Employee where Username=? ", $username))->fetchAll();
	}

	public function logTime(
		$id,
		$time,
		$pause

	) {
		$date = date("Y-m-d");
		try {
			return ($this->query(
				"insert into Hours (startTime , employee_id) VALUES(?,?)",
				$time,
				$id
			)
			);
		} catch (Exception $e) {
			return ($this->query(
				"update Hours set endTime=? , pause=?  where employee_id=? and Date_=?",
				//update Hours set endTime="12:00" , pause=0  where employee_id=1 and Date_="2023-12-19"
				$time,
				$pause,
				$id,
				$date
			)
			);
		}
		;
	}
	public function editLog(
		$id,
		$actual_hours,
		$start,
		$end,
		$overtime,
		$type,
		$date,
		$comment,
		$pause

	) {
		try {
			return ($this->query(
				"insert into Hours (Actual_hours,type,startTime,endTime,overtime,Komment,pause,employee_id ,Date_) Values(?,?,?,?,?,?,?,?,?) ",

				$actual_hours,
				$type,
				$start,
				$end,
				$overtime,
				$comment,
				$pause,
				$id,
				$date
			)
			);
		} catch (Exception $e) {
			return ($this->query(
				"update Hours set Actual_hours=?,type=?,startTime=?,endTime=?,overtime=?,Komment=?,pause=? 
		 where employee_id=? and Date_=?",

				$actual_hours,
				$type,
				$start,
				$end,
				$overtime,
				$comment,
				$pause,
				$id,
				$date
			)
			);
		}
	}


	public function getAllUsers()
	{
		return $this->query("select * from Employee")->fetchAll();
	}

	public function getUserprofile($id)
	{
		return ($this->query("select first_name as 'Username', last_name , C.name as 'CompanyName' , 
		ShouldHours from Employee E 
		join Company C on C.id=E.Company_id where E.id=?", $id))->fetchAll();
	}
	public function getUserDataHour($id)
	{
		$date = date("Y-m-d");
		return ($this->query("select 
		startTime as 'start',endTime as 'end' ,first_name as 'Username'
		from Hours H
		join Employee E on E.id=?
		where (H.Date_)=? and H.employee_id=? ",
			$id,
			$date,
			$id
		)
		)->fetchAll();
	}

	public function getHoursLogMonth($year, $month, $id)
	{
		return ($this->query("select 
		startTime as 'Start',endTime as 'End',type ,Komment,
		Day(Date_) as 'Day' ,pause ,overtime,Actual_hours as 'ActualHours'
		from Hours 
		where year(Date_)=? and Month(Date_)=? and employee_id=? ",
			$year,
			$month,
			$id
		)
		)->fetchAll();
	}

	public function getHoursLogYear($year, $id)
	{
		$this->query("SET lc_time_names = 'de_DE'");
		return ($this->query("
		select 
		MONTHNAME(Date_) as 'Month', 
		ROUND(SUM(Actual_hours),2) as 'TotalHours',
		ROUND(SUM(overtime),2) as 'Overtime'
		from 
		Hours 
		where 
		YEAR(Date_) = ? AND 
		employee_id = ?
group by	MONTH(Date_) ; ",
			$year,
			$id
		)
		)->fetchAll();
	}






	public function __construct($dbhost = '127.0.0.1:3306', $dbuser = 'root', $dbpass = '', $dbname = 'AP', $charset = 'utf8')
	{
		$this->connection = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
		if ($this->connection->connect_error) {
			$this->error('Failed to connect to MySQL :' . $this->connection->connect_error);
		}
		$this->connection->set_charset($charset);

	}
	public function escape($Variable)
	{
		return ($this->connection->real_escape_string($Variable));
	}





	public function query($query)
	{
		if (!$this->query_closed) {
			$this->query->close();
		}
		if ($this->query = $this->connection->prepare($query)) {
			if (func_num_args() > 1) {
				$x = func_get_args();

				$args = array_slice($x, 1);
				$types = '';
				$args_ref = array();
				foreach ($args as $k => &$arg) {
					if (is_array($args[$k])) {
						foreach ($args[$k] as $j => &$a) {
							$types .= $this->_gettype($args[$k][$j]);
							$args_ref[] = &$a;
						}
					} else {
						$types .= $this->_gettype($args[$k]);
						$args_ref[] = &$arg;
					}
				}
				array_unshift($args_ref, $types);
				call_user_func_array(array($this->query, 'bind_param'), $args_ref);
			}
			$this->query->execute();
			if ($this->query->errno) {
				$this->error('Unable to process query (params error) - ' . $this->query->error);
			}
			$this->query_closed = FALSE;
			$this->query_count++;
		} else {
			$this->error('Unable to prepare statement (syntax error) - ' . $this->connection->error);
		}
		return $this;
	}


	public function fetchAll($callback = null)
	{
		$params = array();
		$row = array();
		$meta = $this->query->result_metadata();
		while ($field = $meta->fetch_field()) {
			$params[] = &$row[$field->name];
		}
		call_user_func_array(array($this->query, 'bind_result'), $params);
		$result = array();
		while ($this->query->fetch()) {
			$r = array();
			foreach ($row as $key => $val) {
				$r[$key] = $val;
			}
			if ($callback != null && is_callable($callback)) {
				$value = call_user_func($callback, $r);
				if ($value == 'break')
					break;
			} else {
				$result[] = $r;
			}
		}
		$this->query->close();
		$this->query_closed = TRUE;
		return $result;
	}

	public function fetchArray()
	{
		$params = array();
		$row = array();
		$meta = $this->query->result_metadata();
		while ($field = $meta->fetch_field()) {
			$params[] = &$row[$field->name];
		}
		call_user_func_array(array($this->query, 'bind_result'), $params);
		$result = array();
		while ($this->query->fetch()) {
			foreach ($row as $key => $val) {
				$result[$key] = $val;
			}
		}
		$this->query->close();
		$this->query_closed = TRUE;
		return $result;
	}

	public function close()
	{
		return $this->connection->close();
	}

	public function numRows()
	{
		$this->query->store_result();
		return $this->query->num_rows;
	}

	public function affectedRows()
	{
		return $this->query->affected_rows;
	}

	public function lastInsertID()
	{
		return $this->connection->insert_id;
	}

	public function error($error)
	{
		if ($this->show_errors) {
			exit($error);
		}
	}

	private function _gettype($var)
	{
		if (is_string($var))
			return 's';
		if (is_float($var))
			return 'd';
		if (is_int($var))
			return 'i';
		return 'b';
	}


}




?>