<?php

/**
 * v1.0.0-beta
 * Handles all PHP errors, logging if needed
 * Built for PHP >= 5.4.0
 * Use in earlier versions will result in an undefined constant error
 */
class Err {

	/**
	 * ID for class fatal error
	 */
	const ERR_FATAL = 0;

	/**
	 * ID for class major error
	 */
	const ERR_MAJOR = 1;

	/**
	 * ID for class minor error
	 */
	const ERR_MINOR = 2;

	/**
	 * Mode ID for custom
	 */
	const MODE_CUSTOM = 0;

	/**
	 * Mode ID for development
	 */
	const MODE_DEVELOPMENT = 1;

	/**
	 * Mode ID for production
	 */
	const MODE_PRODUCTION = 2;

	/**
	 * Mode ID for silent
	 */
	const MODE_SILENT = 3;

	/**
	 * Error type ID for class error
	 */
	const TYPE_CLASS_ERROR = 0;

	/**
	 * Error type ID for PHP error
	 */
	const TYPE_PHP_ERROR = 1;

	/**
	 * Error type ID for exception
	 */
	const TYPE_EXCEPTION = 2;

	/**
	 * Major error count
	 * @var integer
	 */
	private static $error_count_major = 0;

	/**
	 * Minor error count
	 * @var integer
	 */
	private static $error_count_minor = 0;

	/**
	 * Fatal error count
	 * @var integer
	 */
	private static $error_count_fatal = 0;

	/**
	 * Holds all error data
	 * @var array
	 */
	private static $errors = [];

	/**
	 * Errors to handle as major, set on initialisation
	 * @var integer
	 */
	private static $errors_major;

	/**
	 * Errors to handle as minor, set on initialisation
	 * @var integer
	 */
	private static $errors_minor;

	/**
	 * Errors that may be set as minor or major, set on initialisation
	 * @var integer
	 */
	private static $errors_settable;

	/**
	 * Extra data to save with log
	 * @var array
	 */
	private static $extra_log_data;

	/**
	 * The class and method to call in the event of a fatal error when in custom mode
	 * @var string
	 */
	private static $fatal_action_custom;

	/**
	 * The class and method to call in the event of a fatal error when in development mode
	 * @var string
	 */
	private static $fatal_action_development;

	/**
	 * The class and method to call in the event of a fatal error when in production mode
	 * @var string
	 */
	private static $fatal_action_production;

	/**
	 * The class and method to call in the event of a fatal error when in silent mode
	 * @var string
	 */
	private static $fatal_action_silent;

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
	 * Class mode, determines action for fatal errors
	 * @var int
	 */
	private static $mode = self::MODE_DEVELOPMENT;

	/**
	 * Timestamp to use in log file with logged errors, set on initialisation
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
					'fatal' => self::$error_count_fatal
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
		self::$error_count_fatal = 0;
		return $data;
	}

	/**
	 * Gets a class error name from its integer value
	 * @param int $id
	 * @return string The name of the error code submitted
	 */
	public static function getClassErrorName($id)
	{
		switch ($id) {
			case self::ERR_FATAL: // 0
				return 'ERR_FATAL';
			case self::ERR_MAJOR: // 1
				return 'ERR_MAJOR';
			case self::ERR_MINOR: // 2
				return 'ERR_MINOR';
			default:
				return 'UNKNOWN_ERR';
		}
	}

	/**
	 * Returns the last error details, if they exist, without adjusting the errors array
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
	 * Returns extra log data
	 * @return null|array
	 */
	public static function getLogData()
	{
		return self::$extra_log_data;
	}

	/**
	 * Gets an error name from its integer value
	 * @param int $id
	 * @return string The name of the error code submitted
	 */
	public static function getPhpErrorName($id)
	{
		switch ($id) {
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
				return 'UNKNOWN_PHP_ERROR';
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
			case self::TYPE_CLASS_ERROR: // 0
				return 'Class Error';
			case self::TYPE_PHP_ERROR: // 1
				return 'PHP Error';
			case self::TYPE_EXCEPTION: // 2
				return 'Exception';
			default:
				return 'Unknown error type';
		}
	}

	/**
	 * Handles an error, registered with set_error_handler()
	 * @param $err_no
	 * @param $err_str
	 * @param $err_file
	 * @param $err_line
	 */
	public static function handleError($err_no, $err_str, $err_file, $err_line)
	{
		// store error details
		self::$errors[] = [
			'type' => self::TYPE_PHP_ERROR,
			'code' => $err_no,
			'message' => $err_str,
			'file' => $err_file,
			'line' => $err_line,
			'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
		];
		// action depends on error type
		if ($err_no & self::$errors_major) {
			self::$error_count_major++;
		} else if ($err_no & self::$errors_minor) {
			self::$error_count_minor++;
		} else {
			self::$error_count_fatal++;
			self::performShutdownTasks();
		}
	}

