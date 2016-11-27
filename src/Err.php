<?php

/**
 * Class Err
 * Built for PHP >= 5.4.0.
 * Use in earlier versions will result in an undefined constant error.
 */
class Err {

	/**
	 * @var integer Set on initialisation. A bitwise derived integer of errors
	 * that may be set as ignore or background.
	 */
	private static $allowed_errors = false;

	/**
	 * @var string The name of the class to use for error handling (maybe an extension of this class)
	 */
	private static $class_name = 'Err';

	/**
	 * @var bool Determines if application is in development or production mode
	 */
	private static $development = true;

	/**
	 * @var integer Number of background errors
	 */
	private static $error_count_background = 0;

	/**
	 * @var integer Number of ignored errors
	 */
	private static $error_count_ignore = 0;

	/**
	 * @var integer Number of terminal errors
	 */
	private static $error_count_terminal = 0;

	/**
	 * @var array Holds all error data
	 */
	private static $errors = [];

	/**
	 * @var integer Set on initialisation. A bitwise derived integer made with
	 * PHP Error Constants controlling which error codes to log silently.
	 */
	private static $errors_background = false;

	/**
	 * @var integer Set on initialisation. A bitwise derived integer made with
	 * PHP Error Constants controlling which error codes to ignore.
	 */
	private static $errors_ignore = false;

	/**
	 * @var array Extra data to save with log
	 */
	protected static $extra_log_data = [];

	/**
	 * @var string The path to log directory
	 */
	private static $log_directory = '';

	/**
	 * @var string The name of the file where background errors will be logged
	 */
	private static $log_file_background = 'background.txt';

	/**
	 * @var string The name of the file where terminal errors will be logged
	 */
	private static $log_file_terminal = 'terminal.txt';

	/**
	 * @var bool Set to true when performShutdownTasks() runs
	 */
	private static $shutdown_tasks_complete = false;

	/**
	 * @var string Timestamp to use in log file with logged errors
	 */
	private static $timestamp = '';

	/**
	 * Add extra details to save with error log. Log array will include an extra
	 * element called "data" which will contain the submitted details. Array must
	 * be 1 dimensional, values are typecast as strings.
	 * @param $data_array array Data to add to log
	 * @throws Exception If $data_array is not an array
	 */
	public static function addLogData($data_array)
	{
		if (!is_array($data_array)) {
			throw new Exception('Input must be an array');
		}

		foreach ($data_array as $key => $value) {
			self::$extra_log_data[$key] = (string) $value;
		}
	}

	/**
	 * Custom error handler registered with set_error_handler()
	 * @param $err_no
	 * @param $err_str
	 * @param $err_file
	 * @param $err_line
	 */
	public static function errorHandler($err_no, $err_str, $err_file, $err_line)
	{
		// store error details
		self::$errors[] = [
			'error'     => $err_no,
			'message'   => $err_str,
			'file'      => $err_file,
			'line'      => $err_line,
			'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
		];

		// action depends on error type
		if (self::$errors_ignore & $err_no) {
			self::$error_count_ignore++;
		} else if (self::$errors_background & $err_no) {
			self::$error_count_background++;
		} else {
			self::$error_count_terminal++;
			self::performShutdownTasks();
		}
	}

	/**
	 * Extract any error data so far then reset
	 * @param bool $with_counts Return error counts as well?
	 * @return array
	 */
	public static function extract($with_counts = false)
	{
		// prepare return array
		if ($with_counts) {
			$data = [
				'counts' => [
					'ignore' => self::$error_count_ignore,
					'background' => self::$error_count_background,
					'terminal' => self::$error_count_terminal
				],
				'errors' => self::$errors
			];
		} else {
			$data = self::$errors;
		}

		// reset
		self::$errors = [];
		self::$error_count_background = 0;
		self::$error_count_ignore = 0;
		self::$error_count_terminal = 0;

		return $data;
	}

