<?php

namespace Mediavine\MCP\Migrations;

/**
 * Base class for all migrations with required functionality.
 *
 * Created to aid with dynamic migration versioning in the future.
 *
 * @codeCoverageIgnore
 */
abstract class Migrate {

	/**
	 * Performs the tasks required to move to this version of MCP.
	 */
	abstract public function run_migration();


}
