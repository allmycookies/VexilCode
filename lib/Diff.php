<?php // lib/Diff.php

/**
 * Eine einfache Klasse zur Erstellung einer visuellen "Diff"-Ansicht von zwei Strings.
 * Basiert auf dem Longest Common Subsequence Algorithmus.
 */
class Diff
{
    /**
     * Erzeugt eine HTML-Tabelle, die die Unterschiede zwischen zwei Strings darstellt.
     *
     * @param string $old Der alte String (z.B. Backup-Datei).
     * @param string $new Der neue String (z.B. aktuelle Datei).
     * @return string Der generierte HTML-Code für die Diff-Ansicht.
     */
    public static function toHtml(string $old, string $new): string
    {
        $diff = self::calculate(explode("\n", $old), explode("\n", $new));
        if (empty($diff)) {
            return '<p class="text-center text-muted p-3">Keine Unterschiede gefunden.</p>';
        }

        $html = '<table class="table table-sm diff-table">';
        $lineNumOld = 1;
        $lineNumNew = 1;

        foreach ($diff as $line) {
            $lineContent = htmlspecialchars(rtrim($line[0], "\r\n"));
            switch ($line[1]) {
                case -1: // DELETED
                    $html .= '<tr class="diff-deleted">';
                    $html .= '<td class="diff-line-num">' . $lineNumOld++ . '</td>';
                    $html .= '<td class="diff-line-num"></td>';
                    $html .= '<td class="diff-line"><span class="diff-marker">-</span>' . $lineContent . '</td>';
                    $html .= '</tr>';
                    break;
                case 1: // ADDED
                    $html .= '<tr class="diff-added">';
                    $html .= '<td class="diff-line-num"></td>';
                    $html .= '<td class="diff-line-num">' . $lineNumNew++ . '</td>';
                    $html .= '<td class="diff-line"><span class="diff-marker">+</span>' . $lineContent . '</td>';
                    $html .= '</tr>';
                    break;
                default: // UNCHANGED
                    $html .= '<tr class="diff-unchanged">';
                    $html .= '<td class="diff-line-num">' . $lineNumOld++ . '</td>';
                    $html .= '<td class="diff-line-num">' . $lineNumNew++ . '</td>';
                    $html .= '<td class="diff-line"><span class="diff-marker"> </span>' . $lineContent . '</td>';
                    $html .= '</tr>';
                    break;
            }
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Berechnet die Unterschiede zwischen zwei Arrays von Zeilen.
     *
     * @param array $oldLines Array der Zeilen aus dem alten String.
     * @param array $newLines Array der Zeilen aus dem neuen String.
     * @return array Ein Array, das die Unterschiede darstellt.
     */
    private static function calculate(array $oldLines, array $newLines): array
    {
        $matrix = [];
        $lenOld = count($oldLines);
        $lenNew = count($newLines);

        for ($i = 0; $i <= $lenOld; $i++) {
            $matrix[$i][0] = 0;
        }
        for ($j = 0; $j <= $lenNew; $j++) {
            $matrix[0][$j] = 0;
        }

        for ($i = 1; $i <= $lenOld; $i++) {
            for ($j = 1; $j <= $lenNew; $j++) {
                if ($oldLines[$i - 1] == $newLines[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                } else {
                    $matrix[$i][$j] = max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
                }
            }
        }

        $result = [];
        $i = $lenOld;
        $j = $lenNew;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $oldLines[$i - 1] == $newLines[$j - 1]) {
                $result[] = [$oldLines[$i - 1], 0]; // UNCHANGED
                $i--; $j--;
            } elseif ($j > 0 && ($i == 0 || $matrix[$i][$j - 1] >= $matrix[$i - 1][$j])) {
                $result[] = [$newLines[$j - 1], 1]; // ADDED
                $j--;
            } elseif ($i > 0 && ($j == 0 || $matrix[$i][$j - 1] < $matrix[$i - 1][$j])) {
                $result[] = [$oldLines[$i - 1], -1]; // DELETED
                $i--;
            }
        }
        return array_reverse($result);
    }
}