<?php

/**
 * Class Err
 * Built for PHP >= 5.4.0
 * Use in earlier versions will result in an undefined constant error
 */
class Err {

	/**
	 * Termination mode ID for development
	 */
	const MODE_DEVELOPMENT = 0;

	/**
	 * Termination mode ID for production
	 */
	const MODE_PRODUCTION = 1;

	/**
	 * Termination mode ID for silent
	 */
	const MODE_SILENT = 2;

	/**
	 * Error type ID for error
	 */
	const TYPE_ERROR = 0;

	/**
	 * Error type ID for exception
	 */
	const TYPE_EXCEPTION = 1;

	/**
	 * Set on initialisation, a bitwise derived integer of errors that may be set as minor or major
	 * @var integer
	 */
	private static $allowed_errors;

	/**
	 * Number of major errors
	 * @var integer
	 */
	private static $error_count_major = 0;

	/**
	 * Number of minor errors
	 * @var integer
	 */
	private static $error_count_minor = 0;

	/**
	 * Number of terminal errors
	 * @var integer
	 */
	private static $error_count_terminal = 0;

	/**
	 * Holds all error data
	 * @var array
	 */
	private static $errors = [];

	/**
	 * Set on initialisation, a bitwise derived integer made with PHP Error Constants controlling which error codes to log silently
	 * @var integer
	 */
	private static $errors_major;

	/**
	 * Set on initialisation, a bitwise derived integer made with PHP Error Constants controlling which error codes to ignore
	 * @var integer
	 */
	private static $errors_minor;

	/**
	 * Extra data to save with log
	 * @var array
	 */
	private static $extra_log_data;

	/**
	 * The path to log directory, set on initialisation
	 * @var string
	 */
	private static $log_directory;

	/**
	 * The name of the file where logs will be saved
	 * @var string
	 */
	private static $log_file = 'errors.txt';

	/**
	 * Set to true when performShutdownTasks() runs
	 * @var bool
	 */
	private static $shutdown_tasks_complete = false;

	/**
	 * The class and method to call in the event of a terminal error when in development mode
	 * @var string
	 */
	private static $terminal_action_development;

	/**
	 * The class and method to call in the event of a terminal error when in production mode
	 * @var string
	 */
	private static $terminal_action_production;

	/**
	 * Determines action for terminal errors
	 * @var int
	 */
	private static $termination_mode = self::MODE_DEVELOPMENT;

	/**
	 * Timestamp to use in log file with logged errors
	 * @var string
	 */
	private static $timestamp;

	/**
	 * Adds extra details to save with error log. Log array will include an extra
	 * element called "data" which will contain the submitted details. Any existing
	 * keys will be overwritten.
	 * @param $data_array array Data to add to log
	 * @throws Exception If $data_array is not an array
	 */
	public static function addLogData($data_array)
	{
		if (false === is_array($data_array)) {
			throw new Exception('Input must be an array');
		}
		foreach ($data_array as $key => $value) {
			self::$extra_log_data[$key] = $value;
		}
	}

	/**
	 * Handles an error, registered with set_error_handler()
	 * @param $err_no
	 * @param $err_str
	 * @param $err_file
	 * @param $err_line
	 */
	public static function errorHandler($err_no, $err_str, $err_file, $err_line)
	{
		// store error details
		self::$errors[] = [
			'type' => self::TYPE_ERROR,
			'code' => $err_no,
			'message' => $err_str,
			'file' => $err_file,
			'line' => $err_line,
			'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
		];
		// action depends on error type
		if ($err_no & self::$errors_minor) {
			self::$error_count_minor++;
		} else if ($err_no & self::$errors_major) {
			self::$error_count_major++;
		} else {
			self::$error_count_terminal++;
			self::performShutdownTasks();
		}
	}

	/**
	 * Handles an Exception
	 * @param Exception $e
	 */
	public static function exceptionHandler(Exception $e)
	{
		self::$errors[] = [
			'type' => self::TYPE_EXCEPTION,
			'code' => $e->getCode(),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'backtrace' => $e->getTrace()
		];
		self::$error_count_terminal++;
		self::performShutdownTasks();
	}

	/**
	 * Extracts any error data so far then empty the errors array
	 * @param bool $with_counts Return error counts as well?
	 * @return array
	 */
	public static function extract($with_counts = false)
	{
		// prepare return array
		if ($with_counts) {
			$data = [
				'counts' => [
					'minor' => self::$error_count_minor,
					'major' => self::$error_count_major,
					'terminal' => self::$error_count_terminal
				],
				'errors' => self::$errors
			];
		} else {
			$data = self::$errors;
		}
		// reset
		self::$errors = [];
		self::$error_count_major = 0;
		self::$error_count_minor = 0;
		self::$error_count_terminal = 0;
		return $data;
	}

