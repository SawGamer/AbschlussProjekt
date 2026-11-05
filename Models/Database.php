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

	/** Columns an admin may change, mapped from API field names. */
	private const EMPLOYEE_FIELDS = [
		'username' => 'Username',
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'weekly_hours' => 'ShouldHours',
		'vacation_days' => 'vacation_days',
		'is_admin' => 'is_admin',
		'active' => 'active',
	];

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
		// Timestamps written by the database (activity log, decisions) use the app's time zone.
		$this->connection->query("SET time_zone = '" . date('P') . "'");
	}

	/**
	 * Runs $work inside a transaction and rolls back if it throws.
	 */
	public function transaction(callable $work)
	{
		$this->connection->begin_transaction();
		try {
			$result = $work();
			$this->connection->commit();
			return $result;
		} catch (Throwable $e) {
			$this->connection->rollback();
			throw $e;
		}
	}

	// ---------------------------------------------------------------
	// Employees
	// ---------------------------------------------------------------

	public function findEmployeeByUsername(string $username): array
	{
		return $this->query(
			"select id, Username, Password, first_name, is_admin, active, token_version
			from Employee where Username = ?",
			$username
		)->fetchArray();
	}

	/**
	 * What the token check needs: role, whether the account is active, token version.
	 */
	public function getAuthState(int $id): array
	{
		return $this->query(
			"select id, first_name, is_admin, active, token_version, Password from Employee where id = ?",
			$id
		)->fetchArray();
	}

	/** Invalidates every token issued so far for this employee. */
	public function bumpTokenVersion(int $id): int
	{
		$this->query("update Employee set token_version = token_version + 1 where id = ?", $id);
		return (int) $this->query("select token_version from Employee where id = ?", $id)->fetchArray()['token_version'];
	}

	public function setPassword(int $id, string $hash): void
	{
		$this->query("update Employee set Password = ?, token_version = token_version + 1 where id = ?", $hash, $id);
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

	public function getEmployee(int $id): array
	{
		return $this->query(
			"select id, Username as username, first_name, last_name, ShouldHours as weekly_hours,
				vacation_days, is_admin, active
			from Employee where id = ?",
			$id
		)->fetchArray();
	}

	/**
	 * Every employee with this month's hours and this year's vacation figures.
	 */
	public function listEmployees(int $year, int $month): array
	{
		return $this->query(
			"select E.id, E.Username as username, E.first_name, E.last_name, C.name as company,
				E.ShouldHours as weekly_hours, E.vacation_days, E.is_admin, E.active,
				coalesce((select round(sum(H.Actual_hours), 2) from Hours H
					where H.employee_id = E.id and year(H.Date_) = ? and month(H.Date_) = ?), 0) as month_hours,
				coalesce((select round(sum(H.overtime), 2) from Hours H
					where H.employee_id = E.id and year(H.Date_) = ? and month(H.Date_) = ?), 0) as month_overtime,
				(select count(*) from Hours H
					where H.employee_id = E.id and year(H.Date_) = ? and H.type = 'Urlaub') as vacation_taken,
				coalesce((select sum(L.days) from LeaveRequest L
					where L.employee_id = E.id and L.status = 'pending' and year(L.start_date) = ?), 0) as vacation_pending
			from Employee E
			left join Company C on C.id = E.Company_id
			order by E.active desc, E.last_name, E.first_name",
			$year, $month, $year, $month, $year, $year
		)->fetchAll();
	}

	public function usernameTaken(string $username, int $exceptId = 0): bool
	{
		return (bool) $this->query(
			"select id from Employee where Username = ? and id <> ?",
			$username,
			$exceptId
		)->fetchArray();
	}

	public function createEmployee(array $data, string $passwordHash): int
	{
		$this->query(
			"insert into Employee (Username, Password, first_name, last_name, Company_id, ShouldHours, vacation_days, is_admin)
			values (?, ?, ?, ?, 1, ?, ?, ?)",
			$data['username'],
			$passwordHash,
			$data['first_name'],
			$data['last_name'],
			$data['weekly_hours'],
			$data['vacation_days'],
			(int) $data['is_admin']
		);
		return (int) $this->lastInsertID();
	}

	/**
	 * Updates the given fields (API names, see EMPLOYEE_FIELDS).
	 */
	public function updateEmployee(int $id, array $fields): void
	{
		$sets = [];
		$values = [];
		foreach ($fields as $field => $value) {
			if (!isset(self::EMPLOYEE_FIELDS[$field])) {
				throw new InvalidArgumentException("Unknown employee field $field");
			}
			$sets[] = self::EMPLOYEE_FIELDS[$field] . ' = ?';
			$values[] = is_bool($value) ? (int) $value : $value;
		}
		if ($sets) {
			$values[] = $id;
			$this->query('update Employee set ' . implode(', ', $sets) . ' where id = ?', $values);
		}
	}

	// ---------------------------------------------------------------
	// Hours
	// ---------------------------------------------------------------

	/**
	 * The employee's name, weekly hours and today's entry (times as HH:MM:SS).
	 */
	public function getUserDataHour(int $id, string $date): array
	{
		return $this->query(
			"select E.first_name as Username, E.ShouldHours,
				TIME_FORMAT(H.startTime, '%H:%i:%s') as start,
				TIME_FORMAT(H.endTime, '%H:%i:%s') as end,
				H.pause, H.type, H.Actual_hours as actual_hours
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
			"select TIME_FORMAT(startTime, '%H:%i:%s') as start, TIME_FORMAT(endTime, '%H:%i:%s') as end, pause, type
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
	 * Entries in a date range, keyed by date. Used for balances and the CSV export.
	 *
	 * @return array<string, array>
	 */
	public function getHoursBetween(int $id, string $from, string $to): array
	{
		$rows = $this->query(
			"select DATE_FORMAT(Date_, '%Y-%m-%d') as date,
				TIME_FORMAT(startTime, '%H:%i') as start, TIME_FORMAT(endTime, '%H:%i') as end,
				pause, Actual_hours as actual, overtime, type, Komment as comment
			from Hours
			where employee_id = ? and Date_ between ? and ?
			order by Date_",
			$id,
			$from,
			$to
		)->fetchAll();

		$byDate = [];
		foreach ($rows as $row) {
			$byDate[$row['date']] = $row;
		}
		return $byDate;
	}

	/** Removes vacation entries that were booked by an approved leave request. */
	public function deleteVacationDays(int $id, string $from, string $to): void
	{
		$this->query(
			"delete from Hours where employee_id = ? and Date_ between ? and ? and type = 'Urlaub' and startTime is null",
			$id,
			$from,
			$to
		);
	}

	// ---------------------------------------------------------------
	// Breaks
	// ---------------------------------------------------------------

	public function getBreaks(int $id, string $date): array
	{
		return $this->query(
			"select TIME_FORMAT(startTime, '%H:%i:%s') as start, TIME_FORMAT(endTime, '%H:%i:%s') as end
			from Breaks where employee_id = ? and Date_ = ? order by startTime",
			$id,
			$date
		)->fetchAll();
	}

	/** Breaks of every employee on one day, grouped by employee id. */
	public function getBreaksForDate(string $date): array
	{
		$rows = $this->query(
			"select employee_id, TIME_FORMAT(startTime, '%H:%i:%s') as start, TIME_FORMAT(endTime, '%H:%i:%s') as end
			from Breaks where Date_ = ? order by startTime",
			$date
		)->fetchAll();

		$grouped = [];
		foreach ($rows as $row) {
			$grouped[$row['employee_id']][] = ['start' => $row['start'], 'end' => $row['end']];
		}
		return $grouped;
	}

	public function startBreak(int $id, string $date, string $time): void
	{
		$this->query("insert into Breaks (employee_id, Date_, startTime) values (?, ?, ?)", $id, $date, $time);
	}

	public function endOpenBreak(int $id, string $date, string $time): void
	{
		$this->query(
			"update Breaks set endTime = ? where employee_id = ? and Date_ = ? and endTime is null",
			$time,
			$id,
			$date
		);
	}

	// ---------------------------------------------------------------
	// Admin overview
	// ---------------------------------------------------------------

	/**
	 * Every active employee with today's entry, for the attendance board.
	 */
	public function getTodayAll(string $date): array
	{
		return $this->query(
			"select E.id, E.first_name, E.last_name, E.ShouldHours as weekly_hours,
				TIME_FORMAT(H.startTime, '%H:%i:%s') as start, TIME_FORMAT(H.endTime, '%H:%i:%s') as end,
				H.pause, H.type, H.Actual_hours as actual_hours
			from Employee E
			left join Hours H on H.employee_id = E.id and H.Date_ = ?
			where E.active = 1
			order by E.last_name, E.first_name",
			$date
		)->fetchAll();
	}

	// ---------------------------------------------------------------
	// Leave requests
	// ---------------------------------------------------------------

	public function createLeave(int $id, string $start, string $end, int $days, string $comment): int
	{
		$this->query(
			"insert into LeaveRequest (employee_id, start_date, end_date, days, comment) values (?, ?, ?, ?, ?)",
			$id,
			$start,
			$end,
			$days,
			$comment
		);
		return (int) $this->lastInsertID();
	}

	public function getLeave(int $requestId): array
	{
		return $this->query(
			"select id, employee_id, DATE_FORMAT(start_date, '%Y-%m-%d') as start_date,
				DATE_FORMAT(end_date, '%Y-%m-%d') as end_date, days, comment, status
			from LeaveRequest where id = ?",
			$requestId
		)->fetchArray();
	}

	/**
	 * Leave requests, newest first. Optionally for one employee and/or one status group.
	 */
	public function listLeave(?int $employeeId = null, ?string $statusGroup = null): array
	{
		$where = [];
		$params = [];
		if ($employeeId !== null) {
			$where[] = 'L.employee_id = ?';
			$params[] = $employeeId;
		}
		if ($statusGroup === 'pending') {
			$where[] = "L.status = 'pending'";
		} elseif ($statusGroup === 'decided') {
			$where[] = "L.status <> 'pending'";
		}

		return $this->query(
			"select L.id, L.employee_id, E.first_name, E.last_name,
				DATE_FORMAT(L.start_date, '%Y-%m-%d') as start_date,
				DATE_FORMAT(L.end_date, '%Y-%m-%d') as end_date,
				L.days, L.comment, L.status, L.decision_note,
				DATE_FORMAT(L.decided_at, '%Y-%m-%d %H:%i') as decided_at,
				D.first_name as decided_by,
				DATE_FORMAT(L.created_at, '%Y-%m-%d %H:%i') as created_at
			from LeaveRequest L
			join Employee E on E.id = L.employee_id
			left join Employee D on D.id = L.decided_by"
			. ($where ? ' where ' . implode(' and ', $where) : '')
			. " order by L.status = 'pending' desc, L.start_date desc, L.id desc",
			$params
		)->fetchAll();
	}

	/** True if a pending or approved request overlaps the range. */
	public function leaveOverlaps(int $id, string $start, string $end): bool
	{
		return (bool) $this->query(
			"select id from LeaveRequest
			where employee_id = ? and status in ('pending', 'approved') and start_date <= ? and end_date >= ?",
			$id,
			$end,
			$start
		)->fetchArray();
	}

	public function setLeaveStatus(int $requestId, string $status, ?int $deciderId, string $note = ''): void
	{
		$this->query(
			"update LeaveRequest set status = ?, decided_by = ?, decided_at = now(), decision_note = ? where id = ?",
			$status,
			$deciderId,
			$note,
			$requestId
		);
	}

	/**
	 * Vacation figures for one employee and year: entitlement, booked days, pending days.
	 */
	public function vacationFigures(int $id, int $year): array
	{
		$row = $this->query(
			"select E.vacation_days as entitlement,
				(select count(*) from Hours H where H.employee_id = E.id and year(H.Date_) = ? and H.type = 'Urlaub') as taken,
				coalesce((select sum(L.days) from LeaveRequest L
					where L.employee_id = E.id and L.status = 'pending' and year(L.start_date) = ?), 0) as pending
			from Employee E where E.id = ?",
			$year,
			$year,
			$id
		)->fetchArray();

		$entitlement = (int) ($row['entitlement'] ?? 0);
		$taken = (int) ($row['taken'] ?? 0);
		$pending = (int) ($row['pending'] ?? 0);
		return [
			'entitlement' => $entitlement,
			'taken' => $taken,
			'pending' => $pending,
			'remaining' => $entitlement - $taken - $pending,
		];
	}

	// ---------------------------------------------------------------
	// Activity log
	// ---------------------------------------------------------------

	public function audit(?int $actorId, ?int $subjectId, string $action, string $details = ''): void
	{
		$this->query(
			"insert into AuditLog (actor_id, subject_id, action, details) values (?, ?, ?, ?)",
			$actorId,
			$subjectId,
			$action,
			mb_substr($details, 0, 500)
		);
	}

	public function listAudit(int $limit = 100): array
	{
		return $this->query(
			"select A.id, A.action, A.details, DATE_FORMAT(A.created_at, '%Y-%m-%d %H:%i') as created_at,
				concat(AE.first_name, ' ', AE.last_name) as actor,
				concat(SE.first_name, ' ', SE.last_name) as subject
			from AuditLog A
			left join Employee AE on AE.id = A.actor_id
			left join Employee SE on SE.id = A.subject_id
			order by A.id desc
			limit ?",
			$limit
		)->fetchAll();
	}

	// ---------------------------------------------------------------
	// Prepared-statement helpers
	// ---------------------------------------------------------------

	/**
	 * Prepares and runs a query. Extra arguments are bound as parameters;
	 * an array argument is expanded into several parameters.
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
				if ($types !== '') {
					array_unshift($args_ref, $types);
					call_user_func_array(array($this->query, 'bind_param'), $args_ref);
				}
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
