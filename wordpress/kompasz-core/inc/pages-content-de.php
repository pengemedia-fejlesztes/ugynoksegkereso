<?php
// Német alapoldalak (de.ugynoksegek.pmdev.hu): Über uns, AGB, Datenschutz, Impressum.
// A jogi szövegeket élesítés előtt érdemes jogásszal átnézetni (német piac: TMG/DDG, DSGVO).
if (!defined('ABSPATH')) exit;

function kompasz_page_contents_de() {
    $o = kompasz_operator();
    $mail = sprintf('<a href="mailto:%1$s">%1$s</a>', esc_html($o['email']));
    $op = sprintf('%s (Sitz: %s, Ungarn; Steuernummer: %s; E-Mail: %s)', esc_html($o['name']), esc_html($o['address']), esc_html($o['tax']), $mail);
    $kriterien = esc_url(kompasz_page_url('kivalasztasi-elvek'));
    $site = esc_html(get_bloginfo('name'));

    $rolunk = <<<HTML
<p>{$site} ist ein unabhängiges Vergleichsportal für Agenturen in Deutschland. Nach Leistung sortierte Ranglisten zeigen Online-Marketing-, Google-Ads-, Meta-Ads-, SEO- und KI-SEO-Agenturen, KI-Agenten-Entwickler, PR-Agenturen, Webdesign- und Content-Marketing-Agenturen sowie Amazon-Spezialisten.</p>
<h2>Warum gibt es dieses Portal?</h2>
<p>Die passende Agentur zu finden ist aufwendig: Websites klingen ähnlich, Preise sind selten öffentlich, und echte Erfahrung zeigt sich oft erst im Projekt. Wir bündeln an einem Ort, was für die Entscheidung wichtig ist: Leistungen, Standort, Schwerpunkte und öffentliche Bewertungen.</p>
<h2>Wie entsteht die Reihenfolge?</h2>
<p>Die Reihenfolge basiert auf Kundenbewertungen, Referenzen, fachlicher Erfahrung und öffentlichen Unternehmensdaten. Bezahlte Platzierungen verändern die Reihenfolge nicht und sind immer als „Anzeige“ oder „Hervorgehoben“ gekennzeichnet. Details stehen in den <a href="{$kriterien}">Auswahlkriterien</a>.</p>
<h2>Sie sind eine Agentur?</h2>
<p>Ist Ihre Agentur bereits gelistet, können Sie Ihr Profil übernehmen und Ihre Daten selbst aktualisieren. Noch nicht dabei? Schreiben Sie uns: {$mail}.</p>
<h2>Wer betreibt das Portal?</h2>
<p>Betreiber: {$op}.</p>
HTML;

    $aszf = <<<HTML
<p>Stand: Oktober 2026.</p>
<h2>1. Anbieter</h2>
<p>Diese Website (nachfolgend „Portal“) wird betrieben von {$op} (nachfolgend „Anbieter“). Hosting: {$o['host']}.</p>
<h2>2. Leistungen</h2>
<p>Das Portal veröffentlicht nach Leistung gegliederte Ranglisten, Agenturprofile und redaktionelle Beiträge über Agenturen und Dienstleister in Deutschland. Die Nutzung ist kostenlos und ohne Registrierung möglich.</p>
<h2>3. Agenturprofile und Unternehmenskonten</h2>
<p>Gelistete Unternehmen können auf Anfrage ein Konto erhalten, um ihr Profil zu bearbeiten. Das Unternehmen ist für die Richtigkeit und Rechtmäßigkeit der eingetragenen Inhalte verantwortlich (z. B. Nutzungsrechte an Logo und Texten). Offensichtlich unrichtige, irreführende oder rechtswidrige Inhalte kann der Anbieter ohne vorherige Ankündigung ändern oder entfernen und das Konto sperren.</p>
<h2>4. Reihenfolge und bezahlte Platzierungen</h2>
<p>Die Reihenfolge der Ranglisten stellt der Anbieter nach den in den <a href="{$kriterien}">Auswahlkriterien</a> beschriebenen Gesichtspunkten zusammen. Sie ist eine redaktionelle Einschätzung und keine Empfehlung oder Garantie. Bezahlte (hervorgehobene) Platzierungen sind nach gesonderter Vereinbarung möglich, werden eindeutig als Anzeige gekennzeichnet und beeinflussen die Reihenfolge nicht.</p>
<h2>5. Haftung</h2>
<p>Die Unternehmensdaten stammen aus öffentlichen Quellen und aus Angaben der Unternehmen. Der Anbieter arbeitet sorgfältig, übernimmt aber keine Gewähr für Aktualität und Vollständigkeit. Verträge zwischen Nutzern und gelisteten Unternehmen kommen ausschließlich zwischen diesen zustande; der Anbieter ist nicht Vertragspartei.</p>
<h2>6. Geistiges Eigentum</h2>
<p>Texte, Ranglisten und Gestaltungselemente des Portals sind urheberrechtlich geschützt; Logos und Marken der Unternehmen gehören den jeweiligen Inhabern. Die gewerbliche Vervielfältigung und das automatisierte Auslesen der Inhalte sind ohne schriftliche Zustimmung des Anbieters untersagt.</p>
<h2>7. Beschwerden</h2>
<p>Ist eine Angabe fehlerhaft oder haben Sie Einwände gegen eine Darstellung, schreiben Sie uns: {$mail}. Wir antworten innerhalb von 15 Werktagen.</p>
<h2>8. Schlussbestimmungen</h2>
<p>Es gilt das Recht von Ungarn, soweit zwingende Verbraucherschutzvorschriften nicht entgegenstehen. Der Anbieter kann diese Bedingungen ändern; Änderungen gelten ab Veröffentlichung auf dem Portal.</p>
HTML;

    $adatvedelem = <<<HTML
<p>Stand: Oktober 2026.</p>
<h2>1. Verantwortlicher</h2>
<p>{$op}. Bei Fragen zum Datenschutz erreichen Sie uns unter {$mail}.</p>
<h2>2. Welche Daten verarbeiten wir?</h2>
<p><strong>Kontaktaufnahme.</strong> Wenn Sie uns per E-Mail schreiben, verarbeiten wir Ihren Namen, Ihre E-Mail-Adresse und den Inhalt Ihrer Nachricht, um Ihre Anfrage zu beantworten. Rechtsgrundlage: Art. 6 Abs. 1 lit. b und f DSGVO. Speicherdauer: ein Jahr nach Abschluss der Anfrage.</p>
<p><strong>Unternehmenskonto.</strong> Erhält Ihr Unternehmen ein Konto, verarbeiten wir Name, E-Mail-Adresse und Zugangsdaten der Kontaktperson zum Betrieb des Kontos. Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO. Speicherdauer: bis zur Schließung des Kontos.</p>
<p><strong>Agenturprofile.</strong> Wir veröffentlichen öffentliche Geschäftsdaten gelisteter Unternehmen (Firmenname, Website, Stadt, Leistungen, öffentliche Bewertungen). Enthält ein Profil personenbezogene Daten, verarbeiten wir diese auf Grundlage unseres berechtigten Interesses (Art. 6 Abs. 1 lit. f DSGVO) und berichtigen oder löschen sie auf Anfrage.</p>
<p><strong>Server-Logfiles und Cookies.</strong> Beim Aufruf der Website verarbeitet der Hoster technisch notwendige Daten (z. B. IP-Adresse, Zeitpunkt, aufgerufene Seite) zur sicheren Bereitstellung. Wir setzen nur technisch notwendige Cookies ein; Statistik- oder Marketing-Cookies nur mit Ihrer Einwilligung.</p>
<h2>3. Auftragsverarbeiter</h2>
<p>Hosting: {$o['host']}. Wir verkaufen keine personenbezogenen Daten und geben sie nur weiter, wenn eine gesetzliche Pflicht besteht.</p>
<h2>4. Ihre Rechte</h2>
<p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Wenden Sie sich dazu an die oben genannte E-Mail-Adresse; wir antworten innerhalb von 30 Tagen.</p>
<h2>5. Beschwerderecht</h2>
<p>Sie können sich bei einer Datenschutz-Aufsichtsbehörde beschweren, insbesondere in dem Mitgliedstaat Ihres Aufenthaltsorts. Für den Anbieter zuständig ist die ungarische Datenschutzbehörde NAIH (1055 Budapest, Falk Miksa utca 9–11., Ungarn; www.naih.hu).</p>
HTML;

    $impresszum = <<<HTML
<h2>Angaben zum Anbieter</h2>
<p>{$o['name']}<br>{$o['address']}<br>Ungarn</p>
<p>Steuernummer: {$o['tax']}<br>E-Mail: {$mail}</p>
<h2>Hosting</h2>
<p>{$o['host']}</p>
<h2>Haftung für Inhalte und Links</h2>
<p>Die Inhalte dieses Portals wurden mit Sorgfalt erstellt. Für Richtigkeit, Vollständigkeit und Aktualität übernehmen wir keine Gewähr. Für Inhalte externer Websites, auf die wir verlinken, sind ausschließlich deren Betreiber verantwortlich.</p>
HTML;

    return ['rolunk' => $rolunk, 'aszf' => $aszf, 'adatvedelem' => $adatvedelem, 'impresszum' => $impresszum];
}
