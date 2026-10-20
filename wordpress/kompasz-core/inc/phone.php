<?php
// Telefonszámok: egységes nemzetközi formátum (+36 20 404 4280) és ellenőrzés. A böngészőben ugyanez fut (assets/admin.js).
if (!defined('ABSPATH')) exit;

/** Alapértelmezett országhívó a webhely nyelve szerint */
function kompasz_phone_default_cc() {
    return kompasz_lang() === 'de' ? '49' : '36';
}

/** A szám részekre bontva: [országhívó, körzet/mobilhálózat, előfizetői szám] vagy null, ha nem értelmezhető */
function kompasz_phone_parse($raw, $cc = null) {
    $cc = $cc ?: kompasz_phone_default_cc();
    $s = trim((string) $raw);
    if ($s === '') return null;
    $plus = $s[0] === '+';
    $d = preg_replace('/\D/', '', $s);
    if (!$plus) {
        if (strpos($d, '00') === 0) $d = substr($d, 2);
        elseif ($cc === '36' && strpos($d, '06') === 0) $d = '36' . substr($d, 2);
        elseif ($d !== '' && $d[0] === '0') $d = $cc . substr($d, 1);
        elseif (strpos($d, $cc) !== 0) $d = $cc . $d;
    }
    if (strpos($d, '36') === 0) {
        $n = substr($d, 2);
        if ($n === '') return null;
        if ($n[0] === '1') return strlen($n) === 8 ? ['36', '1', substr($n, 1)] : null;
        $area = substr($n, 0, 2);
        $mobile = in_array($area, ['20', '30', '31', '50', '70'], true);
        return strlen($n) === ($mobile ? 9 : 8) && $area[0] !== '0' ? ['36', $area, substr($n, 2)] : null;
    }
    if (strpos($d, '49') === 0) {
        $n = substr($d, 2);
        if (strlen($n) < 6 || strlen($n) > 12 || $n[0] === '0') return null;
        $len = preg_match('/^1[5-7]/', $n) ? 3 : (preg_match('/^(30|40|69|89)/', $n) ? 2 : 3);
        return ['49', substr($n, 0, $len), substr($n, $len)];
    }
    // Egyéb ország: 1–3 jegyű hívószám, összesen 8–15 számjegy (E.164)
    if (strlen($d) < 8 || strlen($d) > 15) return null;
    $cl = in_array($d[0], ['1', '7'], true) ? 1 : (in_array(substr($d, 0, 2), ['20', '27', '30', '31', '32', '33', '34', '39', '40', '41', '43', '44', '45', '46', '47', '48', '51', '52', '54', '55', '61', '81', '82', '86', '90', '91'], true) ? 2 : 3);
    return [substr($d, 0, $cl), '', substr($d, $cl)];
}

/** Megjelenítési formátum: +36 20 404 4280, +36 1 234 5678, +36 22 123 456, +49 30 12345678 */
function kompasz_phone_format($raw, $cc = null) {
    $p = kompasz_phone_parse($raw, $cc);
    if (!$p) return '';
    [$c, $area, $rest] = $p;
    if ($c === '36') $rest = substr($rest, 0, 3) . ' ' . substr($rest, 3);
    elseif ($c !== '49') $rest = preg_replace('/(\d{3})(?=\d{4})/', '$1 ', $rest); // 3-as csoportok, a végén 4 jegy
    return trim("+$c " . ($area !== '' ? "$area " : '') . $rest);
}

/** tel: linkhez */
function kompasz_phone_href($formatted) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $formatted);
}

/** E-mail cím ellenőrzése: formátum és (ha a szerver eléri a DNS-t) a domain levelezőszervere */
function kompasz_email_check($email) {
    if (!is_email($email)) return 'formátum';
    $domain = substr(strrchr($email, '@'), 1);
    // A domain ellenőrzése csak akkor, ha a szerveren működik a DNS-lekérdezés (egy biztosan létező domainnel próbálva)
    static $dns = null;
    if ($dns === null) $dns = apply_filters('kompasz_email_dns_check', function_exists('checkdnsrr') && checkdnsrr('gmail.com', 'MX'));
    if ($dns && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) return 'domain';
    return '';
}
