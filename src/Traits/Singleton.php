<?php

namespace Mediavine\MCP\Traits;

/**
 * Provides a standard clean way of defining a singleton.
 *
 * @codeCoverageIgnore
 */
trait Singleton {
	/**
	 * Reference to singleton self.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Construct set as protected so that the class can't be instantiated manually.
	 */
	protected function __construct() {
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return $this
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevents cloning of this class.
	 */
	protected function __clone() {
	}

	/**
	 * Prevents sleep (serialization) of this class.
	 */
	public function __sleep() {
	}

	/**
	 * Prevents wakeup (deserialization) of this class.
	 */
	public function __wakeup() {
	}
}
