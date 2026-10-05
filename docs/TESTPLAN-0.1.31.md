# Testplan 0.1.31 – Personenerkennung Kameras (nur Visu)

## Ziel

Die vier bereits vorhandenen Boolean-Variablen der Kamera-Personenerkennung werden ausschließlich in einer neuen aufklappbaren HTML-Kachel-Rubrik angezeigt. Keine Alarmfunktion darf dadurch verändert werden.

## Konfiguration

1. `Personenerkennung – JV Terrasse` auf die vorhandene Boolean-Variable der Kamera setzen.
2. `Personenerkennung – JV Hof Garage` zuordnen.
3. `Personenerkennung – JV links (Lagerplatz)` zuordnen.
4. `Personenerkennung – JV rechts (Lagerplatz)` zuordnen.
5. Übernehmen.

## Visu-Test

- Rubrik **Personenerkennung Kameras** lässt sich wie die vorhandenen Akkordeons auf-/zuklappen.
- `false` zeigt **Ruhe**.
- `true` zeigt **Person erkannt**.
- Zustandswechsel aktualisiert die Anzeige ohne Bedienaktion.
- Nicht zugeordnete Variable zeigt **Nicht zugeordnet**.
- Entfernte/nicht verfügbare Variable zeigt **Nicht verfügbar**.

## Negativ-/Regressionsprüfung

- Zustandswechsel einer Personenerkennungsvariable darf **keinen Alarm** starten.
- Keine Änderung von `Arm`, `AlarmActive`, PendingMotion, Zwei-Bewegungen-Fenster oder Kamin/Küche-Logik.
- Keine Schaltung von LCN-Licht, Samsung-TV, Dahua-Warnlicht oder Sirene.
- Keine Quittierung und keine Änderung von Alarm-/Rearm-Timern.
- Keine neuen sichtbaren Symcon-Variablen.
- Keine neuen Symcon-Timer.
- Bestehende 0.1.30-Funktionen bleiben unverändert.

## Update-/Rollbackprüfung

- Update 0.1.30 → 0.1.31: bestehende Properties/Attribute/Variablen bleiben erhalten.
- Vier neue Property-Zuordnungen starten mit ID 0 und haben damit bis zur Auswahl keinerlei Wirkung.
- Rollback auf 0.1.30 ignoriert die neuen Property-Werte; Alarmfunktion bleibt kompatibel.
