<?php

namespace Mediavine\MCP\Migrations;

use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;

/**
 * Perform updates and migration of data/schema for version 2.10.3
 */
class Migrate_2_10_3 extends Migrate {

	/**
	 * Performs the tasks required to move to MCP 2.10.3.
	 */
	public function run_migration() {
		// Set default value for all publishers who have already launched.
		if ( ! Upstream::is_launch_mode_enabled() ) {
			Option::get_instance()->update_option( 'seen_launch_success_message', true );
		}
	}

}