	/**
	 * Handles an Exception, registered with set_exception_handler()
	 * @param Exception $e
	 */
	public static function handleException(Exception $e)
	{
		self::$errors[] = [
			'type' => self::TYPE_EXCEPTION,
			'name' => get_class($e),
			'code' => $e->getCode(),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'backtrace' => $e->getTrace()
		];
		self::$error_count_fatal++;
		self::performShutdownTasks();
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
			self::triggerFatal("Log file ($log_file_path) cannot be written to or does not exist");
		}
		// define errors that may be set as minor or major (errors that can passed to function defined by set_error_handler())
		self::$errors_settable = E_WARNING | E_NOTICE | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_USER_NOTICE | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
		// use defaults if parameters not set
		if (self::$timestamp === null) {
			self::setTimestamp(date('c'));
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
		// register functions
		set_error_handler('Err::handleError');
		set_exception_handler('Err::handleException');
		register_shutdown_function('Err::shutdownFunction');
	}

	/**
	 * Checks if logging is required, logs if needed, clears log
	 * @return bool If logging occurred true, otherwise false
	 */
	public static function logErrors()
	{
		if (self::$error_count_fatal > 0 || self::$error_count_major > 0) {
			$file_path = self::$log_directory . '/' . self::$log_file;
			$data['timestamp'] = self::$timestamp;
			$data['fatal_type'] = (self::$error_count_fatal > 0) ? self::getLast()['type'] : null;
			if (self::$extra_log_data !== null) {
				$data['data'] = self::$extra_log_data;
			}
			$data['errors'] = self::extract();
			file_put_contents($file_path, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
			return true;
		}
		return false;
	}

	/**
	 * Sets the fatal action when in custom mode
	 * @param string $action Example "Class::Method"
	 * @throws Exception if action is not callable
	 */
	public static function setFatalActionCustom($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$fatal_action_custom = $action;
	}

	/**
	 * Sets the fatal action when in development mode
	 * @param string $action Example "Class::Method"
	 * @throws Exception if action is not callable
	 */
	public static function setFatalActionDevelopment($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$fatal_action_development = $action;
	}

	/**
	 * Sets the fatal action when in production mode
	 * @param string $action Example "Class::Method"
	 * @throws Exception if action is not callable
	 */
	public static function setFatalActionProduction($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$fatal_action_production = $action;
	}

	/**
	 * Sets the fatal action when in silent mode
	 * @param string $action Example "Class::Method"
	 * @throws Exception if action is not callable
	 */
	public static function setFatalActionSilent($action)
	{
		$parts = explode('::', $action, 2);
		if (false === is_callable($parts)) {
			throw new Exception("Submitted action ($action) is not callable");
		}
		self::$fatal_action_silent = $action;
	}

	/**
	 * Sets mode which determines action in the event of a fatal error
	 * @param int $input
	 * @throws Exception if $input is not valid
	 */
	public static function setMode($input)
	{
		if (false === in_array($input, [self::MODE_CUSTOM, self::MODE_DEVELOPMENT, self::MODE_PRODUCTION, self::MODE_SILENT], true)) {
			throw new Exception('Invalid mode submitted');
		}
		self::$mode = $input;
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
		// They will need to be manually passed to handleError()
		$core_fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
		// Get the last error
		$error = error_get_last();
		// If the last error has a match in $core_fatal pass details to handleError()
		if ($error !== null && ($error['type'] & $core_fatal)) {
			self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
		} else {
			self::performShutdownTasks();
		}
	}

	/**
	 * Triggers a fatal error
	 * @param string $message
	 */
	public static function triggerFatal($message)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$details = array_shift($backtrace);
		self::$errors[] = [
			'type' => self::TYPE_CLASS_ERROR,
			'code' => self::ERR_FATAL,
			'message' => $message,
			'file' => $details['file'],
			'line' => $details['line'],
			'backtrace' => $backtrace
		];
		self::$error_count_fatal++;
		self::performShutdownTasks();
	}

