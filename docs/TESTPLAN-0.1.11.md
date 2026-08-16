# Testplan 0.1.11

## Updatepfad

1. Bestehende Instanz direkt von 0.1.9 auf 0.1.11 aktualisieren.
2. 0.1.10 muss nicht zuvor installiert werden.
3. Keine neue Instanz anlegen.
4. Prüfen, dass GUS-, Panik-, Push-, SMTP- und Samsung-Zuordnungen erhalten bleiben.

## HTML-SDK / RPC-Regression

1. Instanz öffnen und **Übernehmen**: kein `Cannot auto-convert value ... Type is not supported`.
2. Alarm-Nachlaufzeit ändern und **Übernehmen**: kein RPC-Typfehler.
3. Hauptschalter AUS→EIN und EIN→AUS in der HTML-Kachel: kein RPC-Typfehler.
4. Einen GUS unter **Überwachte Räume** AUS→EIN und EIN→AUS schalten: kein RPC-Typfehler.
5. Alarm auslösen, quittieren und Wieder-scharf-Countdown ablaufen lassen: Kachel aktualisiert sich ohne RPC-Typfehler.

## Visuelle Abnahme

1. Keine zweite/eigene Überschrift innerhalb der HTML-Kachel.
2. Schriftbild entspricht den übrigen Kacheln (Poppins/Segoe UI).
3. **Überwachte Räume** ein-/ausklappbar.
4. GUS aktiv: Punkt grün; GUS deaktiviert: Punkt grau.
5. **Protokoll** ein-/ausklappbar.
6. **Historie** chronologisch, bei vielen Einträgen innerhalb des Fensters scrollbar.
7. **Letzter Alarm** zeigt keinen technischen Text `acknowledged`.

## Funktionsregression

1. Anlage AUS: GUS dürfen keinen Alarm auslösen.
2. Anlage EIN: deaktivierter GUS löst nicht aus; aktivierter GUS löst aus.
3. Alarm-Nachlauf beginnt erst, wenn alle aktiv überwachten GUS frei sind.
4. Neue Bewegung während Nachlauf setzt den Nachlauf zurück.
5. Paniklicht EIN/AUS und Quittierung über Lichttaster unverändert.
6. Push und E-Mail pro Session einmal.
7. Samsung-TV AUS→Alarm: sofort WakeUp; Alarmende: vom Alarm gestarteter TV AUS.
8. TV vor Alarm bereits EIN: bleibt nach Alarmende EIN.
9. Wieder-scharf-Countdown unverändert.