	/**
	 * Returns the last error details if they exist
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
	 * Get an error name from its integer value
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
	 * Initialise error logging
	 * @param null|array $parameters Array of parameters as expected by setParametersWithArray()
	 * @throws Exception If log files do not exist or cannot be written to
	 */
	public static function initialise($parameters = null)
	{
		if ($parameters !== null) {
			self::setParametersWithArray($parameters);
		}

		// check for write permissions to log files
		if (!is_writable(self::$log_directory . '/' . self::$log_file_background) || !is_writable(self::$log_directory . '/' . self::$log_file_terminal)) {
			throw new Exception('Err class cannot write to log files or log files do not exist.');
		}

		// define errors that may be set as ignore or background
		self::$allowed_errors = E_WARNING | E_NOTICE | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_USER_NOTICE | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED;

		// use defaults if parameters not set
		if (self::$timestamp === '') {
			self::$timestamp = time();
		}

		if (self::$errors_ignore === false) {
			self::$errors_ignore = E_NOTICE | E_USER_NOTICE | E_STRICT;
		} else {
			self::checkErrorsAreValid('ignore');
		}

		if (self::$errors_background === false) {
			self::$errors_background = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED | E_USER_DEPRECATED;
		} else {
			self::checkErrorsAreValid('background');
		}

		// do not display errors
		error_reporting(0);

		// register error handling functions
		set_error_handler(static::$class_name . '::errorHandler');
		register_shutdown_function(static::$class_name . '::shutdownFunction');
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
		if ($error !== NULL && ($error['type'] & $core_fatal)) {
			self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		} else {
			self::performShutdownTasks();
		}

	}

	/**
	 * Called when a terminal error occurs and development parameter is true
	 */
	private static function terminalActionDevelopment()
	{
		$data = self::extract(true);

		echo '<hr>';
		echo '<h1>PHP error terminated script</h1>';
		echo '<hr>';
		echo '<pre>';
		print_r($data['counts']);
		echo '</pre>';
		echo '<hr>';
		echo '<pre>';
		print_r($data['errors']);
		echo '</pre>';
	}

	/**
	 * Called when a terminal error occurs and development parameter is false
	 */
	private static function terminalActionProduction()
	{
		echo '<h1>Sorry, an error occurred</h1><hr><p>Details have been logged</p>';
	}

	/**
	 * Checks submitted error type contains valid errors
	 * @param $error_type string "background" or "ignore"
	 * @throws Exception if $error_type is not valid, or errors in submitted $error_type are not valid
	 */
	private static function checkErrorsAreValid($error_type)
	{
		if (! property_exists('Err', "errors_$error_type")) {
			throw new Exception('Invalid error type submitted');
		}

		if ((self::$allowed_errors & self::${"errors_$error_type"}) !== self::${"errors_$error_type"}) {
			throw new Exception("Invalid errors submitted for error type $error_type");
		}
	}

	/**
	 * Save any errors to relevant log file
	 */
	private static function logErrors()
	{
		if (self::$error_count_terminal > 0 || self::$error_count_background > 0) {
			$log_file = self::$log_directory . '/' . (self::$error_count_terminal > 0 ? self::$log_file_terminal : self::$log_file_background);
			$data['timestamp'] = self::$timestamp;
			if (self::$extra_log_data) {
				$data['data'] = self::$extra_log_data;
			}
			$data['log'] = self::$errors;
			file_put_contents($log_file, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
		}
	}

	/**
	 * Perform final tasks. Actions depends on the type of errors
	 * occurred and parameter values.
	 */
	private static function performShutdownTasks()
	{
		self::$shutdown_tasks_complete = true;

		if (self::$error_count_terminal === 0) {
			self::logErrors();
			return;
		}

		if (self::$development === true) {
			static::terminalActionDevelopment();
			self::logErrors();
		} else {
			self::logErrors();
			static::terminalActionProduction();
		}
	}

	/**
	 * Set class parameters using submitted array values
	 * @param $parameters
	 * @throws Exception if $parameters is not an array
	 */
	private static function setParametersWithArray($parameters)
	{
		if (!is_array($parameters)) {
			throw new Exception('Parameters must be an array');
		}

		foreach ($parameters as $name => $value) {
			if (in_array($name, [
				'development',
				'errors_background',
				'errors_ignore',
				'log_directory',
				'log_file_background',
				'log_file_terminal',
				'timestamp'
			])) {
				self::${$name} = $value;
			}
		}
	}
}
