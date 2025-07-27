# VexilCode Suite v0.9.4.b4

Schluss mit dem Jonglieren zwischen FTP-Clients, SSH-Terminals und unzähligen Browser-Tabs. Holen Sie sich die Kontrolle über Ihren Server-Workflow zurück – mit einer einzigen, integrierten und blitzschnellen Anwendung, die direkt auf Ihrem Server läuft.

VexilCode ist Ihr persönliches, selbst-gehostetes "Schweizer Taschenmesser" für die Webentwicklung, geschrieben in purem, performantem PHP.

![Screenshot Placeholder](https://dev2.safra-media.com/vexilcode_logo1.png)

---

## 🤔 Warum VexilCode?

In einer Welt von komplexen Cloud-Diensten und schweren Desktop-Anwendungen bietet VexilCode einen erfrischend anderen Ansatz:

* **🚀 Effizienz:** Führen Sie Operationen wie das Zippen von Verzeichnissen oder Suchen & Ersetzen direkt auf dem Server aus – um ein Vielfaches schneller als das Herunterladen, Bearbeiten und erneute Hochladen von Dateien.
* **🔒 Volle Kontrolle:** Ihre Daten, Ihre Werkzeuge, Ihre Regeln. Da VexilCode selbst-gehostet ist, bleiben Ihr Code und Ihre Konfigurationen vollständig unter Ihrer Kontrolle, ohne Abhängigkeiten von Drittanbietern.
* **💡 Innovativer KI-Workflow:** VexilCode ist die **erste und einzige Suite** mit einem dedizierten Workflow, der die Zusammenarbeit mit Code-generierenden KIs wie Gemini oder GPT-4 nicht nur ermöglicht, sondern revolutioniert. Mehr dazu unten.
* **⚙️ Einfachheit:** Keine komplizierten Abhängigkeiten, keine Docker-Container, kein aufwendiges Setup. VexilCode läuft auf nahezu jedem Standard-Webhosting mit PHP-Unterstützung.

---

## ✨ Kernfunktionen im Detail

-   **🗂️ Professioneller Dateimanager:** Eine vollwertige, reaktive Oberfläche für alle Dateioperationen. Inklusive Upload/Download, Berechtigungsverwaltung (`chmod`), Archivierung (.zip) und einer intelligenten Pfad-Navigatio].
-   **🌿 Pragmatische Versionierung ("Vergit"):** Versionieren Sie Ihre Projekte ohne Git-Kenntnisse. "Vergit" ist ein leichtgewichtiges, dateibasiertes System, mit dem Sie Projektstände speichern, "Beta"- und "Stable"-Kanäle definieren und Test-Instanzen mit einem Klick erstellen können.
-   **💻 Integrierter Code Editor:** Bearbeiten Sie Code direkt im Browser mit dem leistungsstarken Ace Editor. Inklusive Syntax-Hervorhebung, Suchen & Ersetzen und der Möglichkeit, vor dem Speichern automatisch Backups anzulegen.
-   **🔍 Mächtiges Suchen & Ersetzen:** Durchsuchen Sie rekursiv ganze Projekte und führen Sie komplexe Ersetzungen durch. Die `.srbkup`-Funktion stellt sicher, dass Sie jede Änderung bei Bedarf rückgängig machen können.

---

## 🤖 Revolutionieren Sie Ihren KI-Workflow mit Collector & Disposer

Dies ist das absolute **Highlight** der VexilCode Suite und der Grund, warum Ihre Arbeitsweise mit KI nie wieder dieselbe sein wird.

**Das Problem:** Jeder, der versucht hat, einer KI ein bestehendes Projekt zur Analyse zu übergeben, kennt den Schmerz: Man kopiert Dutzende Dateien manuell, verliert dabei den Überblick, und die KI hat keinen Kontext über die Dateistruktur, was zu fehlerhaften oder unvollständigen Ergebnissen führt.

**Die VexilCode-Lösung: Ein perfekter 3-Schritte-Kreislauf.**

### **Schritt 1: COLLECT - Das intelligente Sammeln**

Der **Collector** ist mehr als nur ein Kopierwerkzeug. Er scannt Ihr gesamtes Projekt und erstellt eine einzige, makellos formatierte Textdatei.

-   **Kontext ist König:** Das "Geheimnis" sind die automatisch eingefügten Kommentare (`// Quelldatei: ...`), die den exakten Pfad jeder Datei bewahren.
-   **Vollständige Kontrolle:** Sie entscheiden per Klick, welche Dateitypen (php, js, css etc.) gesammelt werden sollen.

### **Schritt 2: COLLABORATE - Die nahtlose Zusammenarbeit**

Kopieren Sie den gesamten Inhalt der generierten Sammeldatei. Fügen Sie ihn in den Prompt Ihrer bevorzugten KI ein. Geben Sie Anweisungen wie:

> *"Hier ist mein komplettes PHP-Projekt. Refaktoriere bitte alle Klassen im Verzeichnis `/lib`, um Interfaces zu verwenden. Füge außerdem eine neue Funktion in `helpers.php` hinzu und binde sie in `index.php` ein."*

Die KI erhält den **vollständigen Code und die Struktur**, was zu drastisch besseren und kohärenteren Ergebnissen führt.

### **Schritt 3: DISPOSE - Die magische Wiederherstellung**

Sobald die KI ihre überarbeitete Version des Codes liefert, kopieren Sie diese. Fügen Sie sie in den **Disposer** ein.

Der Disposer agiert wie ein intelligenter Dekonstruktor:
-   Er liest die `// Quelldatei:` Kommentare.
-   Er erstellt automatisch alle notwendigen Unterverzeichnisse in einem neuen Zielordner.
-   Er schreibt jede Datei fehlerfrei an ihren ursprünglichen Ort zurück.

Das manuelle, fehleranfällige Wiedereinfügen von Code gehört der Vergangenheit an. Ein ganzes Projekt-Refactoring – erledigt in Minuten, nicht in Stunden.

---

## ⚙️ Installation

1.  **Herunterladen:** Laden Sie die neueste Version herunter.
2.  **Hochladen:** Entpacken Sie das Archiv und laden Sie die Dateien auf Ihren Webserver.
3.  **Berechtigungen setzen:** Geben Sie dem Webserver Schreibrechte (`755` oder `775`) für die Verzeichnisse `config/` und `data/`.
4.  **Setup ausführen:** Rufen Sie die Anwendung im Browser auf, um den ersten Administrator-Benutzer anzulegen.

---

## 🤝 Mitwirken

Beiträge zur Verbesserung der VexilCode Suite sind herzlich willkommen! Ob es sich um Fehlerberichte, Funktionswünsche oder Pull-Requests handelt – lassen Sie uns dieses Tool gemeinsam noch besser machen.

---

## 📜 Lizenz

Dieses Projekt steht unter der **MIT-Lizenz**. Sie können den Code frei verwenden, verändern und weiterverbreiten, solange Sie den ursprünglichen Copyright-Vermerk beibehalten.

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
