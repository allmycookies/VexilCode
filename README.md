# ENGLISH:
# VexilCode Suite Update 0.9.6.llm.b1

**Please note: The project is currently in German.**

![Screenshot Placeholder](https://dev2.safra-media.com/vexilcode_logo1.png)

VexilCode Suite is a self-hosted web application written in PHP that provides an integrated development environment (IDE) directly on the server. The purpose of the application is to consolidate tools such as FTP clients, SSH terminals, and code editors into a single, web-based interface. This allows for the direct editing and management of web projects on the server, thereby speeding up the development workflow.

---

## Core Features

-   **File Manager**: A reactive user interface for file and directory operations, including upload/download, permissions management (`chmod`), archiving (ZIP/Unzip), and path navigation.
-   **Code Editor**: Based on the Ace Editor, it offers syntax highlighting, find & replace, and a feature for automatically creating backups before saving.
-   **"Vergit" Versioning**: A lightweight, file-based system for versioning projects without the need for Git. It allows saving project states, defining "Beta" and "Stable" channels, and creating test instances.
-   **Search & Replace**: A tool for recursive search and replace operations across entire project directories. The `.srbkup` feature ensures that changes can be undone if necessary.
-   **Collector & Disposer**: A specialized workflow to simplify collaboration with code-generating Large Language Models (LLMs).
-   **LLM Integration**: A direct interface for communicating with external AI APIs from Google (Gemini) and Moonshot AI (Kimi).

---

## AI-Powered Workflow: Collector & Disposer

A key feature is the optimized workflow for interacting with code-generating AIs.

1.  **Collector**: This tool analyzes a selected project directory and consolidates all relevant files (filterable by type) into a single, formatted text file. The crucial feature is the automatically inserted metadata comments that preserve the exact path of each source file (e.g., `// Source file: lib/helpers.php`).

2.  **Collaboration**: The entire content of this collected file is copied and pasted into the prompt of an LLM (e.g., Gemini, GPT-4). The AI thus receives the complete code and file structure of the project, which significantly improves the quality and coherence of its code suggestions.

3.  **Disposer**: After the AI returns the revised code, it is pasted into the Disposer. The tool reads the `// Source file:` comments, automatically creates the necessary directory structure in a new target folder, and writes each file back to its original location without errors.

**Note**: The reliability of the Disposer module depends on the output discipline of the LLM. The existing code block must be left intact by the LLM and only its content modified. The system works best for small to medium-sized projects where the entire context fits into the LLM's prompt.

---

## LLM API Integration

VexilCode enables direct communication with the following LLM providers via their APIs:

-   **Google Gemini**: Uses the **`gemini-1.5-flash-latest`** model. Requests are sent to the `https://generativelanguage.googleapis.com` endpoint.
-   **Moonshot AI (Kimi)**: Uses the **`moonshot-v1-32k`** model. Requests are sent to the `https://api.moonshot.cn` endpoint.

The corresponding API keys must be stored in the application's settings. Communication is server-to-server to keep the keys secure.

---

## Installation

1.  **Download**: Download the latest version.
2.  **Upload**: Unzip the archive and upload the files to your web server.
3.  **Permissions**: Grant the web server write permissions (`755` or `775`) for the `config/` and `data/` directories.
4.  **Setup**: Open the application in your browser to create the first administrator user.
5.  **(Optional) Cleanup**: Open the `ace_cleanup.php` file once in your browser to reduce the size of the editor library for faster loading times. Delete the file afterward.

---

## Security Features

The application was developed with a focus on security and implements multiple layers of protection.

-   **Path Traversal Protection**: All file operations are rigorously validated on the server side. The use of `realpath()` ensures that no access to files or directories outside the `$ROOT_PATH` defined in `config/config.php` is possible.
-   **Cross-Site Scripting (XSS) Prevention**: All dynamic outputs originating from users or the file system (e.g., file names, paths) are consistently treated with `htmlspecialchars()` before being rendered in HTML.
-   **Cross-Site Request Forgery (CSRF) Protection**: All state-changing actions (POST requests) are protected by a session-based CSRF token. Every form sends a token that is validated on the server side.
-   **Robust Authentication & Session Security**:
    * Passwords are securely stored and verified using `password_hash()` and `password_verify()`.
    * Brute-force protection temporarily locks user accounts after multiple failed login attempts.
    * Bot protection using a time-trap and a honeypot field makes automated login attempts more difficult.
    * The session ID is regenerated after a successful login with `session_regenerate_id(true)` to prevent session fixation attacks.
-   **Secure File Uploads**: File names are sanitized during upload with `basename()` to remove embedded path information. The final storage location is also validated against the `$ROOT_PATH`.
-   **Whitelisted Routing**: The main router in `index.php` uses a fixed whitelist of allowed tools (`$allowed_tools`) to prevent the unauthorized inclusion of arbitrary PHP files.

---

## Documentation of Relevant Files and Functions

-   `index.php`: The central entry point and router of the application. It processes the `tool` parameter, loads the corresponding module and the user interface.
-   `login.php` / `logout.php`: Manage user authentication and the initial setup process. Implements security measures like brute-force protection.
-   `config/config.php`: Defines the fundamental system paths `$ROOT_PATH` and `$WEB_ROOT_PATH`, which are crucial for the security architecture.
-   `helpers.php`: Contains global helper functions.
    -   `loadSettings()` / `saveSettings()`: Manage the application-wide settings in `config/settings.json`.
    -   `generate_csrf_token()` / `validate_csrf_token()`: Generate and validate tokens to protect against Cross-Site Request Forgery.
    -   `logMsg()` / `renderLog()`: Standardized functions for creating and displaying log messages.
-   `lib/vergit.class.php`: The core logic for the "Vergit" system. Manages all operations related to projects, versions, instances, and archives.
-   `lib/file_manager_api.php`: The backend for the file manager. Handles all AJAX requests for file operations and rigorously validates all paths.
-   `lib/llm_api.php`: Serves as a server-side proxy for requests to the configured LLM APIs. It takes prompts from the editor and forwards them securely to Gemini or Kimi.

---

## License

This project is licensed under the **MIT License**. For details, see the `LICENSE.md` file.

<details>
  <summary>Show full license text</summary>
  
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

# GERMAN:
# VexilCode Suite Update 0.9.6.llm.b1

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