	/**
	 * Returns the last error details if they exist without adjusting the errors array
	 * @return null|array
	 */
	public static function getLast()
	{
		if (self::$errors) {
			$index = count(self::$errors) - 1;
			return self::$errors[$index];
		}
		return null;
	}

	/**
	 * Gets an error name from its integer value
	 * @param $error_code int
	 * @return string The name of the error code submitted
	 */
	public static function getName($error_code)
	{
		switch ($error_code) {
			case E_ERROR: // 1
				return 'E_ERROR';
			case E_WARNING: // 2
				return 'E_WARNING';
			case E_PARSE: // 4
				return 'E_PARSE';
			case E_NOTICE: // 8
				return 'E_NOTICE';
			case E_CORE_ERROR: // 16
				return 'E_CORE_ERROR';
			case E_CORE_WARNING: // 32
				return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: // 64
				return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: // 128
				return 'E_COMPILE_WARNING';
			case E_USER_ERROR: // 256
				return 'E_USER_ERROR';
			case E_USER_WARNING: // 512
				return 'E_USER_WARNING';
			case E_USER_NOTICE: // 1024
				return 'E_USER_NOTICE';
			case E_STRICT: // 2048
				return 'E_STRICT';
			case E_RECOVERABLE_ERROR: // 4096
				return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: // 8192
				return 'E_DEPRECATED';
			case E_USER_DEPRECATED: // 16384
				return 'E_USER_DEPRECATED';
			case E_ALL: // 32767
				return 'E_ALL';
			default:
				return 'UNKNOWN_ERROR_CODE';
		}
	}

	/**
	 * Gets an error type from its integer value
	 * @param $error_type int
	 * @return string The name of the error type submitted
	 */
	public static function getType($error_type)
	{
		switch ($error_type) {
			case self::TYPE_ERROR: // 0
				return 'Error';
			case self::TYPE_EXCEPTION: // 1
				return 'Exception';
			default:
				return 'Unknown error type';
		}
	}

	/**
	 * Initialise error logging
	 * @param array $parameters Array of parameters as expected by setParametersWithArray()
	 * @throws Exception If log file does not exist or cannot be written to
	 */
	public static function initialise($parameters)
	{
		self::setParametersWithArray($parameters);
		// check for write permissions to log file
		$log_file_path = self::$log_directory . '/' . self::$log_file;
		if (false === is_writable($log_file_path)) {
			throw new Exception("Log file ($log_file_path) cannot be written to or does not exist");
		}
		// define errors that may be set as minor or major (errors that can passed to function defined by set_error_handler())
		self::$allowed_errors = E_WARNING | E_NOTICE | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_USER_NOTICE | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
		// use defaults if parameters not set
		if (self::$timestamp === null) {
			self::setTimestamp(date('r'));
		}
		if (self::$errors_minor === null) {
			self::$errors_minor = E_NOTICE | E_USER_NOTICE | E_STRICT;
		} else {
			self::checkErrorsAreValid('minor');
		}
		if (self::$errors_major === null) {
			self::$errors_major = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED | E_USER_DEPRECATED;
		} else {
			self::checkErrorsAreValid('major');
		}
		// do not display errors
		error_reporting(0);
		// register error handling functions
		set_error_handler('Err::errorHandler');
		register_shutdown_function('Err::shutdownFunction');
	}

	/**
	 * Sets the terminal action when in development mode
	 * @param $action
	 * @throws Exception if action is not callable
	 */
	public static function setTerminalActionDevelopment($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$terminal_action_development = $action;
	}

	/**
	 * Sets the terminal action when in production mode
	 * @param $action
	 * @throws Exception if action is not callable
	 */
	public static function setTerminalActionProduction($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$terminal_action_production = $action;
	}

	/**
	 * Sets termination mode
	 * @param int $input
	 * @throws Exception if $input is not valid
	 */
	public static function setTerminationMode($input)
	{
		if (false === in_array($input, [self::MODE_DEVELOPMENT, self::MODE_PRODUCTION, self::MODE_SILENT], true)) {
			throw new Exception('Invalid termination mode submitted');
		}
		self::$termination_mode = $input;
	}

	/**
	 * Sets timestamp for log, typecast as a string
	 * @param string $timestamp
	 */
	public static function setTimestamp($timestamp)
	{
		self::$timestamp = (string) $timestamp;
	}

	/**
	 * Registered as shutdown function. Performs final checks before shutdown tasks are performed.
	 */
	public static function shutdownFunction()
	{
		// no need to run if shutdown tasks have already been completed
		if (self::$shutdown_tasks_complete === true) return;
		// The following are fatal errors which will not be processed by the function set in set_error_handler()
		// They will need to be manually passed to errorHandler()
		$core_fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
		// Get the last error
		$error = error_get_last();
		// If the last error has a match in $core_fatal pass details to errorHandler
		if ($error !== null && ($error['type'] & $core_fatal)) {
			self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		} else {
			self::performShutdownTasks();
		}
	}

