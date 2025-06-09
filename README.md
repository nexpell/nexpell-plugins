# nexpell – Plugins

Dieses Repository enthält eine Sammlung von modernisierten Plugins für das **nexpell CMS**, entwickelt für Clans, Communities, Vereine und private Projekte.

Alle Plugins sind vollständig kompatibel mit der neuen Struktur von nexpell und verwenden:
- PHP 8.1+
- Bootstrap 5.3
- Getrennte Templates (HTML/PHP)
- Mehrsprachigkeit via `language/`-Ordner (DE, EN, IT)
- DSGVO-konforme Features (z. B. Counter, Who is Online)
- Plugin-Installer-Unterstützung

---

## 📦 Verfügbare Plugins

| Plugin          | Funktion                                           | Status     			|
|-----------------|----------------------------------------------------|------------------------|
| **News**        | Klassisches News-Plugin mit Kategorien             | 🟡 Kommentare inaktiv	|
| **Blog**        | Einfache Blogeinträge mit optionalem Archiv        | ✅ Stabil   			|
| **Carousel**    | Bootstrap-Slider für Bilder und Texte              | ✅ Stabil   			|
| **Clan Rules**  | Anzeige der Clan-/Community-Regeln                 | ✅ Stabil   			|
| **Userlist**    | Öffentliche Mitgliederliste                        | ✅ Stabil   			|
| **Lastlogin**   | Zeigt letzte Logins & Aktivitätsstatus             | ✅ Stabil   			|
| **Partners**    | Partnerlogos und Links                             | ✅ Modular  			|
| **Sponsors**    | Sponsorenlogos mit Farbe, Link & Text              | ✅ Modular  			|
| **Gallery**     | Bildergalerie mit Drag & Drop                      | ✅ Umfangreich 		|
| **About**       | Infoseite mit Sektionen & Bildern                  | ✅ Bootstrap 			|
| **Pricing**     | Preisboxen mit Featureliste                        | ✅ Marketingfähig 		|
| **Masterlist**  | CoD-Serverliste mit IP und Status                  | ✅ Gamingfokus 		|
| **Articles**    | Redaktionelle Artikel inkl. Bewertung & Kommentare | ✅ Vollständig 		|
| **Counter**     | Besucherzähler (nur Admin, DSGVO-konform)          | ✅ Datenschutz 		|
| **Who is online**| Anzeige aktiver Nutzer (nur Admin)                | ✅ Datenschutz 		|
| **Links**       | Empfehlungsliste für externe Ressourcen            | ✅ Klar strukturiert 	|
| **Entwicklung** | Zeitstrahl der nexpell-Entwicklung                 | ✅ Statisch 			|

---

## 🛠 Installation

1. **Plugin-Installer** im Adminbereich öffnen (`admincenter.php?site=admin_plugin_installer`)
2. Gewünschtes Plugin aus der Liste auswählen und installieren
3. Navigationslink wird automatisch gesetzt (falls im Plugin enthalten)
4. Plugin über den entsprechenden Adminbereich konfigurieren (`admin_[plugin]`)
5. Sprachdateien bei Bedarf anpassen (`/language/de.php`, `en.php` etc.)

---

## 💡 Besonderheiten

- Alle Plugins folgen einem einheitlichen Coding-Standard.
- CSS und JS (sofern nötig) befinden sich im Plugin-Ordner.
- Viele Plugins unterstützen die neue `Template`-Engine mit `{if}`, `{foreach}` etc.
- Unterstützung für Multisite-Setups ist vorgesehen.

---

## 📜 Lizenz

Dieses Projekt steht unter der **GNU General Public License v3.0**.  
Siehe `LICENSE.md` für vollständige Lizenzinformationen.

---

## 🤝 Mitwirken

Wir freuen uns über jede Hilfe:
- Bugreports oder Verbesserungsvorschläge als [Issue](https://github.com/nexpell/nexpell-plugins/issues)
- Pull Requests mit neuen Features oder Fixes
- Sprachübersetzungen (EN, IT, weitere)

---

## 🔗 Weiterführende Links

- [nexpell Hauptrepo](https://github.com/nexpell/nexpell)
- [Offizielle Website](https://www.nexpell.org)
- [Dokumentation (in Arbeit)](https://www.nexpell.org/doku/)

---

© 2025 nexpell Development Team
