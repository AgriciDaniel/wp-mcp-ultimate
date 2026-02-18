<?php
/**
 * Abilities API polyfill for WordPress < 6.9.
 *
 * Provides the WP_Ability, WP_Ability_Category, WP_Abilities_Registry, and
 * WP_Ability_Categories_Registry classes along with their companion global
 * functions when running on WordPress versions that do not yet ship the
 * Abilities API natively (6.7 and 6.8).
 *
 * Every class and function is guarded with class_exists / function_exists so
 * that this file becomes a harmless no-op once the site is upgraded to
 * WordPress 6.9+.
 *
 * @package WpMcpUltimate\Compat
 * @since   1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit();

/*
|--------------------------------------------------------------------------
| WP_Ability
|--------------------------------------------------------------------------
|
| Represents a single ability that can be registered, discovered, and
| executed by AI agents or other consumers.
|
*/

if (!class_exists('WP_Ability')) {
    /**
     * Represents a single WordPress ability.
     *
     * An ability encapsulates a discrete piece of functionality that can be
     * discovered and invoked programmatically.  Each ability carries metadata
     * (label, description, category), JSON-schema definitions for its input
     * and output, a callable that performs the work, and an optional
     * permission callback.
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    class WP_Ability {

        /**
         * Unique machine-readable name for this ability.
         *
         * @var string
         */
        private string $name;

        /**
         * Human-readable label.
         *
         * @var string
         */
        private string $label;

        /**
         * Human-readable description of what the ability does.
         *
         * @var string
         */
        private string $description;

        /**
         * Category slug this ability belongs to.
         *
         * @var string
         */
        private string $category;

        /**
         * JSON Schema describing the expected input.
         *
         * @var array
         */
        private array $input_schema;

        /**
         * JSON Schema describing the output.
         *
         * @var array
         */
        private array $output_schema;

        /**
         * The callable that performs the ability's work.
         *
         * @var callable|null
         */
        private $callback;

        /**
         * Optional callable that checks whether the current user has
         * permission to execute this ability.  Receives the input array
         * and must return a boolean.  When null the ability is considered
         * public.
         *
         * @var callable|null
         */
        private $permission_callback;

        /**
         * Arbitrary metadata attached to this ability.
         *
         * @var array
         */
        private array $meta;

        /**
         * Constructor.
         *
         * @param string $name Unique ability name.
         * @param array  $args {
         *     Ability arguments.
         *
         *     @type string        $label               Human-readable label.
         *     @type string        $description          Description of the ability.
         *     @type string        $category             Category slug.
         *     @type array         $input_schema         JSON Schema for input.
         *     @type array         $output_schema        JSON Schema for output.
         *     @type callable|null $callback             The execution callback.
         *     @type callable|null $permission_callback  Permission check callback.
         *     @type array         $meta                 Arbitrary metadata.
         * }
         */
        public function __construct(string $name, array $args) {
            $this->name                = $name;
            $this->label               = (string) ($args['label'] ?? '');
            $this->description         = (string) ($args['description'] ?? '');
            $this->category            = (string) ($args['category'] ?? '');
            $this->input_schema        = (array) ($args['input_schema'] ?? []);
            $this->output_schema       = (array) ($args['output_schema'] ?? []);
            $this->callback            = $args['callback'] ?? null;
            $this->permission_callback = $args['permission_callback'] ?? null;
            $this->meta                = (array) ($args['meta'] ?? []);
        }

        /**
         * Get the ability name.
         *
         * @return string
         */
        public function get_name(): string {
            return $this->name;
        }

        /**
         * Get the human-readable label.
         *
         * @return string
         */
        public function get_label(): string {
            return $this->label;
        }

        /**
         * Get the description.
         *
         * @return string
         */
        public function get_description(): string {
            return $this->description;
        }

        /**
         * Get the category slug.
         *
         * @return string
         */
        public function get_category(): string {
            return $this->category;
        }

        /**
         * Get the input JSON Schema.
         *
         * @return array
         */
        public function get_input_schema(): array {
            return $this->input_schema;
        }

        /**
         * Get the output JSON Schema.
         *
         * @return array
         */
        public function get_output_schema(): array {
            return $this->output_schema;
        }

        /**
         * Get arbitrary metadata.
         *
         * @return array
         */
        public function get_meta(): array {
            return $this->meta;
        }

        /**
         * Execute the ability.
         *
         * @param array $input Input data conforming to the input schema.
         * @return mixed The callback's return value.
         */
        public function execute(array $input): mixed {
            if (!is_callable($this->callback)) {
                return null;
            }

            return call_user_func($this->callback, $input);
        }

        /**
         * Check whether the current user has permission to execute this ability.
         *
         * When no permission callback has been registered the ability is
         * treated as publicly accessible and this method returns true.
         *
         * @param array $input Input data (forwarded to the permission callback).
         * @return bool
         */
        public function check_permissions(array $input): bool {
            if (null === $this->permission_callback) {
                return true;
            }

            return (bool) call_user_func($this->permission_callback, $input);
        }
    }
}

