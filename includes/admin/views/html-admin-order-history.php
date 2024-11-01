<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-shiptor-history">
    <?php if ($history && !empty($history) && is_array($history)) : ?>
        <ul class="history-list">
            <?php foreach ($history as $point) : ?>
                <li><?php printf('<p class="time">%s</p><div class="event"><span>%s</span> %s</div>', date_i18n('d.m.Y', strtotime($point['date'])), esc_html($point['event']), !empty($point['description']) ? sprintf('<span class="woocommerce-help-tip" data-tip="%s"></span>', esc_html($point['description'])) : '' ); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>