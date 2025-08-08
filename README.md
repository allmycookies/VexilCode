# VexilCode Suite
Update 0.9.6.llm.b1
![Screenshot Placeholder](https://dev2.safra-media.com/vexilcode_logo1.png)

VexilCode Suite ist eine in PHP geschriebene, selbst-gehostete Web-Anwendung, die eine integrierte Entwicklungsumgebung (IDE) direkt auf dem Server bereitstellt. Der Zweck der Anwendung ist die Konsolidierung von Werkzeugen wie FTP-Clients, SSH-Terminals und Code-Editoren in einer einzigen, webbasierten Oberfläche. Dies ermöglicht die direkte Bearbeitung und Verwaltung von Webprojekten auf dem Server, wodurch der Entwicklungs-Workflow beschleunigt wird.

---

## Kernfunktionen

- **Dateimanager**: Eine reaktive Benutzeroberfläche für Datei- und Verzeichnisoperationen, einschließlich Upload/Download, Rechteverwaltung (`chmod`), Archivierung (ZIP/Unzip) und Pfad-Navigation.
- **Code-Editor**: Basiert auf dem Ace Editor und bietet Syntax-Hervorhebung, Suchen & Ersetzen sowie eine Funktion zur automatischen Erstellung von Backups vor dem Speichern.
- **"Vergit" Versionierung**: Ein leichtgewichtiges, dateibasiertes System zur Versionierung von Projekten ohne die Notwendigkeit von Git. Es ermöglicht das Speichern von Projektständen, das Definieren von "Beta"- und "Stable"-Kanälen und die Erstellung von Test-Instanzen.
- **Suchen & Ersetzen**: Ein Werkzeug für rekursive Such- und Ersetzungsvorgänge in ganzen Projektverzeichnissen. Die `.srbkup`-Funktion stellt sicher, dass Änderungen bei Bedarf rückgängig gemacht werden können.
- **Collector & Disposer**: Ein spezialisierter Workflow zur Vereinfachung der Zusammenarbeit mit Code-generierenden Large Language Models (LLMs).
- **LLM-Integration**: Eine direkte Schnittstelle zur Kommunikation mit externen KI-APIs von Google (Gemini) und Moonshot AI (Kimi).

---

## KI-gestützter Workflow: Collector & Disposer

Ein zentrales Merkmal ist der optimierte Arbeitsablauf für die Interaktion mit Code-generierenden KIs.

1.  **Collector**: Dieses Werkzeug analysiert ein ausgewähltes Projektverzeichnis und fasst alle relevanten Dateien (nach Typ filterbar) in einer einzigen, formatierten Textdatei zusammen. Das entscheidende Merkmal sind die automatisch eingefügten Metadaten-Kommentare, die den exakten Pfad jeder Quelldatei beibehalten (z. B. `// Quelldatei: lib/helpers.php`).

2.  **Kollaboration**: Der gesamte Inhalt dieser Sammeldatei wird kopiert und in den Prompt eines LLMs (z. B. Gemini, GPT-4) eingefügt. Die KI erhält somit den vollständigen Code und die Dateistruktur des Projekts, was die Qualität und Kohärenz ihrer Code-Vorschläge signifikant verbessert.

3.  **Disposer**: Nachdem die KI den überarbeiteten Code zurückgegeben hat, wird dieser in den Disposer eingefügt. Das Werkzeug liest die `// Quelldatei:` Kommentare, erstellt automatisch die notwendige Verzeichnisstruktur in einem neuen Zielordner und schreibt jede Datei fehlerfrei an ihren ursprünglichen Ort zurück.

**Hinweis**: Die Zuverlässigkeit des Disposer-Moduls hängt von der Ausgabedisziplin des LLMs ab. Der bestehende Code-Block muss vom LLM intakt gelassen und nur inhaltlich modifiziert werden. Das System funktioniert am besten bei kleineren bis mittelgroßen Projekten, bei denen der gesamte Kontext in den Prompt des LLMs passt.

---

## LLM API-Integration

VexilCode ermöglicht die direkte Kommunikation mit folgenden LLM-Anbietern über deren APIs:

-   **Google Gemini**: Nutzt das Modell **`gemini-1.5-flash-latest`**. Die Anfragen werden an den Endpunkt `https://generativelanguage.googleapis.com` gesendet.
-   **Moonshot AI (Kimi)**: Nutzt das Modell **`moonshot-v1-32k`**. Die Anfragen werden an den Endpunkt `https://api.moonshot.cn` gesendet.

Die entsprechenden API-Schlüssel müssen in den Einstellungen der Anwendung hinterlegt werden. Die Kommunikation erfolgt Server-zu-Server, um die Schlüssel sicher zu halten.

---

## Installation

1.  **Herunterladen**: Laden Sie die neueste Version herunter.
2.  **Hochladen**: Entpacken Sie das Archiv und laden Sie die Dateien auf Ihren Webserver.
3.  **Berechtigungen**: Geben Sie dem Webserver Schreibrechte (`755` oder `775`) für die Verzeichnisse `config/` und `data/`.
4.  **Setup**: Rufen Sie die Anwendung im Browser auf, um den ersten Administrator-Benutzer anzulegen.
5.  **(Optional) Aufräumen**: Rufen Sie die Datei `ace_cleanup.php` einmal im Browser auf, um die Editor-Bibliothek für schnellere Ladezeiten zu verkleinern. Löschen Sie die Datei danach.

---

## Sicherheitsfeatures

Die Anwendung wurde mit einem Fokus auf Sicherheit entwickelt und implementiert mehrere Schutzebenen.

-   **Path Traversal Protection**: Alle Dateioperationen werden serverseitig rigoros validiert. Durch die Verwendung von `realpath()` wird sichergestellt, dass kein Zugriff auf Dateien oder Verzeichnisse außerhalb des in `config/config.php` definierten `$ROOT_PATH` möglich ist.
-   **Cross-Site Scripting (XSS) Prevention**: Alle dynamischen Ausgaben, die von Benutzern oder dem Dateisystem stammen (z.B. Dateinamen, Pfade), werden konsequent mit `htmlspecialchars()` behandelt, bevor sie im HTML gerendert werden.
-   **Cross-Site Request Forgery (CSRF) Protection**: Alle statusverändernden Aktionen (POST-Requests) werden durch ein Session-basiertes CSRF-Token geschützt. Jedes Formular sendet ein Token, das serverseitig validiert wird.
-   **Robuste Authentifizierung & Session-Sicherheit**:
    * Passwörter werden mit `password_hash()` und `password_verify()` sicher gespeichert und überprüft.
    * Ein Brute-Force-Schutz sperrt Benutzerkonten nach mehreren fehlgeschlagenen Anmeldeversuchen temporär.
    * Ein Bot-Schutz mittels Time-Trap und Honeypot-Feld erschwert automatisierte Anmeldeversuche.
    * Die Session-ID wird nach erfolgreichem Login mit `session_regenerate_id(true)` neu generiert, um Session-Fixation-Angriffe zu verhindern.
-   **Sichere Datei-Uploads**: Dateinamen werden beim Upload mit `basename()` bereinigt, um eingeschleuste Pfadinformationen zu entfernen. Der finale Speicherort wird ebenfalls gegen den `$ROOT_PATH` validiert.
-   **Whitelisted Routing**: Der Haupt-Router in `index.php` verwendet eine feste Whitelist von erlaubten Tools (`$allowed_tools`), um das unbefugte Einbinden von beliebigen PHP-Dateien zu verhindern.

---

## Dokumentation relevanter Dateien und Funktionen

-   `index.php`: Der zentrale Einstiegspunkt und Router der Anwendung. Er verarbeitet die `tool`-Parameter, lädt das entsprechende Modul und die Benutzeroberfläche.
-   `login.php` / `logout.php`: Verwalten die Benutzerauthentifizierung und den initialen Setup-Prozess. Implementiert Sicherheitsmaßnahmen wie Brute-Force-Schutz.
-   `config/config.php`: Definiert die fundamentalen Systempfade `$ROOT_PATH` und `$WEB_ROOT_PATH`, die für die Sicherheitsarchitektur entscheidend sind.
-   `helpers.php`: Enthält globale Hilfsfunktionen.
    -   `loadSettings()` / `saveSettings()`: Verwalten die anwendungsweiten Einstellungen in `config/settings.json`.
    -   `generate_csrf_token()` / `validate_csrf_token()`: Erzeugen und validieren Tokens zum Schutz vor Cross-Site Request Forgery.
    -   `logMsg()` / `renderLog()`: Standardisierte Funktionen für die Erstellung und Anzeige von Log-Nachrichten.
-   `lib/vergit.class.php`: Die Kernlogik für das "Vergit"-System. Verwaltet alle Operationen bezüglich Projekten, Versionen, Instanzen und Archiven.
-   `lib/file_manager_api.php`: Das Backend für den Dateimanager. Verarbeitet alle AJAX-Anfragen für Dateioperationen und validiert alle Pfade rigoros.
-   `lib/llm_api.php`: Dient als serverseitiger Proxy für Anfragen an die konfigurierten LLM-APIs. Nimmt Prompts vom Editor entgegen und leitet sie sicher an Gemini oder Kimi weiter.

---

## Lizenz

Dieses Projekt steht unter der **MIT-Lizenz**. Details finden Sie in der `LICENSE.md`-Datei.

<details>
  <summary>Vollständigen Lizenztext anzeigen</summary>
  
  ```plaintext
  Copyright (c) [2025] [Denys Safra]

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all
  copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
  ```
</details>


