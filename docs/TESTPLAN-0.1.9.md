# Testplan 0.1.9

## Ziel

Nachweis der neuen Nachlaufsemantik und der kompakten Kachel, ohne Regression in GUS, Panik, Quittierung, Push, E-Mail oder Samsung-TV.

## T01 Anlage AUS

- Anlage AUS.
- Mehrere GUS auslösen.
- Erwartung: kein Alarm, kein Paniklicht, kein TV, keine Benachrichtigung, Anlage bleibt AUS.

## T02 Nachlauf beginnt erst bei freien Meldern

- Nachlauf testweise 20 s.
- Anlage scharf, Alarm auslösen.
- Mindestens einen überwachten GUS durch wiederholte Bewegung aktiv halten.
- Erwartung: auch deutlich länger als 20 s bleibt `ALARM AUSGELÖST`; Panik und TV bleiben an.
- Erst wenn alle GUS frei sind, zeigt die Kachel `Nachlauf noch 20 s` und zählt lokal herunter.

## T03 Neue Bewegung setzt Nachlauf zurück

- Nach T02 alle GUS frei werden lassen.
- Nach etwa 10 s erneut einen überwachten GUS auslösen.
- Erwartung: laufender Nachlauf stoppt sofort; Status zeigt wieder aktive Bewegung.
- GUS erneut frei werden lassen.
- Erwartung: Nachlauf beginnt wieder mit vollen 20 s.

## T04 Automatisches Ende

- Nachlauf ohne neue Bewegung vollständig ablaufen lassen.
- Erwartung: Panik AUS, vom Alarm gestarteter TV AUS, Alarmanlage bleibt EIN.
- Danach startet der separate `Wieder scharf in ...`-Countdown.

## T05 Mehrere GUS

- Zwei oder mehr GUS gleichzeitig und wechselnd auslösen.
- Erwartung: Nachlauf startet erst, wenn **alle aktiv überwachten** GUS frei sind; keine Mehrfach-Panik-/TV-/Push-/Mailstarts.

## T06 Raum-Schalter

- `Überwachte Räume` aufklappen.
- Einen GUS AUS schalten.
- Erwartung: dieser GUS löst nicht aus und hält weder Nachlauf noch Wieder-scharf-Countdown auf.
- Beim Wiedereinschalten eines aktuell aktiven GUS entsteht kein Sofortalarm; erst nach Freiwerden und neuer Bewegung.

## T07 Protokoll/History

- `Protokoll` aufklappen.
- Erstauslöser, letzte Bewegung und Bewegungsanzahl prüfen.
- Historie muss Bewegungen chronologisch mit Raumname, Datum und Uhrzeit zeigen.
- Viele Bewegungen erzeugen Scrollen innerhalb des History-Fensters statt eine immer längere Kachel.

## T08 Quittierung

- Aktiven Alarm über `Alarm deaktivieren` quittieren.
- Separat über einen Panik-Lichttaster quittieren.
- Erwartung: Alarm endet sofort unabhängig von Bewegung; Anlage bleibt EIN und geht nach freien Meldern in den Wieder-scharf-Ablauf.

## T09 Samsung

- TV vorher AUS: Alarm -> TV sofort EIN; automatisches Ende/Quittierung -> TV wieder AUS.
- TV vorher EIN: Alarm und Ende -> TV bleibt EIN.

## T10 Neustart während Nachlauf

- Alarm auslösen, alle GUS frei, Nachlauf starten.
- Symcon/Instanz neu anwenden.
- Erwartung: persistierte Restzeit wird rekonstruiert, kein neuer Alarm, keine neue Push-/Mail-Session.
