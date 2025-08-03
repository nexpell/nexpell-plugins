<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database;

// LanguageService initialisieren (erst danach detectLanguage aufrufen)
$languageService = new LanguageService($_database);
$lang = $languageService->detectLanguage();
$currentLang = $languageService->currentLanguage;

// Admin-Modul-Sprache laden
$languageService->readPluginModule('resume');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('resume_title'),
        'subtitle' => 'Changelog'
    ];
    
    echo $tpl->loadTemplate("resume", "head", $data_array, 'plugin');

    #echo $tpl->loadTemplate("leistung", "content", [], 'plugin');

function getMultiLangText(string $text, string $lang): string {
    // Alle Sprachblöcke extrahieren
    preg_match_all('/\[\[lang:([a-z]{2})\]\](.*?)(?=\[\[lang:|$)/is', $text, $matches, PREG_SET_ORDER);

    // Durchsuche alle gefundenen Sprachblöcke
    foreach ($matches as $match) {
        if ($match[1] === $lang) {
            return trim($match[2]);
        }
    }

    // Fallback: erster Block
    return isset($matches[0][2]) ? trim($matches[0][2]) : $text;
}
?>

<div class="card">
  <div class="card-body">
    <section id="resume" class="resume py-5 bg-light">
      <div class="container">
        <div class="text-center mb-5">
          <h2 class="fw-bold">
            <?php echo getMultiLangText('[[lang:de]]Entwicklungsgeschichte von nexpell[[lang:en]]Development History of nexpell[[lang:it]]Storia dello sviluppo di nexpell', $currentLang); ?>
          </h2>
          <p class="text-muted">
            <?php echo getMultiLangText('[[lang:de]]Seit 2018 kontinuierlich weiterentwickelt[[lang:en]]Continuously developed since 2018[[lang:it]]Sviluppato continuamente dal 2018', $currentLang); ?>
          </p>
        </div>

        <div class="row">
          <div class="col-lg-12">

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2018 – Gründung von Webspell-RM[[lang:en]]2018 – Founding of Webspell-RM[[lang:it]]2018 – Fondazione di Webspell-RM', $currentLang); ?>
              </h4>
              <p>
                <?php echo getMultiLangText('[[lang:de]]Am 12. September 2018 wurde Webspell-RM ins Leben gerufen, nachdem das Team von <a href="https://www.designperformance.de" target="_blank">Design Performance</a> das Projekt übernommen hatte. Zuvor wurde das Projekt <strong>webSPELL-NOR</strong> infolge der neuen Datenschutzgrundverordnung (DSGVO) eingestellt. Auch nach Anpassungen an die Datenschutzvorgaben wurde am 31.10.2018 intern das Ende von NOR beschlossen und die Webseite abgeschaltet.[[lang:en]]On September 12, 2018, Webspell-RM was launched after the team from <a href="https://www.designperformance.de" target="_blank">Design Performance</a> took over the project. Previously, the project <strong>webSPELL-NOR</strong> was discontinued due to the new General Data Protection Regulation (GDPR). Even after adapting to data protection requirements, NOR was internally ended on 31.10.2018 and the website was shut down.[[lang:it]]Il 12 settembre 2018 è stato lanciato Webspell-RM, dopo che il team di <a href="https://www.designperformance.de" target="_blank">Design Performance</a> aveva preso in gestione il progetto. In precedenza, il progetto <strong>webSPELL-NOR</strong> era stato interrotto a seguito del nuovo Regolamento Generale sulla Protezione dei Dati (GDPR). Anche dopo le modifiche per conformarsi alle normative sulla privacy, il 31.10.2018 è stata decisa internamente la fine di NOR e il sito è stato chiuso.', $currentLang); ?>
              </p>
              <p>
                <?php echo getMultiLangText('[[lang:de]]Viele Ideen, die ursprünglich für NOR vorgesehen waren, konnten nicht umgesetzt werden. Mit Zustimmung der damaligen NOR-Administratoren wurde daher <strong>webSPELL | RM</strong> gegründet – mit dem Ziel, diese Ideen neu zu verwirklichen und das beliebte CMS technisch wie gestalterisch auf ein neues Level zu heben.[[lang:en]]Many ideas originally planned for NOR could not be implemented. With the consent of the then NOR administrators, <strong>webSPELL | RM</strong> was founded with the aim of realizing these ideas anew and taking the popular CMS to a new technical and design level.[[lang:it]]Molte idee originariamente previste per NOR non sono state realizzate. Con il consenso degli amministratori di NOR dell’epoca, è stato quindi fondato <strong>webSPELL | RM</strong> con l’obiettivo di realizzare queste idee in modo nuovo e portare il CMS popolare a un nuovo livello tecnico e di design.', $currentLang); ?>
              </p>
              <p>
                <?php echo getMultiLangText('[[lang:de]]Alle bestehenden Plugins wurden vollständig überarbeitet, um mit der neuen RM-Version kompatibel zu sein. Webspell-RM war damit das letzte aktive Webspell-Projekt vor dem späteren Umstieg auf nexpell.[[lang:en]]All existing plugins were completely revised to be compatible with the new RM version. Webspell-RM was thus the last active Webspell project before the later switch to nexpell.[[lang:it]]Tutti i plugin esistenti sono stati completamente rivisti per essere compatibili con la nuova versione RM. Webspell-RM è stato così l’ultimo progetto Webspell attivo prima del successivo passaggio a nexpell.', $currentLang); ?>
              </p>
            </div>

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2019–2020 – Einführung neuer Features[[lang:en]]2019–2020 – Introduction of New Features[[lang:it]]2019–2020 – Introduzione di nuove funzionalità', $currentLang); ?>
              </h4>
              <ul>
                <li><?php echo getMultiLangText('[[lang:de]]Integration des Bootstrap 5 Frameworks für ein modernes Design[[lang:en]]Integration of Bootstrap 5 framework for a modern design[[lang:it]]Integrazione del framework Bootstrap 5 per un design moderno', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Einführung des CKEditor 4 für eine verbesserte Textbearbeitung[[lang:en]]Introduction of CKEditor 4 for improved text editing[[lang:it]]Introduzione di CKEditor 4 per un\'editing del testo migliorato', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Plugin- und Template-Installer zur einfachen Erweiterung[[lang:en]]Plugin and template installer for easy extension[[lang:it]]Installer di plugin e template per un\'estensione semplice', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Mehrsprachigkeit: Deutsch, Englisch, Italienisch[[lang:en]]Multilingualism: German, English, Italian[[lang:it]]Multilingua: tedesco, inglese, italiano', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Updater für einfache Systemaktualisierungen[[lang:en]]Updater for easy system updates[[lang:it]]Updater per aggiornamenti di sistema facili', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Bis zu 84 Plugins und 13 Templates verfügbar[[lang:en]]Up to 84 plugins and 13 templates available[[lang:it]]Fino a 84 plugin e 13 template disponibili', $currentLang); ?></li>
              </ul>
            </div>

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2021 – Sicherheits- und Performance-Updates[[lang:en]]2021 – Security and Performance Updates[[lang:it]]2021 – Aggiornamenti di sicurezza e prestazioni', $currentLang); ?>
              </h4>
              <ul>
                <li><?php echo getMultiLangText('[[lang:de]]CKEditor Update auf Version 4.16.0 (Sicherheitslücken geschlossen)[[lang:en]]CKEditor update to version 4.16.0 (security vulnerabilities fixed)[[lang:it]]Aggiornamento di CKEditor alla versione 4.16.0 (vulnerabilità di sicurezza risolte)', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Personalisierte Avatar-Icons für Module[[lang:en]]Personalized avatar icons for modules[[lang:it]]Icone avatar personalizzate per i moduli', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Überarbeitetes Dashboard mit "Express Settings"[[lang:en]]Reworked dashboard with "Express Settings"[[lang:it]]Dashboard rivisto con "Express Settings"', $currentLang); ?></li>
              </ul>
            </div>

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2023 – Kompatibilität und Benutzerfreundlichkeit[[lang:en]]2023 – Compatibility and Usability[[lang:it]]2023 – Compatibilità e facilità d\'uso', $currentLang); ?>
              </h4>
              <ul>
                <li><?php echo getMultiLangText('[[lang:de]]Admincenter modernisiert mit Bootstrap 5[[lang:en]]Admin center modernized with Bootstrap 5[[lang:it]]Centro admin modernizzato con Bootstrap 5', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]PHP 8.2 kompatibel[[lang:en]]PHP 8.2 compatible[[lang:it]]Compatibile con PHP 8.2', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Widget-Positionierung & Plugin-Konfiguration vereinfacht[[lang:en]]Widget positioning & plugin configuration simplified[[lang:it]]Posizionamento widget e configurazione plugin semplificati', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Neue Begrüßungsseite im Adminbereich[[lang:en]]New welcome page in the admin area[[lang:it]]Nuova pagina di benvenuto nell\'area admin', $currentLang); ?></li>
              </ul>
            </div>

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2024 – Weitere Optimierungen[[lang:en]]2024 – Further Optimizations[[lang:it]]2024 – Ulteriori ottimizzazioni', $currentLang); ?>
              </h4>
              <ul>
                <li><?php echo getMultiLangText('[[lang:de]]Modul-Übersicht zur besseren Plugin-Verwaltung[[lang:en]]Module overview for better plugin management[[lang:it]]Panoramica dei moduli per una migliore gestione dei plugin', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Language Editor zur einfachen Übersetzung von Inhalten[[lang:en]]Language editor for easy content translation[[lang:it]]Editor linguistico per una facile traduzione dei contenuti', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Sticky Navigation Optionen für Admin-Navigation[[lang:en]]Sticky navigation options for admin navigation[[lang:it]]Opzioni di navigazione sticky per la navigazione admin', $currentLang); ?></li>
              </ul>
            </div>

            <div class="resume-item mb-4">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]2025 – Neuentwicklung als nexpell[[lang:en]]2025 – Redevelopment as nexpell[[lang:it]]2025 – Riadattamento come nexpell', $currentLang); ?>
              </h4>
              <p>
                <?php echo getMultiLangText('[[lang:de]]Im Jahr 2025 wurde das System unter dem neuen Namen <strong>nexpell</strong> komplett neu entwickelt mit einem modernen, responsiven Adminbereich.[[lang:en]]In 2025, the system was completely redeveloped under the new name <strong>nexpell</strong> with a modern, responsive admin area.[[lang:it]]Nel 2025 il sistema è stato completamente riadattato con il nuovo nome <strong>nexpell</strong> con un\'area admin moderna e responsive.', $currentLang); ?>
              </p>
              <ul>
                <li><?php echo getMultiLangText('[[lang:de]]Integration und Umstieg auf Bootstrap 5.3 als Design- und Komponentenbasis[[lang:en]]Integration and switch to Bootstrap 5.3 as design and component basis[[lang:it]]Integrazione e passaggio a Bootstrap 5.3 come base per design e componenti', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Einführung eines modularen Adminsystems mit separaten Bereichen für Pricing, About, Gallery, Resume u.v.m.[[lang:en]]Introduction of a modular admin system with separate areas for Pricing, About, Gallery, Resume, and more[[lang:it]]Introduzione di un sistema admin modulare con aree separate per Pricing, About, Gallery, Resume e altro', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Verbesserte Drag-&-Drop-Sortierung mit AJAX, inklusive Seitenvorschau für Galerien und Inhalte[[lang:en]]Improved drag & drop sorting with AJAX, including page preview for galleries and content[[lang:it]]Ordinamento drag & drop migliorato con AJAX, inclusa l\'anteprima della pagina per gallerie e contenuti', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Neuer Theme- und Template-Wechsler mit Live-Vorschau und komfortabler Speicherung[[lang:en]]New theme and template switcher with live preview and comfortable saving[[lang:it]]Nuovo switcher per temi e template con anteprima live e salvataggio comodo', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Vollständig neu strukturierte Plugin-Architektur mit eigenständigen Admin-Panels und Datenbanksteuerung[[lang:en]]Completely restructured plugin architecture with independent admin panels and database control[[lang:it]]Architettura plugin completamente ristrutturata con pannelli admin indipendenti e controllo del database', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Systematischer Ausbau von Hilfetexten, Tooltips und modaler Vorschau zur Verbesserung der Benutzerfreundlichkeit (UX)[[lang:en]]Systematic expansion of help texts, tooltips and modal preview to improve usability (UX)[[lang:it]]Espansione sistematica di testi di aiuto, tooltip e anteprime modali per migliorare l\'usabilità (UX)', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Zahlreiche Kernfunktionen aus Webspell-RM 2.1.6 wurden überarbeitet, optimiert oder komplett neu geschrieben[[lang:en]]Numerous core functions from Webspell-RM 2.1.6 were revised, optimized or completely rewritten[[lang:it]]Numerose funzioni core di Webspell-RM 2.1.6 sono state riviste, ottimizzate o riscritte completamente', $currentLang); ?></li>
                <li>
                  <strong><?php echo getMultiLangText('[[lang:de]]Deutliche Verbesserungen bei der Sicherheit:[[lang:en]]Significant improvements in security:[[lang:it]]Significativi miglioramenti nella sicurezza:', $currentLang); ?></strong>
                  <ul>
                    <li><?php echo getMultiLangText('[[lang:de]]Einführung von CSRF-Schutz in Formularen[[lang:en]]Introduction of CSRF protection in forms[[lang:it]]Introduzione della protezione CSRF nei form', $currentLang); ?></li>
                    <li><?php echo getMultiLangText('[[lang:de]]Prepared Statements für alle Datenbankzugriffe zur Vermeidung von SQL-Injections[[lang:en]]Prepared statements for all database access to avoid SQL injections[[lang:it]]Prepared statements per tutti gli accessi al database per evitare SQL injection', $currentLang); ?></li>
                    <li><?php echo getMultiLangText('[[lang:de]]Verbesserte Benutzer- und Rechteverwaltung[[lang:en]]Improved user and rights management[[lang:it]]Gestione utenti e permessi migliorata', $currentLang); ?></li>
                    <li><?php echo getMultiLangText('[[lang:de]]Sicherere Passwort-Hashing-Methoden[[lang:en]]More secure password hashing methods[[lang:it]]Metodi di hashing delle password più sicuri', $currentLang); ?></li>
                  </ul>
                </li>
                <li><?php echo getMultiLangText('[[lang:de]]Verbesserte Performance durch moderne PHP-Standards und optimierten Code[[lang:en]]Improved performance through modern PHP standards and optimized code[[lang:it]]Prestazioni migliorate grazie a standard PHP moderni e codice ottimizzato', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Integration neuer Features wie SEO-optimierte URL-Strukturen und bessere Mehrsprachigkeit[[lang:en]]Integration of new features such as SEO-optimized URL structures and improved multilingualism[[lang:it]]Integrazione di nuove funzionalità come URL ottimizzate per SEO e migliore multilingua', $currentLang); ?></li>
                <li><?php echo getMultiLangText('[[lang:de]]Vereinfachte Erweiterbarkeit und Wartbarkeit durch konsequente Trennung von Logik und Darstellung[[lang:en]]Simplified extensibility and maintainability through consistent separation of logic and presentation[[lang:it]]Estensibilità e manutenibilità semplificate grazie a una chiara separazione tra logica e presentazione', $currentLang); ?></li>
              </ul>
            </div>

            <div class="resume-item">
              <h4>
                <?php echo getMultiLangText('[[lang:de]]Weitere Informationen[[lang:en]]Further Information[[lang:it]]Ulteriori informazioni', $currentLang); ?>
              </h4>
              <ul>
                <li><a href="https://www.nexpell.de" target="_blank"><?php echo getMultiLangText('[[lang:de]]Offizielle Website[[lang:en]]Official Website[[lang:it]]Sito ufficiale', $currentLang); ?></a></li>
                <li><a href="https://github.com/nexpell" target="_blank"><?php echo getMultiLangText('[[lang:de]]GitHub-Repository (Basis)[[lang:en]]GitHub Repository (Base)[[lang:it]]Repository GitHub (Base)', $currentLang); ?></a></li>
                <li><a href="https://www.nexpell.de/forum.html" target="_blank"><?php echo getMultiLangText('[[lang:de]]Forum[[lang:en]]Forum[[lang:it]]Forum', $currentLang); ?></a></li>
                <li><a href="https://www.nexpell.de/wiki.html" target="_blank"><?php echo getMultiLangText('[[lang:de]]Wiki[[lang:en]]Wiki[[lang:it]]Wiki', $currentLang); ?></a></li>
              </ul>
            </div>

          </div>
        </div>
      </div>
    </section>
  </div>
</div>
