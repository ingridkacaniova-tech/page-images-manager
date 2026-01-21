<?php
/**
 * Debug Logger with Visual Tree Structure
 * 
 * Usage:
 * PIM_Debug_Logger::enter('function_name', ['param1' => 123]);
 * PIM_Debug_Logger::log('Uploading file...');
 * PIM_Debug_Logger::exit('reload_image_file', ['result' => 'success']);
 */
class PIM_Debug_Logger {
	private static $depth = 0;
	private static $function_stack = array();
	private static $start_times = array();

	/**
	 * ✅ Log session start with unique ID
	 */
	public static function log_session_start($handler_name = 'unknown') {
		$session_id = uniqid('sess_', true);
		$timestamp = microtime(true);
		$ms = sprintf("%03d", ($timestamp - floor($timestamp)) * 1000);
		$time_str = date('H:i:s', $timestamp) . '.' . $ms;
		
		error_log("\n🔗🔗🔗 SESSION START: {$session_id} [{$time_str}]");
		error_log("📌 Handler: {$handler_name}");
		
		return $session_id;
	}

	/**
	 * Enter function - start tracking
	 */
	public static function enter($function_name, $inputs = array()) {
		$indent = str_repeat('  ', self::$depth);
		$timestamp = self::get_timestamp();
		
		error_log("\n{$indent}┌─ 🔵 ENTER: {$function_name} [{$timestamp}]");
		
		if (!empty($inputs)) {
			foreach ($inputs as $key => $value) {
				$formatted_value = self::format_value($value);
				error_log("{$indent}│  📥 {$key}: {$formatted_value}");
			}
		}
		
		self::$function_stack[] = $function_name;
		self::$start_times[$function_name] = microtime(true);
		self::$depth++;
	}

	/**
	 * Log inside function
	 */
	public static function log($message, $data = null) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  💬 {$message}");
		
		if ($data !== null) {
			$formatted = self::format_value($data);
			error_log("{$indent}│     └─ {$formatted}");
		}
	}

	/**
	 * Log success step
	 */
	public static function success($message, $data = null) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  ✅ {$message}");
		
		if ($data !== null) {
			$formatted = self::format_value($data);
			error_log("{$indent}│     └─ {$formatted}");
		}
	}

	/**
	 * Log error
	 */
	public static function error($message, $data = null) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  ❌ ERROR: {$message}");
		
		if ($data !== null) {
			$formatted = self::format_value($data);
			error_log("{$indent}│     └─ {$formatted}");
		}
	}

	/**
	 * Log warning
	 */
	public static function warning($message, $data = null) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  ⚠️ WARNING: {$message}");
		
		if ($data !== null) {
			$formatted = self::format_value($data);
			error_log("{$indent}│     └─ {$formatted}");
		}
	}

	/**
	 * Exit function - end tracking
	 */
	public static function exit_function($function_name, $outputs = array()) {
		self::$depth--;
		$indent = str_repeat('  ', self::$depth);
		
		// Calculate execution time
		$duration = 0;
		if (isset(self::$start_times[$function_name])) {
			$duration = round((microtime(true) - self::$start_times[$function_name]) * 1000, 2);
			unset(self::$start_times[$function_name]);
		}
		
		if (!empty($outputs)) {
			foreach ($outputs as $key => $value) {
				$formatted_value = self::format_value($value);
				error_log("{$indent}│  📤 {$key}: {$formatted_value}");
			}
		}
		
		error_log("{$indent}└─ 🟢 EXIT: {$function_name} [{$duration}ms]\n");
		array_pop(self::$function_stack);
	}

	/**
	 * Log file operation
	 */
	public static function file_op($operation, $file_path, $result = null) {
		$indent = str_repeat('  ', self::$depth);
		$basename = basename($file_path);
		$exists = file_exists($file_path) ? '✓ exists' : '✗ missing';
		
		error_log("{$indent}│  📁 {$operation}: {$basename}");
		error_log("{$indent}│     └─ Path: {$file_path}");
		error_log("{$indent}│     └─ Status: {$exists}");
		
		if ($result !== null) {
			$formatted = self::format_value($result);
			error_log("{$indent}│     └─ Result: {$formatted}");
		}
	}

	/**
	 * Log cache operation
	 */
	public static function cache_op($operation, $cache_key) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  🧹 Cache {$operation}: {$cache_key}");
	}

	/**
	 * Log metadata operation
	 */
	public static function metadata($attachment_id, $metadata) {
		$indent = str_repeat('  ', self::$depth);
		error_log("{$indent}│  📋 Metadata for #{$attachment_id}:");
		
		if (isset($metadata['width']) && isset($metadata['height'])) {
			error_log("{$indent}│     └─ Dimensions: {$metadata['width']}×{$metadata['height']}");
		}
		
		if (isset($metadata['file'])) {
			error_log("{$indent}│     └─ File: " . basename($metadata['file']));
		}
		
		if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
			error_log("{$indent}│     └─ Sizes: " . implode(', ', array_keys($metadata['sizes'])));
		}
	}

	/**
	 * Get current function stack
	 */
	public static function get_stack() {
		return implode(' → ', self::$function_stack);
	}

	/**
	 * Format value for logging
	 */
	private static function format_value($value) {
		if (is_array($value)) {
			if (empty($value)) {
				return '[]';
			}
			return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		
		if (is_bool($value)) {
			return $value ? 'TRUE' : 'FALSE';
		}
		
		if (is_null($value)) {
			return 'NULL';
		}
		
		if (is_object($value)) {
			if ($value instanceof WP_Error) {
				return 'WP_Error: ' . $value->get_error_message();
			}
			return get_class($value);
		}
		
		return (string)$value;
	}

	/**
	 * Get timestamp
	 */
	private static function get_timestamp() {
		return date('H:i:s.') . substr(microtime(), 2, 3);
	}

	/**
	 * Reset logger (for testing)
	 */
	public static function reset() {
		self::$depth = 0;
		self::$function_stack = array();
		self::$start_times = array();
	}
}
