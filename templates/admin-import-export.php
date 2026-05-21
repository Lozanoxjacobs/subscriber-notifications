<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Import/Export Subscribers', 'subscriber-notifications'); ?></h1>
    
    <div class="subscriber-notifications-import-export">
        
        <!-- Import Section -->
        <div class="import-section">
            <h2><?php _e('Import Subscribers', 'subscriber-notifications'); ?></h2>
            
            <div class="import-instructions">
                <h3><?php _e('CSV Format Requirements', 'subscriber-notifications'); ?></h3>
                
                <div class="format-requirements">
                    <div class="required-columns">
                        <h4><?php _e('Required Columns:', 'subscriber-notifications'); ?></h4>
                        <ul>
                            <li><strong>name</strong> - <?php _e('Subscriber\'s full name', 'subscriber-notifications'); ?></li>
                            <li><strong>email</strong> - <?php _e('Valid email address (must be unique)', 'subscriber-notifications'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="optional-columns">
                        <h4><?php _e('Optional Columns:', 'subscriber-notifications'); ?></h4>
                        <ul>
                            <li><strong>frequency</strong> - <?php _e('daily, weekly, or monthly (defaults to weekly)', 'subscriber-notifications'); ?></li>
                            <?php foreach ($reference_lists as $ref) : ?>
                                <li>
                                    <strong><?php echo esc_html($ref['post_type'] . ':' . $ref['taxonomy']); ?></strong> -
                                    <?php
                                    /* translators: 1: post type label, 2: taxonomy label */
                                    printf(esc_html__('Comma-separated term names from %1$s / %2$s', 'subscriber-notifications'), esc_html($ref['post_type_label']), esc_html($ref['taxonomy_label']));
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="description"><?php _e('Exports include user_id (WordPress user ID when the subscriber signed up while logged in). Imports ignore user_id; account linking is set only via the public subscription form for logged-in users.', 'subscriber-notifications'); ?></p>
                    </div>
                </div>

                <div class="sample-csv">
                    <h4><?php _e('Sample CSV Format:', 'subscriber-notifications'); ?></h4>
                    <pre><?php
                        $header_cols = array('name', 'email', 'frequency');
                        foreach ($reference_lists as $ref) {
                            $header_cols[] = $ref['post_type'] . ':' . $ref['taxonomy'];
                        }
                        echo esc_html(implode(',', $header_cols)) . "\n";
                        echo esc_html('John Doe,john@example.com,weekly' . str_repeat(',""', max(0, count($reference_lists)))) . "\n";
                    ?></pre>
                    <p class="description"><?php _e('Note: Column headers use the post_type:taxonomy format to avoid collisions when the same taxonomy slug is reused across post types.', 'subscriber-notifications'); ?></p>
                </div>

                <div class="category-reference">
                    <h4><?php _e('Available Terms by Post Type / Taxonomy', 'subscriber-notifications'); ?></h4>
                    <?php if (empty($reference_lists)) : ?>
                        <p><?php esc_html_e('No content types are configured yet. Visit Content Types to enable post types and taxonomies.', 'subscriber-notifications'); ?></p>
                    <?php else : ?>
                        <?php if (SubscriberNotifications_Term_Resolver::should_hide_empty_terms_for_public()) : ?>
                            <p class="description sn-term-reference-intro"><?php esc_html_e('All configured terms are listed for CSV import. Terms marked below with “hidden from subscription form” have no published posts for that post type and do not appear on the public subscribe form while Hide Empty Terms is enabled in Settings.', 'subscriber-notifications'); ?></p>
                        <?php endif; ?>
                        <?php foreach ($reference_lists as $ref) : ?>
                            <div class="reference-block">
                                <h5><?php echo esc_html($ref['post_type_label'] . ' — ' . $ref['taxonomy_label']); ?> <code><?php echo esc_html($ref['post_type'] . ':' . $ref['taxonomy']); ?></code></h5>
                                <ul>
                                    <?php foreach ($ref['terms'] as $term_row) :
                                        $term = $term_row['term'];
                                        ?>
                                        <li>
                                            <?php echo esc_html($term->name); ?>
                                            <?php if (!empty($term_row['hidden_from_public_form'])) : ?>
                                                <span class="sn-term-reference-note"><?php esc_html_e('(0 posts — hidden from subscription form)', 'subscriber-notifications'); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="import-form">
                <h3><?php _e('Upload CSV File', 'subscriber-notifications'); ?></h3>
                <form method="post" enctype="multipart/form-data" id="csv-import-form">
                    <?php wp_nonce_field('subscriber_notifications_import', 'import_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv-file"><?php _e('CSV File', 'subscriber-notifications'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="csv-file" name="csv_file" accept=".csv" required>
                                <p class="description"><?php _e('Select a CSV file to import subscribers.', 'subscriber-notifications'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="import_csv" class="button button-primary" value="<?php _e('Import Subscribers', 'subscriber-notifications'); ?>">
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Export Section -->
        <div class="export-section">
            <h2><?php _e('Export Subscribers', 'subscriber-notifications'); ?></h2>
            
            <div class="export-instructions">
                <p><?php _e('Export all active subscribers to a CSV file. The exported file will include all subscriber information and can be used as a backup or for external processing.', 'subscriber-notifications'); ?></p>
                <p><strong><?php _e('Tip:', 'subscriber-notifications'); ?></strong> <?php _e('You can use the exported CSV file as a template for importing new subscribers. Just remove the rows you don\'t want to import and add new ones.', 'subscriber-notifications'); ?></p>
            </div>
            
            <div class="export-form">
                <button type="button" class="button button-primary export-csv">
                    <?php _e('Export CSV', 'subscriber-notifications'); ?>
                </button>
                <p class="description"><?php _e('Downloads a CSV file with all active subscribers.', 'subscriber-notifications'); ?></p>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="help-section">
            <h2><?php _e('Troubleshooting', 'subscriber-notifications'); ?></h2>
            
            <div class="help-content">
                <h3><?php _e('Common Import Issues:', 'subscriber-notifications'); ?></h3>
                <ul>
                    <li><strong><?php _e('Missing required columns:', 'subscriber-notifications'); ?></strong> <?php _e('Make sure your CSV has "name" and "email" columns.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('Invalid email addresses:', 'subscriber-notifications'); ?></strong> <?php _e('Check that all email addresses are valid and properly formatted.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('Duplicate emails:', 'subscriber-notifications'); ?></strong> <?php _e('Each email address must be unique. Duplicates will be skipped.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('Term names not found:', 'subscriber-notifications'); ?></strong> <?php _e('Make sure term names match exactly with those listed above.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('No terms selected for a row:', 'subscriber-notifications'); ?></strong> <?php _e('Each subscriber must have at least one term across all configured taxonomies.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('Invalid frequency:', 'subscriber-notifications'); ?></strong> <?php _e('Frequency must be "daily", "weekly", or "monthly".', 'subscriber-notifications'); ?></li>
                </ul>

                <h3><?php _e('Tips:', 'subscriber-notifications'); ?></h3>
                <ul>
                    <li><?php _e('Use the sample format above as a template for your CSV file.', 'subscriber-notifications'); ?></li>
                    <li><?php _e('Term names are case-sensitive and must match exactly.', 'subscriber-notifications'); ?></li>
                    <li><?php _e('Empty rows will be automatically skipped.', 'subscriber-notifications'); ?></li>
                    <li><?php _e('All imported subscribers will be set to "active" status.', 'subscriber-notifications'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.subscriber-notifications-import-export {
    max-width: 1200px;
}

.subscriber-notifications-import-export .import-section,
.subscriber-notifications-import-export .export-section,
.subscriber-notifications-import-export .help-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.subscriber-notifications-import-export .import-instructions {
    margin-bottom: 30px;
}

.subscriber-notifications-import-export .format-requirements {
    display: flex;
    gap: 30px;
    margin: 20px 0;
}

.subscriber-notifications-import-export .required-columns,
.subscriber-notifications-import-export .optional-columns {
    flex: 1;
}

.subscriber-notifications-import-export .required-columns h4 {
    color: #d63638;
}

.subscriber-notifications-import-export .optional-columns h4 {
    color: #0073aa;
}

.subscriber-notifications-import-export .sample-csv {
    background: #f0f0f1;
    padding: 15px;
    border-radius: 4px;
    margin: 20px 0;
}

.subscriber-notifications-import-export .sample-csv pre {
    margin: 0;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

.subscriber-notifications-import-export .category-reference {
    margin: 20px 0;
}

.subscriber-notifications-import-export .category-reference .reference-block {
    margin-bottom: 20px;
}

.subscriber-notifications-import-export .category-reference ul {
    list-style: disc;
    margin-left: 20px;
}

.subscriber-notifications-import-export .sn-term-reference-intro {
    max-width: 72em;
}

.subscriber-notifications-import-export .sn-term-reference-note {
    color: #646970;
    font-size: 12px;
    font-style: italic;
}

.subscriber-notifications-import-export .export-form {
    text-align: center;
    padding: 20px;
}

.subscriber-notifications-import-export .help-content ul {
    list-style: disc;
    margin-left: 20px;
}

.subscriber-notifications-import-export .help-content li {
    margin-bottom: 8px;
}
</style>
