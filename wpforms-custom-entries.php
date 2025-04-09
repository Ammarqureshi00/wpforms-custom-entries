<?php

/**
 * Plugin Name: WPForms Custom Entries
 * Plugin URI: https://example.com/wpforms-custom-entries
 * Description: A custom plugin to store WPForms entries in a custom database table and display them in the WordPress admin panel.
 * Version: 1.0
 * Author: Ammar Qureshi
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: wpforms-custom-entries
 */

if (!defined('ABSPATH')) {
      exit; // Exit if accessed directly
}


// Define Constants
define('WPCE_DB_VERSION', '1.0');
define('WPCE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPCE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPCE_NONCE', 'wpforms_custom_entries_nonce');
define('WPCE_TABLE_NAME', $wpdb->prefix . 'wpforms_custom_entries');

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-core.php';

// Plugin Activation Hook

// Plugin Activation Hook
function wpce_plugin_activation()
{
      global $wpdb;
      $table_name = $wpdb->prefix . 'wpforms_custom_entries';
      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        form_id INT(11) NOT NULL, 
        entry_data LONGTEXT NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'active',  /* ✅ Ensures status column is created */
        PRIMARY KEY (id)
    ) $charset_collate;";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql);

      // Ensure 'status' column exists if updating plugin
      $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'status'");
      if (empty($columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status VARCHAR(50) DEFAULT 'active'");
      }
}
register_activation_hook(__FILE__, 'wpce_plugin_activation');



// Plugin Uninstall Hook
function wpce_plugin_uninstall()
{
      global $wpdb;
      $table_name = $wpdb->prefix . 'wpforms_custom_entries';

      // Delete the table when the plugin is uninstalled
      $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'wpce_plugin_uninstall');

// ✅ Handle Bulk Actions
add_action('admin_post_wpce_bulk_action', 'wpce_process_bulk_action');

function wpce_process_bulk_action()
{
      global $wpdb;

      // ✅ Verify nonce for security
      if (!isset($_POST['wpce_nonce']) || !wp_verify_nonce($_POST['wpce_nonce'], 'wpce_bulk_action')) {
            wp_die('Security check failed.');
      }

      // ✅ Check if entries are selected
      if (empty($_POST['selected_entries'])) {
            wp_redirect(admin_url('admin.php?page=wpforms-custom-entries&message=no_selection'));
            exit;
      }

      // ✅ Get selected action and entries
      $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
      $selected_entries = array_map('intval', $_POST['selected_entries']);
      $table_entries = $wpdb->prefix . 'wpforms_custom_entries';

      if ($action === 'trash') {
            // Move selected entries to trash
            $wpdb->query("UPDATE $table_entries SET status = 'trash' WHERE id IN (" . implode(',', $selected_entries) . ")");
            $message = 'trashed';
      } elseif ($action === 'restore') {
            // Restore selected entries from trash
            $wpdb->query("UPDATE $table_entries SET status = 'active' WHERE id IN (" . implode(',', $selected_entries) . ")");
            $message = 'restored';
      } elseif ($action === 'mark_read') {
            // Mark selected entries as read
            $wpdb->query("UPDATE $table_entries SET status = 'read' WHERE id IN (" . implode(',', $selected_entries) . ")");
            $message = 'marked_read';
      } elseif ($action === 'mark_unread') {
            // Mark selected entries as unread
            $wpdb->query("UPDATE $table_entries SET status = 'unread' WHERE id IN (" . implode(',', $selected_entries) . ")");
            $message = 'marked_unread';
      }

      // ✅ Redirect back to the admin page with success message
      wp_redirect(admin_url('admin.php?page=wpforms-custom-entries&message=' . $message));
      exit;
}
//   Hook to handle CSV download request`
function wpce_export_csv()
{
      if (!current_user_can('manage_options')) {
            error_log('Unauthorized Access attempt to export CSV');
            wp_die('Unauthorized Access');
      }
      error_log('CSV Export Function Triggered!');

      if (!isset($_GET['form_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpce_export_csv')) {
            wp_die('Invalid request');
      }

      global $wpdb;
      $form_id = intval($_GET['form_id']);
      $table_name = $wpdb->prefix . 'wpforms_custom_entries';

      $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE form_id = %d",
            $form_id
      ));

      if (empty($entries)) {
            error_log('No entries found to export.');
            wp_die('No entries found to export.');
      }

      // Convert JSON entry_data into an associative array
      $all_fields = [];

      foreach ($entries as $entry) {
            $entry_data = json_decode($entry->entry_data, true); // Use object property notation

            if (!is_array($entry_data)) {
                  $entry_data = maybe_unserialize($entry->entry_data); // If serialized, unserialize it
            }

            if (is_array($entry_data)) {
                  $all_fields = array_merge($all_fields, array_keys($entry_data));
            }
      }

      $all_fields = array_unique($all_fields); // Ensure unique field names

      // CSV Headers
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Disposition: attachment; filename=wpforms_entries.csv');

      $output = fopen('php://output', 'w');

      if ($output === false) {
            error_log('Failed to open output stream for CSV export.');
            wp_die('Failed to export entries.');
      }

      // Write column headers
      $headers = array_merge(['Entry ID', 'Form ID', 'Submitted At'], $all_fields);
      fputcsv($output, $headers);

      // Write each entry as a row
      foreach ($entries as $entry) {
            $entry_data = json_decode($entry->entry_data, true); // Fix here

            if (!is_array($entry_data)) {
                  $entry_data = maybe_unserialize($entry->entry_data);
            }

            $row = [
                  $entry->id,
                  $entry->form_id,
                  $entry->submitted_at
            ];

            // Fill the row with the correct column values
            foreach ($all_fields as $field) {
                  $row[] = isset($entry_data[$field]) ? $entry_data[$field] : ''; // Insert empty if field is missing
            }

            fputcsv($output, $row);
      }

      fclose($output);
      exit;
}
add_action('admin_post_wpce_export_csv', 'wpce_export_csv');
