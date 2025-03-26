<?php
if (!defined('ABSPATH')) exit; // Prevent direct access

function wpce_backup_and_migrate()
{
      global $wpdb;
      $old_table = $wpdb->prefix . 'wpforms_custom_entries';
      $backup_file = WP_CONTENT_DIR . '/uploads/wpforms_backup.json';

      // ✅ Backup old data
      $entries = $wpdb->get_results("SELECT * FROM `$old_table`");
      if (!empty($entries)) {
            file_put_contents($backup_file, json_encode($entries));
      }

      // ✅ Migrate data to new table
      $new_table = $wpdb->prefix . 'wpforms_custom_entries';
      foreach ($entries as $entry) {
            $entry_data = [
                  'name' => $entry->name,
                  'email' => $entry->email,
                  'qualification' => $entry->qualification,
                  'city' => $entry->city,
                  'desired_program' => $entry->desired_program,
                  'desired_country' => $entry->desired_country,
                  'phone_number' => $entry->phone_number,
                  'ielts' => $entry->ielts
            ];

            $wpdb->insert(
                  $new_table,
                  [
                        'form_id' => 1, // Default value (adjust as needed)
                        'entry_data' => maybe_serialize($entry_data),
                        'submitted_at' => $entry->submitted_at
                  ]
            );
      }
}
register_activation_hook(__FILE__, 'wpce_backup_and_migrate');
