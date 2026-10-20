<?php
// Contact Form 7: ajánlatkérő űrlap az ügynökség adatlapján.
// Alapértelmezett űrlap: Kompasz → Beállítások (ha üres, a rendszer létrehoz egyet); ügynökségenként felülírható az adatlapon.
if (!defined('ABSPATH')) exit;

/** CF7 űrlapok: [id => cím], vagy null, ha a bővítmény nincs bekapcsolva */
function kompasz_cf7_forms() {
    if (!class_exists('WPCF7_ContactForm')) return null;
    $out = [];
    foreach (get_posts(['post_type' => 'wpcf7_contact_form', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $p) $out[$p->ID] = $p->post_title;
    return $out;
}

/** Alapértelmezett űrlap létrehozása (egyszer) */
add_action('admin_init', function () {
    if (!class_exists('WPCF7_ContactForm') || !current_user_can('manage_options')) return;
    $cur = (int) get_option('kompasz_cf7_form');
    if ($cur && get_post_type($cur) === 'wpcf7_contact_form') return;
    if (get_option('kompasz_cf7_created')) return; // szándékosan törölték: nem hozzuk létre újra
    $de = kompasz_lang() === 'de';
    $form = WPCF7_ContactForm::get_template(['title' => $de ? 'Kompass – Angebotsanfrage an die Agentur' : 'Kompasz – ajánlatkérés az ügynökségtől']);
    $L = $de
        ? ['Name', 'E-Mail', 'Telefon', 'Ihre Anfrage', 'Ich akzeptiere die Datenschutzerklärung.', 'Angebot anfragen', 'Angebotsanfrage', 'Agentur']
        : ['Név', 'E-mail', 'Telefon', 'Miben segíthet az ügynökség?', 'Elfogadom az adatkezelési tájékoztatót.', 'Ajánlatot kérek', 'ajánlatkérés', 'Ügynökség'];
    $privacy = kompasz_page_url('adatvedelem');
    $body = "<label> {$L[0]}\n    [text* your-name autocomplete:name] </label>\n\n<label> {$L[1]}\n    [email* your-email autocomplete:email] </label>\n\n<label> {$L[2]}\n    [tel your-phone autocomplete:tel] </label>\n\n<label> {$L[3]}\n    [textarea* your-message] </label>\n\n[acceptance adatkezeles] <a href=\"$privacy\" target=\"_blank\">{$L[4]}</a> [/acceptance]\n\n[submit \"{$L[5]}\"]";
    $mail = $form->prop('mail');
    $mail['subject'] = "[_site_title] {$L[6]}: [_post_title]";
    $mail['body'] = "{$L[7]}: [_post_title]\n[_post_url]\n\n{$L[0]}: [your-name]\n{$L[1]}: [your-email]\n{$L[2]}: [your-phone]\n\n[your-message]\n\n-- \n[_site_title] ([_site_url])";
    $mail['recipient'] = '[_site_admin_email]';
    $mail['additional_headers'] = 'Reply-To: [your-email]';
    $form->set_properties(['form' => $body, 'mail' => $mail]);
    $id = $form->save();
    if ($id) { update_option('kompasz_cf7_form', (int) $id); update_option('kompasz_cf7_created', 1); }
});

/** Az ügynökség adatlapján megjelenő űrlap azonosítója (0 = nincs) */
function kompasz_cf7_form_for($agency_id) {
    if (!class_exists('WPCF7_ContactForm')) return 0;
    $own = get_post_meta($agency_id, 'kompasz_cf7', true);
    if ($own === 'none') return 0;
    $id = (int) ($own ?: get_option('kompasz_cf7_form'));
    return $id && get_post_type($id) === 'wpcf7_contact_form' ? $id : 0;
}

function kompasz_cf7_html($agency_id) {
    $id = kompasz_cf7_form_for($agency_id);
    return $id ? do_shortcode('[contact-form-7 id="' . $id . '"]') : '';
}

// Előfizető ügynökségnél (e-mail funkció) az ajánlatkérés közvetlenül a cég e-mail címére megy, a webhely címe másolatot kap
add_action('wpcf7_before_send_mail', function ($form) {
    $sub = class_exists('WPCF7_Submission') ? WPCF7_Submission::get_instance() : null;
    $post = $sub ? (int) $sub->get_meta('container_post_id') : 0;
    if (!$post || get_post_type($post) !== 'ugynokseg') return;
    $email = get_post_meta($post, 'kompasz_email', true);
    if (!$email || !is_email($email) || !function_exists('kompasz_agency_has') || !kompasz_agency_has($post, 'email')) return;
    $mail = $form->prop('mail');
    $mail['recipient'] = $email;
    $mail['additional_headers'] = trim(($mail['additional_headers'] ?? '') . "\nBcc: " . get_option('admin_email'));
    $form->set_properties(['mail' => $mail]);
});
