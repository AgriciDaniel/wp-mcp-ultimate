<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities;

/**
 * Centralized helpers for ability implementations.
 * Provides validation, caching, formatting, and response helpers.
 */
class Helpers {

    /**
     * Ensure WordPress admin includes are loaded.
     * Call this early to prevent issues in REST API context.
     */
    public static function load_admin_includes(): void {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        if (!class_exists('Plugin_Upgrader', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('wp_create_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
    }

    /**
     * Validate required parameters.
     *
     * @param array       $input    Input data.
     * @param array       $required Required parameter names.
     * @return array|null Error array if validation fails, null if valid.
     */
    public static function validate_required(array $input, array $required): ?array {
        $missing = [];
        foreach ($required as $param) {
            if (empty($input[$param])) {
                $missing[] = $param;
            }
        }
        if (!empty($missing)) {
            return [
                'success' => false,
                'message' => esc_html(sprintf(
                    __('Missing required parameter(s): %s', 'wp-mcp-ultimate'),
                    implode(', ', $missing)
                )),
            ];
        }
        return null;
    }

    /**
     * Get cached result or compute and cache.
     *
     * @param string   $cache_key  Cache key.
     * @param callable $callback   Function to call on cache miss.
     * @param string   $cache_group Cache group.
     * @param int      $expires    Expiration in seconds.
     * @return mixed Cached or computed result.
     */
    public static function get_cached(string $cache_key, callable $callback, string $cache_group = 'wp_mcp_ultimate', int $expires = 300) {
        $result = wp_cache_get($cache_key, $cache_group);
        if (false === $result) {
            $result = $callback();
            if (!is_wp_error($result)) {
                wp_cache_set($cache_key, $result, $cache_group, $expires);
            }
        }
        return $result;
    }

    /**
     * Format a WP_Post for ability output.
     *
     * @param \WP_Post $post  Post object.
     * @param array    $extra Extra fields to merge.
     * @return array Formatted post data.
     */
    public static function format_post(\WP_Post $post, array $extra = []): array {
        $data = [
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'slug'     => $post->post_name,
            'status'   => $post->post_status,
            'date'     => $post->post_date,
            'modified' => $post->post_modified,
            'link'     => get_permalink($post->ID),
        ];
        return array_merge($data, $extra);
    }

    /**
     * Format a WP_User for ability output.
     *
     * @param \WP_User $user  User object.
     * @param array    $extra Extra fields to merge.
     * @return array Formatted user data.
     */
    public static function format_user(\WP_User $user, array $extra = []): array {
        $data = [
            'id'           => $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
        ];
        return array_merge($data, $extra);
    }

    /**
     * Format a media attachment for ability output.
     *
     * @param \WP_Post $attachment Attachment post object.
     * @return array Formatted media data.
     */
    public static function format_media(\WP_Post $attachment): array {
        return [
            'id'        => $attachment->ID,
            'title'     => $attachment->post_title,
            'filename'  => basename(get_attached_file($attachment->ID)),
            'mime_type' => $attachment->post_mime_type,
            'url'       => wp_get_attachment_url($attachment->ID),
            'date'      => $attachment->post_date,
            'alt_text'  => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
        ];
    }

    /**
     * Check a user capability, returning error array if denied.
     *
     * @param string     $cap           Capability to check.
     * @param mixed      $args          Additional arguments for capability check.
     * @param string     $error_message Error message if denied.
     * @return array|null Error array if denied, null if allowed.
     */
    public static function check_capability(string $cap, $args = null, string $error_message = 'Permission denied'): ?array {
        if (!current_user_can($cap, $args)) {
            return [
                'success' => false,
                'message' => esc_html($error_message),
            ];
        }
        return null;
    }

    /**
     * Create a success response.
     *
     * @param string $message Success message.
     * @param array  $extra   Extra data to include.
     * @return array Response array.
     */
    public static function success(string $message, array $extra = []): array {
        return array_merge(
            ['success' => true, 'message' => esc_html($message)],
            $extra
        );
    }

    /**
     * Create an error response.
     *
     * @param string|\WP_Error $error  Error message or WP_Error.
     * @param string           $prefix Optional message prefix.
     * @return array Response array.
     */
    public static function error($error, string $prefix = ''): array {
        $message = $error instanceof \WP_Error
            ? $error->get_error_message()
            : $error;
        return [
            'success' => false,
            'message' => esc_html($prefix . $message),
        ];
    }

    /**
     * Parse and normalize pagination parameters.
     *
     * @param array $input            Input array.
     * @param int   $default_per_page Default per-page value.
     * @param int   $max_per_page     Maximum per-page value.
     * @return array{per_page: int, page: int}
     */
    public static function parse_pagination(array $input, int $default_per_page = 20, int $max_per_page = 100): array {
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : $default_per_page;
        $per_page = max(1, min($max_per_page, $per_page));
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $page = max(1, $page);
        return ['per_page' => $per_page, 'page' => $page];
    }

    /**
     * Get optimized WP_Query arguments with performance defaults.
     *
     * @param array $args       Base arguments (post_type, post_status, etc.).
     * @param array $pagination Pagination params (per_page, page).
     * @param array $options    Additional options (orderby, order, search, etc.).
     * @return array Full WP_Query arguments.
     */
    public static function get_optimized_query_args(array $args, array $pagination = [], array $options = []): array {
        $defaults = [
            'posts_per_page'         => $pagination['per_page'] ?? 20,
            'paged'                  => $pagination['page'] ?? 1,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ];
        return array_merge($defaults, $args, $options);
    }

    /**
     * Install a plugin from a local zip file.
     *
     * @param string $zip_path Path to the plugin zip file.
     * @param array  $input    Ability input for activate/overwrite flags.
     * @return array Result payload.
     */
    public static function install_plugin_zip(string $zip_path, array $input): array {
        if (empty($zip_path) || !file_exists($zip_path)) {
            return ['success' => false, 'message' => esc_html__('Plugin zip file not found', 'wp-mcp-ultimate')];
        }

        if (!function_exists('get_current_screen')) {
            function get_current_screen() { return null; }
        }

        \WP_Filesystem();
        global $wp_filesystem;

        $plugins_dir = WP_PLUGIN_DIR;
        $temp_dir    = $plugins_dir . '/mcp-temp-' . uniqid();

        $unzip_result = unzip_file($zip_path, $temp_dir);
        if (is_wp_error($unzip_result)) {
            if ($wp_filesystem) { $wp_filesystem->delete($temp_dir, true); }
            return ['success' => false, 'message' => esc_html__('Unzip failed: ', 'wp-mcp-ultimate') . esc_html($unzip_result->get_error_message())];
        }

        $files = $wp_filesystem ? $wp_filesystem->dirlist($temp_dir) : [];
        if (empty($files)) {
            if ($wp_filesystem) { $wp_filesystem->delete($temp_dir, true); }
            return ['success' => false, 'message' => esc_html__('Invalid plugin zip - no files found', 'wp-mcp-ultimate')];
        }

        $plugin_folder = '';
        foreach ($files as $file => $info) {
            if ('d' === $info['type']) {
                $plugin_folder = $file;
                break;
            }
        }

        if (empty($plugin_folder)) {
            $found_items = [];
            foreach ($files as $file => $info) {
                $found_items[] = $file . ' (type: ' . $info['type'] . ')';
            }
            if ($wp_filesystem) { $wp_filesystem->delete($temp_dir, true); }
            return [
                'success' => false,
                'message' => esc_html__('Invalid plugin zip - no plugin folder found. Found: ', 'wp-mcp-ultimate') . esc_html(implode(', ', $found_items)),
            ];
        }

        $target_dir  = $plugins_dir . '/' . $plugin_folder;
        $source_dir  = $temp_dir . '/' . $plugin_folder;
        $plugin_file = '';

        if (is_dir($target_dir)) {
            if (empty($input['overwrite']) && false === ($input['overwrite'] ?? null)) {
                if ($wp_filesystem) { $wp_filesystem->delete($temp_dir, true); }
                return ['success' => false, 'message' => esc_html__('Plugin already exists and overwrite is disabled', 'wp-mcp-ultimate')];
            }
            $all_plugins = get_plugins();
            foreach ($all_plugins as $file => $data) {
                if (strpos($file, $plugin_folder . '/') === 0) {
                    $plugin_file = $file;
                    if (is_plugin_active($file)) { deactivate_plugins($file); }
                    break;
                }
            }
            if ($wp_filesystem) { $wp_filesystem->delete($target_dir, true); }
        }

        $move_result = $wp_filesystem ? $wp_filesystem->move($source_dir, $target_dir) : false;
        if ($wp_filesystem) { $wp_filesystem->delete($temp_dir, true); }

        if (!$move_result) {
            return ['success' => false, 'message' => esc_html__('Failed to move plugin to plugins directory', 'wp-mcp-ultimate')];
        }

        if (empty($plugin_file)) {
            $all_plugins = get_plugins();
            foreach ($all_plugins as $file => $data) {
                if (strpos($file, $plugin_folder . '/') === 0) {
                    $plugin_file = $file;
                    break;
                }
            }
        }

        if (empty($plugin_file)) {
            return ['success' => false, 'message' => esc_html__('Plugin installed but main file not found', 'wp-mcp-ultimate')];
        }

        $activated = false;
        if (!empty($input['activate']) || !isset($input['activate'])) {
            $activate_result = activate_plugin($plugin_file);
            if (is_wp_error($activate_result)) {
                return [
                    'success'   => true,
                    'message'   => esc_html__('Plugin installed but activation failed: ', 'wp-mcp-ultimate') . esc_html($activate_result->get_error_message()),
                    'plugin'    => $plugin_file,
                    'activated' => false,
                ];
            }
            $activated = true;
        }

        return [
            'success'   => true,
            'message'   => $activated
                ? esc_html__('Plugin installed successfully and activated', 'wp-mcp-ultimate')
                : esc_html__('Plugin installed successfully', 'wp-mcp-ultimate'),
            'plugin'    => $plugin_file,
            'activated' => $activated,
        ];
    }
}
