<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/HttpException.php';

/**
 * Thin wrapper around mysqli prepared statements, plus the app's queries.
 */
class Database
{
	protected $connection;
	protected $query;
	protected $query_closed = TRUE;
	public $query_count = 0;

	public function __construct(array $config)
	{
		// Throw mysqli_sql_exception on errors (the default since PHP 8.1, set here explicitly).
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		$this->connection = new mysqli(
			$config['host'],
			$config['user'],
			$config['password'],
			$config['name'],
			$config['port']
		);
		$this->connection->set_charset('utf8mb4');
	}

	// ---------------------------------------------------------------
	// Employees
	// ---------------------------------------------------------------

	public function findEmployeeByUsername(string $username): array
	{
		return $this->query(
			"select id, Username, Password, first_name, is_admin from Employee where Username = ?",
			$username
		)->fetchArray();
	}

	/**
	 * Contracted hours per week, used for the daily target.
	 */
	public function getWeeklyHours(int $id): float
	{
		$row = $this->query("select ShouldHours from Employee where id = ?", $id)->fetchArray();
		if (!$row) {
			throw new HttpException(404, 'Employee not found');
		}
		return (float) $row['ShouldHours'];
	}

	/**
	 * All employees with their hours and overtime for the given month.
	 */
	public function getAllUsers(int $year, int $month): array
	{
		return $this->query(
			"select E.id, E.first_name, E.last_name, E.Username, C.name as CompanyName,
				E.ShouldHours, E.is_admin,
				coalesce(round(sum(H.Actual_hours), 2), 0) as MonthHours,
				coalesce(round(sum(H.overtime), 2), 0) as MonthOvertime
			from Employee E
			left join Company C on C.id = E.Company_id
			left join Hours H on H.employee_id = E.id and year(H.Date_) = ? and month(H.Date_) = ?
			group by E.id, E.first_name, E.last_name, E.Username, C.name, E.ShouldHours, E.is_admin
			order by E.is_admin desc, E.last_name, E.first_name",
			$year,
			$month
		)->fetchAll();
	}

	// ---------------------------------------------------------------
	// Hours
	// ---------------------------------------------------------------

	/**
	 * The employee's name, weekly hours and today's clock times (HH:MM:SS, null if not clocked in yet).
	 */
	public function getUserDataHour(int $id, string $date): array
	{
		return $this->query(
			"select E.first_name as Username, E.ShouldHours,
				TIME_FORMAT(H.startTime, '%H:%i:%s') as start,
				TIME_FORMAT(H.endTime, '%H:%i:%s') as end
			from Employee E
			left join Hours H on H.employee_id = E.id and H.Date_ = ?
			where E.id = ?",
			$date,
			$id
		)->fetchArray();
	}

	public function getDay(int $id, string $date): array
	{
		return $this->query(
			"select TIME_FORMAT(startTime, '%H:%i:%s') as start, TIME_FORMAT(endTime, '%H:%i:%s') as end, pause
			from Hours where employee_id = ? and Date_ = ?",
			$id,
			$date
		)->fetchArray();
	}

	public function clockIn(int $id, string $date, string $time): void
	{
		$this->query(
			"insert into Hours (employee_id, Date_, startTime, type) values (?, ?, ?, 'Normal')",
			$id,
			$date,
			$time
		);
	}

	public function clockOut(int $id, string $date, string $time, int $pause, float $actualHours, float $overtime): void
	{
		$this->query(
			"update Hours set endTime = ?, pause = ?, Actual_hours = ?, overtime = ?
			where employee_id = ? and Date_ = ?",
			$time,
			$pause,
			$actualHours,
			$overtime,
			$id,
			$date
		);
	}

	/**
	 * Inserts or replaces one day's entry (one row per employee and day).
	 */
	public function editLog(
		int $id,
		float $actual_hours,
		?string $start,
		?string $end,
		float $overtime,
		string $type,
		string $date,
		string $comment,
		int $pause
	): void {
		$this->query(
			"insert into Hours (Actual_hours, type, startTime, endTime, overtime, Komment, pause, employee_id, Date_)
			values (?, ?, ?, ?, ?, ?, ?, ?, ?)
			on duplicate key update
				Actual_hours = values(Actual_hours), type = values(type),
				startTime = values(startTime), endTime = values(endTime),
				overtime = values(overtime), Komment = values(Komment), pause = values(pause)",
			$actual_hours,
			$type,
			$start,
			$end,
			$overtime,
			$comment,
			$pause,
			$id,
			$date
		);
	}

	public function getHoursLogMonth(int $year, int $month, int $id): array
	{
		return $this->query(
			"select
				TIME_FORMAT(startTime, '%H:%i') as 'Start', TIME_FORMAT(endTime, '%H:%i') as 'End',
				type, Komment, Day(Date_) as 'Day', pause, overtime, Actual_hours as 'ActualHours'
			from Hours
			where year(Date_) = ? and month(Date_) = ? and employee_id = ?
			order by Date_",
			$year,
			$month,
			$id
		)->fetchAll();
	}

	/**
	 * Hours, overtime and paid absence days (vacation, sick, holiday) per month.
	 * Month is a number (1-12) so the result does not depend on the database's locale.
	 */
	public function getHoursLogYear(int $year, int $id): array
	{
		return $this->query(
			"select
				MONTH(Date_) as 'Month',
				ROUND(SUM(Actual_hours), 2) as 'TotalHours',
				ROUND(SUM(overtime), 2) as 'Overtime',
				SUM(type in ('Urlaub', 'Krank', 'Feiertag')) as 'AbsenceDays'
			from Hours
			where YEAR(Date_) = ? and employee_id = ?
			group by MONTH(Date_)
			order by MONTH(Date_)",
			$year,
			$id
		)->fetchAll();
	}

	// ---------------------------------------------------------------
	// Prepared-statement helpers
	// ---------------------------------------------------------------

	/**
	 * Prepares and runs a query. Extra arguments are bound as parameters.
	 */
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

	/**
	 * Returns the last row of the result as an associative array, or [] if empty.
	 */
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

	/**
	 * Raises database errors as exceptions. The front controller logs them
	 * and answers with a generic message, so SQL details never reach the browser.
	 */
	public function error($error)
	{
		throw new RuntimeException($error);
	}

	private function _gettype($var)
	{
		if (is_float($var))
			return 'd';
		if (is_int($var) || is_bool($var))
			return 'i';
		return 's'; // strings and null
	}
}
