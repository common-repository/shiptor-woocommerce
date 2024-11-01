<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<ul class="shiptor-checkpoint-list">
    <?php foreach ((array) $history as $checkpoint) : ?>
        <li class="shiptor-checkpoint">
            <div class="shiptor-date"><?php echo date_i18n('j M Y - H:i', strtotime($checkpoint['date'])); ?></div>
            <div class="shiptor-description"><?php echo esc_html($checkpoint['message']); ?></div>
            <div class="shiptor-name"><?php echo esc_textarea($checkpoint['details']); ?></div>
        </li>
    <?php endforeach; ?>
</ul>
