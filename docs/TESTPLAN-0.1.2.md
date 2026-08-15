# Testplan 0.1.2

## Vorbedingungen

- 0.1.1 war real erfolgreich getestet.
- GUS arbeiten spontan als native Boolean-Binäreingänge.
- vorhandene Panik-Lichtgruppe funktioniert manuell zuverlässig.

## T01 Update

- bestehende Instanz bleibt erhalten
- bisherige GUS-Zuordnungen bleiben erhalten
- Hauptschalter/Automatik/Zeiten unverändert
- keine Hardwareaktion allein durch Update im Zustand ohne aktiven Alarm

## T02 Panik EIN

- Anlage scharf
- ein GUS löst aus
- genau eine Alarm-Session
- Panikgruppe wird einmal auf Ziel EIN angefordert
- alle laut Gruppe schaltbaren Paniklichter gehen EIN

## T03 mehrere GUS

- während aktivem Alarm mehrere GUS auslösen
- Bewegungsprofil wächst
- kein zweiter Panikstart

## T04 Quittierung in Symcon

- aktiver Alarm
- „Alarm deaktivieren“
- Alarm endet, Hauptschalter bleibt EIN
- Panikgruppe AUS
- Warte-/Countdown-Logik aus 0.1.1 unverändert

## T05 Quittierung über Licht

- aktiver Alarm, Paniklichter EIN
- ein ausgewähltes Paniklicht per kurzem GT8/GT2-Tastendruck ausschalten
- genau diese EIN->AUS-Flanke quittiert
- übrige Paniklichter werden durch PANIK AUS ebenfalls ausgeschaltet
- Hauptschalter bleibt EIN

## T06 Selbstquittierung ausgeschlossen

- Alarm startet aus komplett ausgeschalteter Panikgruppe
- mehrere Paniklichter wechseln AUS->EIN
- Alarm bleibt aktiv

## T07 automatisches Alarmende

- nicht quittieren
- nach Alarmdauer: AlarmActive AUS, Panikgruppe AUS
- anschließend freie Melder + Countdown + wieder scharf

## T08 Hauptschalter AUS während Alarm

- Alarm aktiv
- Hauptschalter auf AUS
- Alarm endet
- Panikgruppe AUS
- Anlage bleibt AUS, kein Countdown

## T09 Neustart während Alarm

- Alarm aktiv
- Symcon/Instanz neu initialisieren
- Session bleibt aktiv
- Panikgruppe wird deterministisch erneut auf EIN angefordert; bereits eingeschaltete Gruppenmitglieder sollen keinen unnötigen Toggle erhalten
- Alarmtimeout wird mit Restzeit rekonstruiert

## T10 kein Polling

- im Ruhezustand keine zyklischen LCN-Statusanforderungen
- 1-s-Timer ausschließlich während sichtbarem Wieder-Scharf-Countdown