/*
|--------------------------------------------------------------------------
| WP_Ability_Category
|--------------------------------------------------------------------------
|
| Represents a grouping category for abilities.
|
*/

if (!class_exists('WP_Ability_Category')) {
    /**
     * Represents a category used to group related abilities.
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    class WP_Ability_Category {

        /**
         * Unique slug identifier.
         *
         * @var string
         */
        private string $slug;

        /**
         * Human-readable label.
         *
         * @var string
         */
        private string $label;

        /**
         * Human-readable description.
         *
         * @var string
         */
        private string $description;

        /**
         * Constructor.
         *
         * @param string $slug Unique category slug.
         * @param array  $args {
         *     Category arguments.
         *
         *     @type string $label       Human-readable label.
         *     @type string $description Description of the category.
         * }
         */
        public function __construct(string $slug, array $args) {
            $this->slug        = $slug;
            $this->label       = (string) ($args['label'] ?? '');
            $this->description = (string) ($args['description'] ?? '');
        }

        /**
         * Get the category slug.
         *
         * @return string
         */
        public function get_slug(): string {
            return $this->slug;
        }

        /**
         * Get the human-readable label.
         *
         * @return string
         */
        public function get_label(): string {
            return $this->label;
        }

        /**
         * Get the description.
         *
         * @return string
         */
        public function get_description(): string {
            return $this->description;
        }
    }
}

/*
|--------------------------------------------------------------------------
| WP_Abilities_Registry
|--------------------------------------------------------------------------
|
| Singleton registry that stores all registered abilities.
|
*/

if (!class_exists('WP_Abilities_Registry')) {
    /**
     * Singleton registry for WordPress abilities.
     *
     * Provides a central store for all registered WP_Ability instances and
     * exposes methods to register, unregister, query, and enumerate them.
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    class WP_Abilities_Registry {

        /**
         * Singleton instance.
         *
         * @var self|null
         */
        private static ?self $instance = null;

        /**
         * Registered abilities keyed by name.
         *
         * @var array<string, WP_Ability>
         */
        private array $abilities = [];

        /**
         * Retrieve the singleton instance.
         *
         * @return self
         */
        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Register a new ability.
         *
         * @param string $name Unique ability name.
         * @param array  $args Ability arguments passed to WP_Ability.
         * @return WP_Ability The newly registered ability instance.
         */
        public function register(string $name, array $args): WP_Ability {
            $ability = new WP_Ability($name, $args);
            $this->abilities[$name] = $ability;

            return $ability;
        }

        /**
         * Unregister an ability by name.
         *
         * @param string $name The ability name to remove.
         * @return bool True if the ability existed and was removed, false otherwise.
         */
        public function unregister(string $name): bool {
            if (!isset($this->abilities[$name])) {
                return false;
            }

            unset($this->abilities[$name]);

            return true;
        }

        /**
         * Retrieve a single ability by name.
         *
         * @param string $name The ability name.
         * @return WP_Ability|null The ability instance or null if not found.
         */
        public function get(string $name): ?WP_Ability {
            return $this->abilities[$name] ?? null;
        }

        /**
         * Retrieve all registered abilities.
         *
         * @return array<string, WP_Ability>
         */
        public function get_all(): array {
            return $this->abilities;
        }

        /**
         * Check whether an ability is registered.
         *
         * @param string $name The ability name.
         * @return bool
         */
        public function has(string $name): bool {
            return isset($this->abilities[$name]);
        }
    }
}

/*
|--------------------------------------------------------------------------
| WP_Ability_Categories_Registry
|--------------------------------------------------------------------------
|
| Singleton registry that stores all registered ability categories.
|
*/

