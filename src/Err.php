<?php

/**
 * Class Err
 */
class Err {

	/**
	 * @var array Of arrays holding error data to be logged
	 */
	private static $error_list_log = [];

	/**
	 * @var array Of arrays holding ignored error data
	 */
	private static $error_list_ignore = [];

	/**
	 * @var integer A bitwise derived integer made with PHP Error Constants controlling which error codes to log silently
	 */
	private static $errors_background = null;

	/**
	 * @var integer A bitwise derived integer made with PHP Error Constants controlling which error codes to ignore
	 */
	private static $errors_ignore = null;

	/**
	 * @var string The path to log directory
	 */
	private static $log_directory = null;

	/**
	 * @var string The name of the file where background errors will be logged
	 */
	private static $log_file_background = 'background.txt';

	/**
	 * @var string The name of the file where terminating errors will be logged
	 */
	private static $log_file_terminate = 'terminate.txt';

	/**
	 * @var bool Whether or not the script terminated due do an error
	 */
	private static $script_terminated = false;

	/**
	 * @var bool|string A string to echo when the script is terminated, otherwise the error logged is dumped
	 */
	private static $termination_message = false;

	/**
	 * @var string The timestamp to record with logged errors
	 */
	private static $timestamp = null;


	/**
	 * Extract any logged error data so far and clear the data due to be logged
	 * @return array
	 */
	public static function extract()
	{
		$data = [
			'background' => self::$error_list_log,
			'ignore' => self::$error_list_ignore
		];

		self::$error_list_log = [];
		self::$error_list_ignore = [];

		return $data;
	}

	public static function initialise($parameters = null)
	{
		if (is_array($parameters)) {
			self::setParametersWithArray($parameters);
		}

		// check for write permissions to log files
		if (!is_writable(self::$log_directory . '/' . self::$log_file_background) || !is_writable(self::$log_directory . '/' . self::$log_file_terminate)) {
			throw new Exception('Err class cannot write to log files or log files do not exist.');
		}

		// use defaults if parameters not set
		if (self::$timestamp === null) {
			self::$timestamp = time();
		}
		if (self::$errors_ignore === null) {
			self::$errors_ignore = E_NOTICE | E_USER_NOTICE;
		}
		if (self::$errors_background === null) {
			self::$errors_background = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED | E_USER_DEPRECATED;
		}

		// do not display errors
		error_reporting(0);

		// register functions
		set_error_handler('Err::process');
		register_shutdown_function('Err::shutdownCheckForFatal');
		register_shutdown_function('Err::shutdownFinal');
	}

	public static function process($err_no, $err_str, $err_file, $err_line)
	{
		if (self::$errors_ignore & $err_no) {
			// this error should not be logged, the script can continue
			self::$error_list_ignore[] = [
				'error'     => $err_no,
				'message'   => $err_str,
				'file'      => $err_file,
				'line'      => $err_line,
				'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
			];
		} else {
			// this error should be logged...
			self::$error_list_log[] = [
				'error'     => $err_no,
				'message'   => $err_str,
				'file'      => $err_file,
				'line'      => $err_line,
				'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
			];
			if (!(self::$errors_background & $err_no)) {
				// ...the script should be terminated
				self::$script_terminated = true;
				self::shutdownFinal();
			}
		}
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

	public static function setLogFileTerminate($file_name)
	{
		self::$log_file_terminate = $file_name;
	}

	/**
	 * Set class parameters using submitted array values
	 * @param $parameters
	 */
	public static function setParametersWithArray($parameters)
	{
		$can_set = array_flip([
			'errors_to_ignore',
			'errors_to_log',
			'log_directory',
			'log_file_background',
			'log_file_terminate',
			'termination_message',
			'timestamp'
		]);

		foreach ($parameters as $name => $value) {
			if (array_key_exists($name, $can_set)) {
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
		// get the last error
		$error = error_get_last();

		// the following are fatal errors which will not be processed by the function set in set_error_handler()...
		$core_fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

		// ...so if the last error matches, record that the script has terminated and pass details to be processed
		if ($error !== NULL && in_array($error['type'], $core_fatal, true)) {
			self::$script_terminated = true;
			self::process($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}

	/**
	 * Registered as shutdown function. Performs final clean up, logging errors
	 * if needed and outputting any required data.
	 */
	public static function shutdownFinal()
	{
		if (self::$script_terminated && self::$termination_message === false) {
			echo '<hr>';
			echo '<h1>Script Terminated by PHP Error</h1>';
			echo '<hr>';
			echo '<pre>';
			print_r(self::extract());
			exit;
		}

		if (self::$error_list_log) {
			$log_file_path = self::$log_directory . '/' . (self::$script_terminated ? self::$log_file_terminate : self::$log_file_background);

			$data = [
				'timestamp' => self::$timestamp,
				'log' => self::extract()
			];

			file_put_contents($log_file_path, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
		}

		if (self::$script_terminated) {
			echo self::$termination_message;
			exit;
		}
	}
}
