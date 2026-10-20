<?php
// Képek automatikusan WebP formátumban: a feltöltött JPG/PNG eredetit és a generált méreteket is WebP-re alakítja
if (!defined('ABSPATH')) exit;

const KOMPASZ_WEBP_QUALITY = 82;

function kompasz_webp_supported() {
    return wp_image_editor_supports(['mime_type' => 'image/webp']);
}

// A WordPress által generált képméretek WebP-ben készülnek
add_filter('image_editor_output_format', function ($formats, $filename = '', $mime = '') {
    if (!kompasz_webp_supported()) return $formats;
    $formats['image/jpeg'] = 'image/webp';
    // Palettás PNG-t a GD nem tud WebP-be menteni: annak méretei PNG-k maradnak
    if (!kompasz_is_palette_png($filename)) $formats['image/png'] = 'image/webp';
    return $formats;
}, 10, 3);

function kompasz_is_palette_png($file) {
    return $file && !class_exists('Imagick') && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'png'
        && ord((string) file_get_contents($file, false, null, 25, 1)) === 3;
}

/** Egy JPG/PNG fájl WebP-re alakítása; siker esetén az új útvonal, különben null */
function kompasz_to_webp($file) {
    if (!kompasz_webp_supported() || !is_file($file)) return null;
    $type = wp_check_filetype($file)['type'] ?? '';
    if (!in_array($type, ['image/jpeg', 'image/png'], true)) return null;
    $target = wp_unique_filename(dirname($file), pathinfo($file, PATHINFO_FILENAME) . '.webp');
    // Palettás PNG: a GD imagewebp() nem kezeli (figyelmeztetés + üres kép) – előbb truecolor képpé alakítjuk, átlátszósággal
    if ($type === 'image/png' && function_exists('imagecreatefrompng') && kompasz_is_palette_png($file)) {
        $im = @imagecreatefrompng($file);
        if (!$im) return null;
        imagepalettetotruecolor($im); imagealphablending($im, false); imagesavealpha($im, true);
        $path = trailingslashit(dirname($file)) . $target;
        $ok = imagewebp($im, $path, KOMPASZ_WEBP_QUALITY);
        imagedestroy($im);
        return $ok ? $path : null;
    }
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) return null;
    $editor->set_quality(KOMPASZ_WEBP_QUALITY);
    $saved = $editor->save(trailingslashit(dirname($file)) . $target, 'image/webp');
    return is_wp_error($saved) || empty($saved['path']) ? null : $saved['path'];
}

// Feltöltéskor (médiatár és importált logók) az eredeti fájl is WebP-re vált
add_filter('wp_handle_upload', function ($upload) {
    if (!empty($upload['error']) || empty($upload['file'])) return $upload;
    $webp = kompasz_to_webp($upload['file']);
    if (!$webp) return $upload;
    @unlink($upload['file']);
    $upload['url'] = trailingslashit(dirname($upload['url'])) . basename($webp);
    $upload['file'] = $webp;
    $upload['type'] = 'image/webp';
    return $upload;
});

/** A már feltöltött JPG/PNG képek átalakítása (egyszeri, kötegelt futtatás) */
function kompasz_convert_existing_to_webp($limit = 40) {
    $ids = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => ['image/jpeg', 'image/png'], 'fields' => 'ids', 'numberposts' => $limit]);
    $done = 0;
    foreach ($ids as $id) {
        $file = get_attached_file($id);
        $webp = $file ? kompasz_to_webp($file) : null;
        if (!$webp) continue;
        $old_meta = wp_get_attachment_metadata($id);
        if (!empty($old_meta['sizes'])) foreach ($old_meta['sizes'] as $s) @unlink(trailingslashit(dirname($file)) . $s['file']);
        @unlink($file);
        update_attached_file($id, $webp);
        wp_update_post(['ID' => $id, 'post_mime_type' => 'image/webp']);
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $webp));
        $done++;
    }
    return ['converted' => $done, 'remaining' => count(get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => ['image/jpeg', 'image/png'], 'fields' => 'ids', 'numberposts' => -1]))];
}
