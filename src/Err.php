<?php

/**
 * Class Err
 */
class Err {

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
	 * @var integer A bitwise derived integer made with PHP Error Constants controlling which error codes to log silently
	 */
	private static $errors_background = 0;

	/**
	 * @var integer A bitwise derived integer made with PHP Error Constants controlling which error codes to ignore
	 */
	private static $errors_ignore = 0;

	/**
	 * @var array Extra data to save with log
	 */
	private static $extra_log_data = [];

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
	 * @var bool If shutdown (log/output errors) has happened
	 */
	private static $shutdown_complete = false;

	/**
	 * @var false|string A string to echo when the script is terminated, otherwise the error log is dumped
	 */
	private static $terminal_message = false;

	/**
	 * @var string Timestamp to use in log file with logged errors
	 */
	private static $timestamp = '';

	/**
	 * Add extra details to save with error log. Log array will include an extra
	 * element called "data" which will contain the submitted details. Array must
	 * be 1 dimensional, values are typecast as strings.
	 * @param $data_array array Data to add to log
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

		if (self::$errors_ignore & $err_no) {
			self::$error_count_ignore++;
		} else if (self::$errors_background & $err_no) {
			self::$error_count_background++;
		} else {
			self::$error_count_terminal++;
			self::shutdownFinal();
		}
	}

	/**
	 * Extract any error data so far then reset
	 * @param bool $with_counts Return error counts as well?
	 * @return array
	 */
	public static function extract($with_counts = false)
	{
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
		self::$errors = [];
		self::$error_count_background = 0;
		self::$error_count_ignore = 0;
		self::$error_count_terminal = 0;
		return $data;
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
	 * @throws Exception if log files do not exist or cannot be written to
	 */
	public static function initialise($parameters = null)
	{
		if (is_array($parameters)) {
			self::setParametersWithArray($parameters);
		}

		// check for write permissions to log files
		if (!is_writable(self::$log_directory . '/' . self::$log_file_background) || !is_writable(self::$log_directory . '/' . self::$log_file_terminal)) {
			throw new Exception('Err class cannot write to log files or log files do not exist.');
		}

		// use defaults if parameters not set
		if (self::$timestamp === '') {
			self::$timestamp = time();
		}

		if (self::$errors_ignore === 0) {
			self::$errors_ignore = E_NOTICE | E_USER_NOTICE;
		}

		if (self::$errors_background === 0) {
			self::$errors_background = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED | E_USER_DEPRECATED;
		}

		// do not display errors
		error_reporting(0);

		// register error handling functions
		set_error_handler('Err::errorHandler');
		register_shutdown_function('Err::shutdownCheckForFatal');
		register_shutdown_function('Err::shutdownFinal');
	}

	public static function setErrorsBackground($errors)
	{
		self::$errors_background = $errors;
	}

	public static function setErrorsIgnore($errors)
	{
		self::$errors_ignore = $errors;
	}

	public static function setLogDirectory($path)
	{
		self::$log_directory = $path;
	}

	public static function setLogFileBackground($file_name)
	{
		self::$log_file_background = $file_name;
	}

	public static function setLogFileTerminal($file_name)
	{
		self::$log_file_terminal = $file_name;
	}

	public static function setTerminalMessage($terminal_message)
	{
		self::$terminal_message = $terminal_message;
	}

	/**
	 * Set class parameters using submitted array values
	 * @param $parameters
	 */
	public static function setParametersWithArray($parameters)
	{
		$can_set = [
			'errors_background',
			'errors_ignore',
			'log_directory',
			'log_file_background',
			'log_file_terminal',
			'terminal_message',
			'timestamp'
		];
		foreach ($parameters as $name => $value) {
			if (in_array($name, $can_set)) {
				self::${$name} = $value;
			}
		}
	}

	/**
	 * Registered as a shutdown function. Checks the last error and passes its
	 * details to be processed if needed.
	 */
	public static function shutdownCheckForFatal()
	{
		// The following are fatal errors which will not be processed by the function set in set_error_handler()
		// They will need to be manually passed to errorHandler()
		$core_fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

		// Get the last error
		$error = error_get_last();

		// If the last error has a match in $core_fatal, record that the script has terminated and pass details to be processed
		if ($error !== NULL && in_array($error['type'], $core_fatal, true)) {
			self::$error_count_terminal++;
			self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}

	/**
	 * Registered as shutdown function and called if a terminal error occurs.
	 * Ensures that shutdownProcedure() (log or dump) is performed once.
	 */
	public static function shutdownFinal()
	{
		if (self::$shutdown_complete === false) {
			self::$shutdown_complete = true;
			self::shutdownProcedure();
		}
	}

	/**
	 * Perform final procedure. Actions depends on the type of errors occurred
	 * and terminal message value.
	 */
	private static function shutdownProcedure()
	{
		if (self::$error_count_terminal > 0 && self::$terminal_message === false) {
			echo '<hr>';
			echo '<h1>Script Terminated by PHP Error</h1>';
			echo '<hr>';
			echo '<pre>';
			print_r(self::$errors);
			exit;
		}

		if (self::$error_count_terminal > 0 || self::$error_count_background > 0) {
			$log_file = self::$log_directory . '/' . (self::$error_count_terminal > 0 ? self::$log_file_terminal : self::$log_file_background);
			$data['timestamp'] = self::$timestamp;
			if (self::$extra_log_data) {
				$data['data'] = self::$extra_log_data;
			}
			$data['log'] = self::$errors;
			file_put_contents($log_file, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
		}

		if (self::$error_count_terminal > 0) {
			echo self::$terminal_message;
			exit;
		}
	}
}