	/**
	 * Triggers a major error
	 * @param string $message
	 */
	public static function triggerMajor($message)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$details = array_shift($backtrace);
		self::$errors[] = [
			'type' => self::TYPE_CLASS_ERROR,
			'code' => self::ERR_MAJOR,
			'message' => $message,
			'file' => $details['file'],
			'line' => $details['line'],
			'backtrace' => $backtrace
		];
		self::$error_count_major++;
	}

	/**
	 * Triggers a minor error
	 * @param string $message
	 */
	public static function triggerMinor($message)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$details = array_shift($backtrace);
		self::$errors[] = [
			'type' => self::TYPE_CLASS_ERROR,
			'code' => self::ERR_MINOR,
			'message' => $message,
			'file' => $details['file'],
			'line' => $details['line'],
			'backtrace' => $backtrace
		];
		self::$error_count_minor++;
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
		if ((self::$errors_settable & self::${"errors_$error_type"}) !== self::${"errors_$error_type"}) {
			throw new Exception("Invalid error types submitted for $error_type errors");
		}
	}

	/**
	 * Performs fatal action for custom mode
	 */
	private static function fatalActionCustom()
	{
		if (self::$fatal_action_custom === null) {
			self::logErrors();
			echo '<h1>Custom fatal action has run</h1><hr><p>Errors logged</p>';
		} else {
			call_user_func(self::$fatal_action_custom);
		}
	}

	/**
	 * Performs fatal action for development mode
	 */
	private static function fatalActionDevelopment()
	{
		if (self::$fatal_action_development === null) {
			$last_error = self::getLast();
			switch ($last_error['type']) {
				case self::TYPE_CLASS_ERROR:
					$heading = 'Err: '.self::getClassErrorName($last_error['code']);
					break;
				case self::TYPE_PHP_ERROR:
					$heading = 'PHP Error: ' . self::getPhpErrorName($last_error['code']);
					break;
				default: // self::TYPE_EXCEPTION
					$heading = 'Uncaught ' . $last_error['name'];
			}
			$data = self::extract(true);
			echo '<!DOCTYPE html><head><meta charset="utf-8"><title>Fatal error</title>';
			echo '<style>body{font-family:Helvetica,Arial,sans-serif;text-align:center}';
			echo 'div{margin:50px auto;text-align:left;width:100%;max-width:1000px}</style></head>';
			echo '<body><div><h1>' . $heading . '</h1><hr><pre>';
			print_r($data['counts']);
			echo '</pre><hr><pre>';
			print_r($data['errors']);
			echo '</pre></div></body></html>';
		} else {
			call_user_func(self::$fatal_action_development);
		}
	}

	/**
	 * Performs fatal action for production mode
	 */
	private static function fatalActionProduction()
	{
		if (self::$fatal_action_production === null) {
			self::logErrors();
			echo '<!DOCTYPE html><head><title>Fatal error</title>';
			echo '<style>body{text-align:center;font-family:Helvetica,Arial,sans-serif}h1{margin-top:50px}</style>';
			echo '</head><body><h1>Application error</h1><p>Details have been logged</p></body></html>';
		} else {
			call_user_func(self::$fatal_action_production);
		}
	}

	/**
	 * Performs fatal action for silent mode
	 */
	private static function fatalActionSilent()
	{
		if (self::$fatal_action_silent === null) {
			self::logErrors();
		} else {
			call_user_func(self::$fatal_action_production);
		}
	}

	/**
	 * Performs final tasks based on errors and mode
	 */
	private static function performShutdownTasks()
	{
		self::$shutdown_tasks_complete = true;
		if (self::$error_count_fatal === 0) {
			self::logErrors();
		} else if (self::$mode === self::MODE_CUSTOM) {
			self::fatalActionCustom();
		} else if (self::$mode === self::MODE_DEVELOPMENT) {
			self::fatalActionDevelopment();
		} else if (self::$mode === self::MODE_PRODUCTION) {
			self::fatalActionProduction();
		} else { // self::$mode === self::MODE_SILENT
			self::fatalActionSilent();
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
			'fatal_action_development' => 'setFatalActionDevelopment',
			'fatal_action_production' => 'setFatalActionProduction',
			'fatal_action_silent' => 'setFatalActionSilent',
			'log_data' => 'addLogData',
			'log_directory' => 'setLogDirectory',
			'log_file' => 'setLogFile',
			'mode' => 'setMode',
			'timestamp' => 'setTimestamp'
		];
		// check submitted keys are valid and submit values to defined methods
		$invalid_keys = [];
		foreach ($parameters as $name => $value) {
			if (array_key_exists($name, $key_map)) {
				$method = $key_map[$name];
				self::$method($value);
			} else {
				$invalid_keys[] = $name;
			}
		}
		if ($invalid_keys) {
			$invalid_key_list = implode(', ', $invalid_keys);
			throw new Exception("Invalid keys ($invalid_key_list) submitted");
		}
	}
}
