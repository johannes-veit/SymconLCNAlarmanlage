# Testplan 0.1.10

## Zweck
Reiner Regressionstest des HTML-SDK-Kompatibilitätsfixes gegenüber 0.1.9.

1. Bestehende Instanz von 0.1.9 auf 0.1.10 aktualisieren. Keine neue Instanz anlegen.
2. Alarm-Nachlaufzeit in der Instanzkonfiguration ändern und **Übernehmen**. Erwartung: kein `Type is not supported`, keine Warning.
3. Kachel öffnen. Erwartung: Status, `Überwachte Räume` und `Protokoll` werden korrekt dargestellt.
4. Einen GUS-Schalter in der Kachel AUS/EIN schalten. Erwartung: Aktion funktioniert und Kachel aktualisiert sich.
5. Alarm mit kurzer Nachlaufzeit auslösen. Erwartung: bestehende 0.1.9-Alarm-/TV-/Panik-/Push-/E-Mail-Funktionen unverändert.
6. Bewegung länger als Nachlaufzeit aufrechterhalten. Erwartung: Alarm bleibt aktiv.
7. Alle GUS frei werden lassen. Erwartung: Nachlauf startet erst jetzt; neue Bewegung setzt ihn zurück.
