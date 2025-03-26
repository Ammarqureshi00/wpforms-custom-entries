<?php
if (!defined('ABSPATH')) exit; // Prevent direct access

if (!defined('WPCE_PLUGIN_URL')) {
      define('WPCE_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// ✅ Enqueue admin styles & scripts
function wpce_enqueue_admin_assets()
{
      wp_enqueue_style('wpce-admin-styles', WPCE_PLUGIN_URL . 'assets/css/style.css', [], '1.0');
      wp_enqueue_script('wpce-admin-scripts', WPCE_PLUGIN_URL . 'assets/js/scripts.js', ['jquery'], '1.0', true);
}
add_action('admin_enqueue_scripts', 'wpce_enqueue_admin_assets');

// ✅ Function to show bulk action notices at the top of the page
add_action('admin_init', 'wpce_show_bulk_action_notice');
function wpce_show_bulk_action_notice($notice)
{
      if (empty($notice)) {
            return;
      }

      $messages = [
            'trashed' => '✅ Selected entries moved to trash successfully!',
            'restored' => '✅ Selected entries restored successfully!',
            'marked_read' => '✅ Selected entries marked as read!',
            'marked_unread' => '✅ Selected entries marked as unread!',
            'no_selection' => '⚠ Please select at least one entry before applying bulk actions!',
      ];

      if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
      }
}
// ✅ Register admin menu
add_action('admin_menu', function () {
      add_menu_page(
            'WPForms Entries',
            'WPForms Entries',
            'manage_options',
            'wpforms-custom-entries',
            'wpforms_custom_entries_display',
            'dashicons-list-view',
            25
      );
});
// ✅ Display Entries in Admin Panel
function wpforms_custom_entries_display()
{
      global $wpdb;
      $table_entries = $wpdb->prefix . 'wpforms_custom_entries';
      $table_forms = $wpdb->prefix . 'posts'; // ✅ Use wp_posts where WPForms stores form data

      // ✅ Get filter parameters
      $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
      $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
      $form_id_filter = isset($_GET['form_id']) ? intval($_GET['form_id']) : '';
      $date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
      $notice = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

      // ✅ Base Query (Now Includes Form Name)
      $query = "SELECT e.*, f.post_title AS form_name FROM $table_entries e 
          LEFT JOIN $table_forms f ON e.form_id = f.ID AND f.post_type = 'wpforms' 
          WHERE 1=1";

      if ($filter === 'trash') {
            $query .= " AND e.status = 'trash'";
      } else {
            $query .= " AND (e.status IS NULL OR e.status = 'active')";
      }

      // ✅ Apply other filters
      if (!empty($search_query)) {
            $query .= $wpdb->prepare(" AND e.entry_data LIKE %s", '%' . $wpdb->esc_like($search_query) . '%');
      }
      if (!empty($form_id_filter)) {
            $query .= $wpdb->prepare(" AND e.form_id = %d", $form_id_filter);
      }
      if (!empty($date_filter)) {
            $query .= $wpdb->prepare(" AND DATE(e.submitted_at) = %s", $date_filter);
      }

      $query .= " ORDER BY e.submitted_at DESC"; // ✅ Apply sorting

      // ✅ Fetch results
      $results = $wpdb->get_results($query);

      // ✅ Display Bulk Action Notices
      wpce_show_bulk_action_notice($notice);
?>
      <div class="wrap" style="padding: 0 15px;">
            <h1 class="wp-heading-inline">📋 WPForms Custom Entries</h1>
            <div class="action-form">
                  <form method="GET">
                        <input type="hidden" name="page" value="wpforms-custom-entries">
                        <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search entries...">
                        <select name="form_id">
                              <option value="" style="font-weight: bold; color: #056ab2;">Select Form</option>
                              <?php
                              $forms = $wpdb->get_results("SELECT ID, post_title FROM $table_forms WHERE post_type = 'wpforms'");
                              foreach ($forms as $form) {
                                    echo '<option value="' . esc_attr($form->ID) . '"' . selected($form_id_filter, $form->ID, false) . '>' . esc_html($form->post_title) . '</option>';
                              }
                              ?>
                        </select>
                        <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>">
                        <button type="submit" class="button">Filter</button>
                  </form>
                  <p>
                        <a href="<?php echo admin_url('admin.php?page=wpforms-custom-entries&filter=all'); ?>"
                              class="button <?php echo ($filter === 'all') ? 'button-primary' : ''; ?>">All</a>

                        <a href="<?php echo admin_url('admin.php?page=wpforms-custom-entries&filter=trash'); ?>"
                              class="button <?php echo ($filter === 'trash') ? 'button-primary' : ''; ?>">Trash</a>
                  </p>
                  <?php
                  // ✅ Get selected form ID from URL or default to 0
                  $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
                  $export_url = admin_url('admin-post.php?action=wpce_export_csv&form_id=' . $form_id . '&_wpnonce=' . wp_create_nonce('wpce_export_csv'));
                  ?>

                  <a href="#" id="wpce-export-btn" class="button" style="margin-bottom: 10px; background-color: #056ab2; border-color: #056ab2; color: #fff;">
                        <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span> Export as CSV
                  </a>
                  <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="bulk-action-form">
                        <?php wp_nonce_field('wpce_bulk_action', 'wpce_nonce'); ?>
                        <input type="hidden" name="action" value="wpce_bulk_action">

                        <select name="bulk_action" style="margin-bottom: 10px;">
                              <option value="">Bulk Actions</option>
                              <option value="trash">Move to Trash</option>
                              <option value="restore">Restore</option>
                              <option value="mark_read">Mark as Read</option>
                              <option value="mark_unread">Mark as Unread</option>
                        </select>
                        <button type="submit" class="button">Apply</button>

                        <table class="wp-list-table widefat fixed striped">
                              <thead>
                                    <tr>
                                          <th><input type="checkbox" id="select-all"></th>
                                          <th>Form Name</th>
                                          <th>Data</th>
                                          <th>Submitted At</th>
                                          <th>Status</th>
                                    </tr>
                              </thead>
                              <tbody>
                                    <?php if (!empty($results)): ?>
                                          <?php foreach ($results as $row): ?>
                                                <tr>
                                                      <td><input type="checkbox" name="selected_entries[]" value="<?php echo esc_attr($row->id); ?>"></td>
                                                      <td style="color:#056ab2
                                                        ; font-weight:bold; "> <?php
                                                                                    $form_name = !empty($row->form_name) ? esc_html($row->form_name) : 'Unknown Form';
                                                                                    echo $form_name . " (ID: " . esc_html($row->form_id) . ")";
                                                                                    ?></td>
                                                      <td>
                                                            <?php
                                                            $entry_data = maybe_unserialize($row->entry_data);
                                                            if (is_array($entry_data)) {
                                                                  foreach ($entry_data as $key => $value) {
                                                                        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                                                              echo '<strong>' . esc_html($key) . ':</strong> <a href="mailto:' . esc_html($value) . '">' . esc_html($value) . '</a><br>';
                                                                        } else {
                                                                              echo '<strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '<br>';
                                                                        }
                                                                  }
                                                            } else {
                                                                  echo esc_html($entry_data);
                                                            }
                                                            ?>
                                                      </td>
                                                      <td><?php echo esc_html($row->submitted_at); ?></td>
                                                      <td><?php echo esc_html($row->status); ?></td>
                                                </tr>
                                          <?php endforeach; ?>
                                    <?php else: ?>
                                          <tr>
                                                <td colspan="5">No records found!</td>
                                          </tr>
                                    <?php endif; ?>
                              </tbody>
                        </table>
                  </form>
            </div>
      </div>
      <!-- filter scripts -->
      <script>
            document.addEventListener("DOMContentLoaded", function() {
                  document.getElementById("select-all").addEventListener("change", function() {
                        document.querySelectorAll("input[name='selected_entries[]']").forEach(cb => cb.checked = this.checked);
                  });
            });
      </script>
      <!-- export scripts -->
      <script>
            document.addEventListener("DOMContentLoaded", function() {
                  let exportBtn = document.getElementById("wpce-export-btn");
                  let formSelect = document.querySelector("select[name='form_id']");

                  exportBtn.addEventListener("click", function(e) {
                        if (!formSelect.value || formSelect.value === "0") {
                              e.preventDefault();
                              alert("⚠ Please select a form before exporting!");
                              return;
                        }

                        let exportUrl = "<?php echo admin_url('admin-post.php?action=wpce_export_csv'); ?>" +
                              "&form_id=" + formSelect.value +
                              "&_wpnonce=" + "<?php echo wp_create_nonce('wpce_export_csv'); ?>";

                        exportBtn.setAttribute("href", exportUrl);
                  });
            });
      </script>
<?php
}
?>