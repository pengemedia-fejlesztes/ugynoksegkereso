<?php
// Kompasz beállítások: kiemelt ügynökség, kezdőlapi lista, elérhetőség
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('Kompasz', 'Kompasz', 'manage_options', 'kompasz', 'kompasz_settings_page', 'dashicons-location-alt', 4);
    add_submenu_page('kompasz', 'Kompasz beállítások', 'Beállítások', 'manage_options', 'kompasz', 'kompasz_settings_page');
});

add_action('admin_init', function () {
    register_setting('kompasz', 'kompasz_featured', ['type' => 'integer', 'sanitize_callback' => 'absint']);
    register_setting('kompasz', 'kompasz_featured_pitch', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    register_setting('kompasz', 'kompasz_featured_cats', ['type' => 'array', 'sanitize_callback' => function ($v) { return array_map('absint', (array) $v); }]);
    register_setting('kompasz', 'kompasz_home_top', ['type' => 'array', 'sanitize_callback' => function ($v) { return array_values(array_filter(array_map('absint', (array) $v))); }]);
    register_setting('kompasz', 'kompasz_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email']);
});

function kompasz_settings_page() {
    $agencies = get_posts(['post_type' => 'ugynokseg', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish']);
    $opt = function ($sel) use ($agencies) {
        $o = '<option value="">–</option>';
        foreach ($agencies as $p) $o .= sprintf('<option value="%d" %s>%s</option>', $p->ID, selected((int) $sel, $p->ID, false), esc_html($p->post_title));
        return $o;
    };
    $featured_cats = array_map('intval', (array) get_option('kompasz_featured_cats', []));
    $top = (array) get_option('kompasz_home_top', []);
    ?>
    <div class="wrap">
        <h1>Kompasz beállítások</h1>
        <form method="post" action="options.php">
            <?php settings_fields('kompasz'); ?>
            <h2>Kiemelt ügynökség (fizetett megjelenés)</h2>
            <p class="description">A kiválasztott toplistákon a 3. hely után jelenik meg „Kiemelt ügynökség” jelöléssel, a lista tetején „Hirdetés” felirattal. A sorrendet nem változtatja meg.</p>
            <table class="form-table">
                <tr><th><label for="kompasz_featured">Ügynökség</label></th><td><select id="kompasz_featured" name="kompasz_featured"><?php echo $opt(get_option('kompasz_featured')); ?></select></td></tr>
                <tr><th><label for="kompasz_featured_pitch">Rövid szöveg</label></th><td><input class="large-text" id="kompasz_featured_pitch" name="kompasz_featured_pitch" value="<?php echo esc_attr(get_option('kompasz_featured_pitch')); ?>"></td></tr>
                <tr><th>Toplisták</th><td>
                    <?php foreach (kompasz_categories() as $c) printf('<label style="display:block;margin:2px 0"><input type="checkbox" name="kompasz_featured_cats[]" value="%d" %s> %s</label>', $c->term_id, checked(in_array($c->term_id, $featured_cats, true), true, false), esc_html($c->name)); ?>
                </td></tr>
            </table>
            <h2>Kezdőlap: Legjobbra értékeltek</h2>
            <p class="description">Az „Online marketing” fülön ez az 5 ügynökség jelenik meg ebben a sorrendben. A többi fülön az adott toplista első 5 helyezettje.</p>
            <table class="form-table">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr><th><?php echo $i + 1; ?>.</th><td><select name="kompasz_home_top[]"><?php echo $opt($top[$i] ?? ''); ?></select></td></tr>
                <?php endfor; ?>
            </table>
            <h2>Elérhetőség</h2>
            <table class="form-table">
                <tr><th><label for="kompasz_email">E-mail cím a láblécben</label></th><td><input class="regular-text" id="kompasz_email" name="kompasz_email" value="<?php echo esc_attr(get_option('kompasz_email')); ?>"></td></tr>
            </table>
            <h2>Keresőmotorok</h2>
            <p>Állapot: <strong><?php echo get_option('blog_public') ? 'az oldal indexelhető' : 'a keresőrobotok ki vannak tiltva (noindex, robots.txt)'; ?></strong>.
                Átállítás: <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>">Beállítások → Olvasás → Keresőmotorok láthatósága</a>.</p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Ha a keresőrobotok ki vannak tiltva, HTTP fejlécben is jelezzük
add_action('send_headers', function () {
    if (!get_option('blog_public')) header('X-Robots-Tag: noindex, nofollow, noarchive', true);
});
