# Testplan 0.1.19 – Smartphone-Scroll und Funktionsparität

## Ziel
Reine HTML-/CSS-Korrektur. Keine Änderung des Alarmkerns.

## Smartphone
1. Kachel öffnen und alle drei Bereiche einzeln aufklappen.
2. Überwachte Räume vollständig nach unten und oben scrollen.
3. Status Bewegungsmelder vollständig nach unten und oben scrollen.
4. Protokoll/History vollständig scrollen.
5. Während eines Scrollvorgangs dürfen keine Schalter unbeabsichtigt ausgelöst werden.
6. Arm-Schalter, Automatik-Schalter, Scharf-ab-/Unscharf-ab-Zeitfelder und Raum-Schalter bedienen.
7. Alarm auslösen und den Button „Alarm deaktivieren“ bedienen.
8. Bewegungsmelderstatus Ruhe/Bewegung beobachten.

## Desktop-Regression
1. Alle Bereiche auf-/zuklappen.
2. Arm/Automatik/Zeitfelder/Raumschalter bedienen.
3. Alarm quittieren.
4. Protokoll und Bewegungsmelderstatus prüfen.

## Technisch
- `LCNAlarmanlage/module.php` byteidentisch zu 0.1.18.
- `LCNAlarmanlageMediaServer/module.php` byteidentisch zu 0.1.18.
- JavaScript-Block in module.html byteidentisch zu 0.1.18.
- Keine neuen Properties, Variablen oder Timer.
