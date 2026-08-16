# Testplan 0.1.16

## Ziel

0.1.16 enthält vollständig 0.1.15, wird aber direkt als nächstes Update installiert. Zusätzlich abgenommen werden der größere obere Abstand und die neue rein lesende Kategorie `Status Bewegungsmelder`. 0.1.14 bleibt die Rollback-Basis.

## Vorbedingungen

- ZIP `LCN-Alarmanlage_v0.1.14.zip` als Rollback aufbewahren.
- LCN Light Control 0.6.1 unverändert installiert.
- Samsung-Testmodule bis zur vollständigen TV-Abnahme noch installiert lassen.

## T01 – Update / Struktur

1. 0.1.16 installieren und Bibliothek aktualisieren.
2. Erwartung: vorhandene Alarmanlagen-Instanz und sämtliche bisherigen Einstellungen bleiben erhalten.
3. Keine neue sichtbare Symcon-Statusvariable darf allein für `Status Bewegungsmelder` angelegt werden.

## T02 – Visualisierung oben

1. Kachel vollständig neu laden.
2. Erwartung: `Alarmanlage EIN/AUS` liegt deutlich unter der Symcon-Kachelüberschrift und überschneidet sich nicht.
3. `Überwachte Räume`, `Status Bewegungsmelder` und `Protokoll` lassen sich unabhängig auf-/zuklappen.

## T03 – Status Bewegungsmelder

1. `Status Bewegungsmelder` öffnen.
2. Erwartung: alle in der Alarmanlage konfigurierten GUS werden angezeigt.
3. Zusätzlich müssen eindeutig als `Bewegungsmelder`/`GUS` benannte native LCN-Unit-Instanzen mit Boolean-Status erscheinen, auch wenn sie derzeit nicht zur Alarmüberwachung aktiviert sind.
4. Freier/AUS-Melder: grauer Punkt.
5. Bewegung/AN: grüner Punkt.
6. Bewegung beenden: Punkt wird wieder grau.
7. Die Anzeige darf keine Schaltaktion anbieten.

## T04 – Anzeigequelle darf keinen Alarm auslösen

1. Einen automatisch gefundenen Bewegungsmelder verwenden, der nicht als Alarmquelle konfiguriert ist.
2. Dessen Status mehrfach AN/AUS ändern.
3. Erwartung: nur der Punkt ändert sich; keine Alarm-Session, kein Paniklicht, kein TV, keine E-Mail/Push und kein Wieder-scharf-Countdown.

## T05 – normaler Alarm

1. Anlage scharf, überwachten GUS auslösen.
2. Erwartung: Alarmablauf wie 0.1.15; gleichzeitig wird sein Punkt grün.
3. Quittieren über beliebigen LCN-Lichtschalter.
4. Erwartung: Lichtzustände werden auf Vor-Alarm-Zustand zurückgestellt; Video/TV wie bisher; Punkt folgt weiterhin nur dem nativen GUS-Istwert.

## T06 – TV bereits EIN

Alarm auslösen und quittieren. Erwartung: Video startet/stoppt, TV bleibt EIN.

## T07 – TV AUS / WOL

TV ausreichend lange AUS lassen, Alarm auslösen. Erwartung: WOL + Endlosschleife. Quittieren: Video stoppt, vom Alarm gestarteter TV wird sauber ausgeschaltet und nicht später erneut beeinflusst.

## T08 – Neustart scharf, GUS frei

Symcon/SymBox bei scharfer Anlage neu starten. Erwartung: kein Fehlalarm, Startschutz/Sensorabgleich, danach wieder scharf. Die Bewegungsmelder-Statusliste darf dabei keinen zusätzlichen Alarmpfad erzeugen.

## T09 – Neustart mit aktivem GUS

Mit aktivem GUS neu starten. Erwartung: kein historischer Fehlalarm; Anlage wartet fail-safe. Nach Freigabe und einer neuen echten Bewegung muss normal Alarm ausgelöst werden.

## T10 – E-Mail-Quittierung

Optional aktivieren. Mail-Link öffnet nur Bestätigungsseite; erst POST quittiert. Einmal-Token danach ungültig.

## T11 – Testmodule entfernen

Erst nach erfolgreichem TV-Test in 0.1.16 die beiden Samsung-Testmodule und deren alten Test-Server-Socket löschen. `LCN Alarmanlage MediaServer (intern)` und `LCN Alarmanlage Video HTTP` bleiben bestehen. Anschließend WOL/Video nochmals ohne Testmodule testen.

## Rollback

Bei Fehler vollständigen Repository-Inhalt wieder durch 0.1.14 ersetzen und Bibliothek aktualisieren. GUIDs, Prefix und alle bestehenden Properties/Variablen-Idents bleiben kompatibel. Die neue Bewegungsmelder-Statusanzeige erzeugt keine eigenen Statusvariablen, die bei einem Rollback bereinigt werden müssten.