if (!class_exists('WP_Ability_Categories_Registry')) {
    /**
     * Singleton registry for ability categories.
     *
     * Provides a central store for all registered WP_Ability_Category
     * instances and exposes methods to register, unregister, query, and
     * enumerate them.
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    class WP_Ability_Categories_Registry {

        /**
         * Singleton instance.
         *
         * @var self|null
         */
        private static ?self $instance = null;

        /**
         * Registered categories keyed by slug.
         *
         * @var array<string, WP_Ability_Category>
         */
        private array $categories = [];

        /**
         * Retrieve the singleton instance.
         *
         * @return self
         */
        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Register a new ability category.
         *
         * @param string $slug Unique category slug.
         * @param array  $args Category arguments passed to WP_Ability_Category.
         * @return WP_Ability_Category The newly registered category instance.
         */
        public function register(string $slug, array $args): WP_Ability_Category {
            $category = new WP_Ability_Category($slug, $args);
            $this->categories[$slug] = $category;

            return $category;
        }

        /**
         * Unregister a category by slug.
         *
         * @param string $slug The category slug to remove.
         * @return bool True if the category existed and was removed, false otherwise.
         */
        public function unregister(string $slug): bool {
            if (!isset($this->categories[$slug])) {
                return false;
            }

            unset($this->categories[$slug]);

            return true;
        }

        /**
         * Retrieve a single category by slug.
         *
         * @param string $slug The category slug.
         * @return WP_Ability_Category|null The category instance or null if not found.
         */
        public function get(string $slug): ?WP_Ability_Category {
            return $this->categories[$slug] ?? null;
        }

        /**
         * Retrieve all registered categories.
         *
         * @return array<string, WP_Ability_Category>
         */
        public function get_all(): array {
            return $this->categories;
        }

        /**
         * Check whether a category is registered.
         *
         * @param string $slug The category slug.
         * @return bool
         */
        public function has(string $slug): bool {
            return isset($this->categories[$slug]);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Global Helper Functions
|--------------------------------------------------------------------------
|
| Convenience wrappers around the singleton registries.  Each function is
| individually guarded so that WordPress 6.9+ native implementations take
| precedence when available.
|
*/

if (!function_exists('wp_register_ability')) {
    /**
     * Register a new WordPress ability.
     *
     * Applies the {@see 'wp_register_ability_args'} filter to the arguments
     * before forwarding them to the abilities registry.
     *
     * @param string $name Unique ability name.
     * @param array  $args Ability arguments.
     * @return WP_Ability The registered ability instance.
     */
    function wp_register_ability(string $name, array $args): WP_Ability {
        /** This filter is documented in wp-includes/abilities.php (WordPress 6.9+) */
        $args = apply_filters('wp_register_ability_args', $args, $name);

        return WP_Abilities_Registry::get_instance()->register($name, $args);
    }
}

if (!function_exists('wp_unregister_ability')) {
    /**
     * Unregister an ability by name.
     *
     * @param string $name The ability name.
     * @return bool True on success, false if the ability was not registered.
     */
    function wp_unregister_ability(string $name): bool {
        return WP_Abilities_Registry::get_instance()->unregister($name);
    }
}

if (!function_exists('wp_get_ability')) {
    /**
     * Retrieve a registered ability by name.
     *
     * @param string $name The ability name.
     * @return WP_Ability|null The ability instance or null.
     */
    function wp_get_ability(string $name): ?WP_Ability {
        return WP_Abilities_Registry::get_instance()->get($name);
    }
}

if (!function_exists('wp_get_abilities')) {
    /**
     * Retrieve all registered abilities.
     *
     * @return array<string, WP_Ability>
     */
    function wp_get_abilities(): array {
        return WP_Abilities_Registry::get_instance()->get_all();
    }
}

if (!function_exists('wp_has_ability')) {
    /**
     * Check whether an ability is registered.
     *
     * @param string $name The ability name.
     * @return bool
     */
    function wp_has_ability(string $name): bool {
        return WP_Abilities_Registry::get_instance()->has($name);
    }
}

if (!function_exists('wp_register_ability_category')) {
    /**
     * Register a new ability category.
     *
     * @param string $slug Unique category slug.
     * @param array  $args Category arguments.
     * @return WP_Ability_Category The registered category instance.
     */
    function wp_register_ability_category(string $slug, array $args): WP_Ability_Category {
        return WP_Ability_Categories_Registry::get_instance()->register($slug, $args);
    }
}

if (!function_exists('wp_unregister_ability_category')) {
    /**
     * Unregister an ability category by slug.
     *
     * @param string $slug The category slug.
     * @return bool True on success, false if the category was not registered.
     */
    function wp_unregister_ability_category(string $slug): bool {
        return WP_Ability_Categories_Registry::get_instance()->unregister($slug);
    }
}

if (!function_exists('wp_has_ability_category')) {
    /**
     * Check whether an ability category is registered.
     *
     * @param string $slug The category slug.
     * @return bool
     */
    function wp_has_ability_category(string $slug): bool {
        return WP_Ability_Categories_Registry::get_instance()->has($slug);
    }
}

if (!function_exists('wp_get_ability_category')) {
    /**
     * Retrieve a registered ability category by slug.
     *
     * @param string $slug The category slug.
     * @return WP_Ability_Category|null The category instance or null.
     */
    function wp_get_ability_category(string $slug): ?WP_Ability_Category {
        return WP_Ability_Categories_Registry::get_instance()->get($slug);
    }
}

if (!function_exists('wp_get_ability_categories')) {
    /**
     * Retrieve all registered ability categories.
     *
     * @return array<string, WP_Ability_Category>
     */
    function wp_get_ability_categories(): array {
        return WP_Ability_Categories_Registry::get_instance()->get_all();
    }
}

/*
|--------------------------------------------------------------------------
| Init Action Hooks
|--------------------------------------------------------------------------
|
| Fire the Abilities API initialisation actions on WordPress `init` at
| priority 5, giving plugins and themes a chance to register their
| categories and abilities early.
|
*/

add_action('init', static function (): void {
    /**
     * Fires when the Abilities API is ready for category registration.
     *
     * Plugins and themes should hook here to register ability categories
     * via wp_register_ability_category().
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    do_action('wp_abilities_api_categories_init');

    /**
     * Fires when the Abilities API is ready for ability registration.
     *
     * Plugins and themes should hook here to register abilities via
     * wp_register_ability().
     *
     * @since 6.9.0 (WordPress core)
     * @since 1.0.0 (polyfill)
     */
    do_action('wp_abilities_api_init');
}, 5);
