<?php
/**
 * Abilities Registry — orchestrator for all WP MCP Ultimate abilities.
 *
 * Registers two ability categories ('site' and 'user'), calls the
 * static register() method on each of the 13 ability files, and
 * applies a blanket filter to mark every ability as MCP-public.
 *
 * @package WpMcpUltimate\Abilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WpMcpUltimate\Abilities;

use WpMcpUltimate\Abilities\Content\Posts;
use WpMcpUltimate\Abilities\Content\Pages;
use WpMcpUltimate\Abilities\Content\Taxonomy;
use WpMcpUltimate\Abilities\Content\Search;
use WpMcpUltimate\Abilities\Content\Revisions;
use WpMcpUltimate\Abilities\Media\Media;
use WpMcpUltimate\Abilities\Users\Users;
use WpMcpUltimate\Abilities\Plugins\Plugins;
use WpMcpUltimate\Abilities\Menus\Menus;
use WpMcpUltimate\Abilities\Widgets\Widgets;
use WpMcpUltimate\Abilities\Comments\Comments;
use WpMcpUltimate\Abilities\Options\Options;
use WpMcpUltimate\Abilities\System\System;

/**
 * Registry that wires all ability categories and abilities into the
 * WordPress Abilities API (native 6.9+ or polyfill).
 *
 * @since 1.0.0
 */
final class Registry {

	/**
	 * Wire up the Abilities API hooks.
	 *
	 * Called once from Plugin::setup(). Registers callbacks on the two
	 * Abilities API init actions and adds the MCP exposure filter.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
		add_filter( 'wp_register_ability_args', array( self::class, 'expose_all_abilities' ), 10, 2 );
	}

	/**
	 * Register ability categories.
	 *
	 * Hooked to {@see 'wp_abilities_api_categories_init'}.
	 *
	 * @since 1.0.0
	 */
	public static function register_categories(): void {
		wp_register_ability_category(
			'site',
			array(
				'label'       => __( 'Site Management', 'wp-mcp-ultimate' ),
				'description' => __( 'Abilities for managing site content, media, plugins, menus, widgets, options, and system settings.', 'wp-mcp-ultimate' ),
			)
		);

		wp_register_ability_category(
			'user',
			array(
				'label'       => __( 'User Management', 'wp-mcp-ultimate' ),
				'description' => __( 'Abilities for managing WordPress users and their roles.', 'wp-mcp-ultimate' ),
			)
		);
	}

	/**
	 * Register all 57 abilities from the 13 ability files.
	 *
	 * Hooked to {@see 'wp_abilities_api_init'}.
	 *
	 * @since 1.0.0
	 */
	public static function register_abilities(): void {
		// Content abilities.
		Posts::register();
		Pages::register();
		Taxonomy::register();
		Search::register();
		Revisions::register();

		// Media abilities.
		Media::register();

		// User abilities.
		Users::register();

		// Plugin abilities.
		Plugins::register();

		// Menu abilities.
		Menus::register();

		// Widget abilities.
		Widgets::register();

		// Comment abilities.
		Comments::register();

		// Option abilities.
		Options::register();

		// System abilities.
		System::register();
	}

	/**
	 * Mark every registered ability as MCP-public.
	 *
	 * Hooked to {@see 'wp_register_ability_args'} so the filter runs
	 * before each ability is stored in the registry.  Sets
	 * `meta.mcp.public = true` and `meta.mcp.type = 'tool'` (unless
	 * a type was already specified).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $args         The ability registration arguments.
	 * @param string $ability_name The ability name being registered.
	 * @return array Modified arguments with MCP metadata.
	 */
	public static function expose_all_abilities( array $args, string $ability_name ): array {
		if ( ! isset( $args['meta'] ) ) {
			$args['meta'] = array();
		}
		if ( ! isset( $args['meta']['mcp'] ) ) {
			$args['meta']['mcp'] = array();
		}

		$args['meta']['mcp']['public'] = true;

		if ( ! isset( $args['meta']['mcp']['type'] ) ) {
			$args['meta']['mcp']['type'] = 'tool';
		}

		return $args;
	}
}
