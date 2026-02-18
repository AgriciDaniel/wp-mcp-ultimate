# Abilities Reference

WP MCP Ultimate provides **58 abilities** across **9 domains**. All abilities are automatically exposed to MCP clients via the 3 meta-tools pattern (`discover-abilities`, `get-ability-info`, `execute-ability`).

## Summary

| Domain | Count |
|--------|-------|
| Content (Posts) | 6 |
| Content (Pages) | 6 |
| Content (Taxonomy) | 4 |
| Content (Search) | 1 |
| Content (Revisions) | 2 |
| Media | 5 |
| Users | 6 |
| Plugins | 6 |
| Menus | 7 |
| Widgets | 3 |
| Comments | 6 |
| Options | 3 |
| System | 3 |
| **Total** | **58** |

---

## Content - Posts

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-posts` | List Posts | `edit_posts` |
| `content/get-post` | Get Post | `edit_posts` |
| `content/create-post` | Create Post | `edit_posts` |
| `content/update-post` | Update Post | `edit_posts` |
| `content/delete-post` | Delete Post | `delete_posts` |
| `content/patch-post` | Patch Post Content | `edit_posts` |

## Content - Pages

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-pages` | List Pages | `edit_pages` |
| `content/get-page` | Get Page | `edit_pages` |
| `content/create-page` | Create Page | `edit_pages` |
| `content/update-page` | Update Page | `edit_pages` |
| `content/delete-page` | Delete Page | `delete_pages` |
| `content/patch-page` | Patch Page Content | `edit_pages` |

## Content - Taxonomy

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-categories` | List Categories | `manage_categories` |
| `content/create-category` | Create Category | `manage_categories` |
| `content/list-tags` | List Tags | `manage_categories` |
| `content/create-tag` | Create Tag | `manage_categories` |

## Content - Search

| Ability | Label | Capability |
|---------|-------|------------|
| `content/search` | Search Content | `edit_posts` |

## Content - Revisions

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-revisions` | List Revisions | `edit_posts` |
| `content/get-revision` | Get Revision | `edit_posts` |

## Media

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-media` | List Media | `upload_files` |
| `media/upload` | Upload Media | `upload_files` |
| `media/get` | Get Media Item | `upload_files` |
| `media/update` | Update Media Item | `upload_files` |
| `media/delete` | Delete Media Item | `delete_posts` |

## Users

| Ability | Label | Capability |
|---------|-------|------------|
| `content/list-users` | List Users | `list_users` |
| `users/list` | List Users (Extended) | `list_users` |
| `users/get` | Get User | `list_users` |
| `users/create` | Create User | `create_users` |
| `users/update` | Update User | `edit_users` |
| `users/delete` | Delete User | `delete_users` |

## Plugins

| Ability | Label | Capability |
|---------|-------|------------|
| `plugins/upload` | Upload Plugin | `install_plugins` |
| `plugins/upload-base64` | Upload Plugin (Base64 or Zip Path) | `install_plugins` |
| `plugins/list` | List Plugins | `activate_plugins` |
| `plugins/delete` | Delete Plugin | `delete_plugins` |
| `plugins/activate` | Activate Plugin | `activate_plugins` |
| `plugins/deactivate` | Deactivate Plugin | `activate_plugins` |

## Menus

| Ability | Label | Capability |
|---------|-------|------------|
| `menus/list` | List Menus | `edit_theme_options` |
| `menus/get-items` | Get Menu Items | `edit_theme_options` |
| `menus/create` | Create Menu | `edit_theme_options` |
| `menus/add-item` | Add Menu Item | `edit_theme_options` |
| `menus/update-item` | Update Menu Item | `edit_theme_options` |
| `menus/delete-item` | Delete Menu Item | `edit_theme_options` |
| `menus/assign-location` | Assign Menu to Location | `edit_theme_options` |

## Widgets

| Ability | Label | Capability |
|---------|-------|------------|
| `widgets/list-sidebars` | List Widget Sidebars | `edit_theme_options` |
| `widgets/get-sidebar` | Get Sidebar Widgets | `edit_theme_options` |
| `widgets/list-available` | List Available Widgets | `edit_theme_options` |

## Comments

| Ability | Label | Capability |
|---------|-------|------------|
| `comments/list` | List Comments | `moderate_comments` |
| `comments/get` | Get Comment | `moderate_comments` |
| `comments/update-status` | Update Comment Status | `moderate_comments` |
| `comments/reply` | Reply to Comment | `moderate_comments` |
| `comments/create` | Create Comment | `moderate_comments` |
| `comments/delete` | Delete Comment | `moderate_comments` |

## Options

| Ability | Label | Capability |
|---------|-------|------------|
| `options/get` | Get Option | `manage_options` |
| `options/update` | Update Option | `manage_options` |
| `options/list` | List Options | `manage_options` |

## System

| Ability | Label | Capability |
|---------|-------|------------|
| `system/get-transient` | Get Transient | `manage_options` |
| `system/debug-log` | Read Debug Log | `manage_options` |
| `system/toggle-debug` | Toggle Debug Mode | `manage_options` |
