<?php
if (!defined('ABSPATH')) exit; // Prevent direct access

// ✅ Hook into WPForms Submission
add_action("wpforms_process_complete", function ($fields, $entry, $form_data, $entry_id) {
      global $wpdb;
      $table_name = $wpdb->prefix . 'wpforms_custom_entries';

      // ✅ Ensure table exists
      $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
      if (!$table_exists) {
            error_log("❌ Table does not exist: $table_name");
            return;
      }

      // ✅ Extract dynamic form data
      $entry_data = [];
      foreach ($fields as $field) {
            $entry_data[$field['name']] = sanitize_text_field($field['value']);
      }

      // ✅ Insert dynamic data into the database
      $result = $wpdb->insert(
            $table_name,
            [
                  'form_id' => $form_data['id'],
                  'entry_data' => maybe_serialize($entry_data),
                  'submitted_at' => current_time('mysql')
            ]
      );

      // ✅ Debugging logs
      if ($result === false) {
            error_log("❌ Database Insert Error: " . $wpdb->last_error);
      } else {
            error_log("✅ Form ID: {$form_data['id']} Data inserted successfully.");
      }
}, 10, 4);