	/**
	 * Checks submitted error type contains valid errors
	 * @param $error_type string "major" or "minor"
	 * @throws Exception if $error_type is not valid, or errors in submitted $error_type are not valid
	 */
	private static function checkErrorsAreValid($error_type)
	{
		if (true !== property_exists('Err', "errors_$error_type")) {
			throw new Exception('Invalid error type submitted');
		}
		if ((self::$allowed_errors & self::${"errors_$error_type"}) !== self::${"errors_$error_type"}) {
			throw new Exception("Invalid error types submitted for $error_type errors");
		}
	}

	/**
	 * Saves any errors to log file
	 */
	private static function logErrors()
	{
		if (self::$error_count_terminal > 0 || self::$error_count_major > 0) {
			$file_path = self::$log_directory . '/' . self::$log_file;
			$data['timestamp'] = self::$timestamp;
			$data['terminal'] = (self::$error_count_terminal > 0);
			if (self::$extra_log_data !== null) {
				$data['data'] = self::$extra_log_data;
			}
			$data['log'] = self::$errors;
			file_put_contents($file_path, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
		}
	}

	/**
	 * Perform final tasks. Actions depends on the type of errors
	 * occurred and parameter values.
	 */
	private static function performShutdownTasks()
	{
		self::$shutdown_tasks_complete = true;
		if (self::$error_count_terminal === 0 || self::$termination_mode === self::MODE_SILENT) {
			self::logErrors();
		} else if (self::$termination_mode === self::MODE_DEVELOPMENT) {
			static::terminalActionDevelopment();
			self::logErrors();
		} else if (self::$termination_mode === self::MODE_PRODUCTION) {
			self::logErrors();
			static::terminalActionProduction();
		}
		exit;
	}

	/**
	 * Sets the value for $errors_major, typecast as integer
	 * @param int $errors
	 */
	private static function setErrorsMajor($errors)
	{
		self::$errors_major = (int) $errors;
	}

	/**
	 * Sets the value for $errors_minor, typecast as integer
	 * @param int $errors
	 */
	private static function setErrorsMinor($errors)
	{
		self::$errors_minor = (int) $errors;
	}

	/**
	 * Sets path for log directory, typecast as string
	 * @param string $path
	 */
	private static function setLogDirectory($path)
	{
		self::$log_directory = (string) $path;
	}

	/**
	 * Sets name of log file, typecast as string
	 * @param string $file_name
	 */
	private static function setLogFile($file_name)
	{
		self::$log_file = (string) $file_name;
	}

	/**
	 * Set class parameters using submitted array values
	 * @param $parameters
	 * @throws Exception if $parameters is not an array or a submitted key is not valid
	 */
	private static function setParametersWithArray($parameters)
	{
		if (false === is_array($parameters)) {
			throw new Exception('Parameters must be an array');
		}
		// map accepted parameter keys to class methods
		$key_map = [
			'errors_major' => 'setErrorsMajor',
			'errors_minor' => 'setErrorsMinor',
			'log_data' => 'addLogData',
			'log_directory' => 'setLogDirectory',
			'log_file' => 'setLogFile',
			'terminal_action_development' => 'setTerminalActionDevelopment',
			'terminal_action_production' => 'setTerminalActionProduction',
			'termination_mode' => 'setTerminationMode',
			'timestamp' => 'setTimestamp'
		];
		// check submitted keys are valid and submit values to defined methods
		$invalid_keys = [];
		foreach ($parameters as $name => $value) {
			if (array_key_exists($name, $key_map)) {
				self::$key_map[$name]($value);
			} else {
				$invalid_keys[] = $name;
			}
		}
		if ($invalid_keys) {
			$invalid_key_list = implode(', ', $invalid_keys);
			throw new Exception("Invalid keys ($invalid_key_list) submitted");
		}
	}

	/**
	 * Performs terminal action for development mode
	 */
	private static function terminalActionDevelopment()
	{
		if (self::$terminal_action_development === null) {
			$data = self::extract(true);
			echo '<hr>';
			echo '<h1>PHP script terminated</h1>';
			echo '<hr>';
			echo '<pre>';
			print_r($data['counts']);
			echo '</pre>';
			echo '<hr>';
			echo '<pre>';
			print_r($data['errors']);
			echo '</pre>';
		} else {
			call_user_func(self::$terminal_action_development);
		}
	}

	/**
	 * Performs terminal action for production mode
	 */
	private static function terminalActionProduction()
	{
		if (self::$terminal_action_production === null) {
			echo '<h1>Sorry, an error occurred</h1><hr><p>Details have been logged</p>';
		} else {
			call_user_func(self::$terminal_action_production);
		}
	}
}
