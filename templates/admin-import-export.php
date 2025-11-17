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
                            <li><strong>news_categories</strong> - <?php _e('News category names (comma-separated)', 'subscriber-notifications'); ?></li>
                            <li><strong>meeting_categories</strong> - <?php _e('Meeting category names (comma-separated)', 'subscriber-notifications'); ?></li>
                            <li><strong>frequency</strong> - <?php _e('daily, weekly, or monthly (defaults to weekly)', 'subscriber-notifications'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="sample-csv">
                    <h4><?php _e('Sample CSV Format:', 'subscriber-notifications'); ?></h4>
                    <pre>name,email,news_categories,meeting_categories,frequency
John Doe,john@example.com,"Announcements, News","City Council, Planning Commission",weekly
Jane Smith,jane@example.com,"News",,daily
Bob Johnson,bob@example.com,,"City Council",monthly</pre>
                    <p class="description"><?php _e('Note: You can export existing subscribers and use that file as a template for importing new ones.', 'subscriber-notifications'); ?></p>
                </div>
                
                <div class="category-reference">
                    <h4><?php _e('Available Categories:', 'subscriber-notifications'); ?></h4>
                    
                    <div class="news-categories-ref">
                        <h5><?php _e('News Categories:', 'subscriber-notifications'); ?></h5>
                        <?php if (!empty($news_categories)): ?>
                            <ul>
                                <?php foreach ($news_categories as $category): ?>
                                    <li><?php echo esc_html($category->name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><?php _e('No news categories found.', 'subscriber-notifications'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="meeting-categories-ref">
                        <h5><?php _e('Meeting Categories:', 'subscriber-notifications'); ?></h5>
                        <?php if (!empty($meeting_categories)): ?>
                            <ul>
                                <?php foreach ($meeting_categories as $category): ?>
                                    <li><?php echo esc_html($category->name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><?php _e('No meeting categories found.', 'subscriber-notifications'); ?></p>
                        <?php endif; ?>
                    </div>
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
                    <li><strong><?php _e('Category names not found:', 'subscriber-notifications'); ?></strong> <?php _e('Make sure category names match exactly with those listed above.', 'subscriber-notifications'); ?></li>
                    <li><strong><?php _e('Invalid frequency:', 'subscriber-notifications'); ?></strong> <?php _e('Frequency must be "daily", "weekly", or "monthly".', 'subscriber-notifications'); ?></li>
                </ul>
                
                <h3><?php _e('Tips:', 'subscriber-notifications'); ?></h3>
                <ul>
                    <li><?php _e('Use the sample format above as a template for your CSV file.', 'subscriber-notifications'); ?></li>
                    <li><?php _e('Category names are case-sensitive and must match exactly.', 'subscriber-notifications'); ?></li>
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
    display: flex;
    gap: 30px;
    margin: 20px 0;
}

.subscriber-notifications-import-export .news-categories-ref,
.subscriber-notifications-import-export .meeting-categories-ref {
    flex: 1;
}

.subscriber-notifications-import-export .category-reference ul {
    list-style: disc;
    margin-left: 20px;
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
