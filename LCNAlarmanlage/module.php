<?php

declare(strict_types=1);

class LCNAlarmanlage extends IPSModuleStrict
{
    private const SESSION_NONE = '';
    private const SESSION_ACTIVE = 'active';
    private const SESSION_REARM_WAIT = 'rearm_wait';

    private const OVERRIDE_NONE = 0;
    private const OVERRIDE_ON = 1;
    private const OVERRIDE_OFF = 2;

    private const MAX_EVENTS_PER_SESSION = 1000;
    private const SAMSUNG_TIZEN_MODULE_GUID = '{65BF76B4-042C-4971-A5CC-292FA5E49C86}';
    // Exakte Modul-GUID von LCN Light Control 0.6.1 (LCNLight).
    private const LCN_LIGHT_MODULE_GUID = '{331B7F25-09CF-4611-9300-EADA0BB9AFF3}';
    private const SERVER_SOCKET_MODULE_GUID = '{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}';
    private const MEDIA_HELPER_MODULE_GUID = '{0D50907C-F261-4354-A8E3-0D8D12F48D4C}';
    // Native LCN-Struktur wie in LCN Light Control 0.6.1 validiert.
    private const LCN_UNIT_MODULE_GUID = '{2D871359-14D8-493F-9B01-26432E3A710F}';
    private const LCN_MODULE_MODULE_GUID = '{0E31FED6-E465-4621-95D4-AAF2683C41EC}';

    // Nach Kernel-/Modulstart werden die Melder einmalig aktiv synchronisiert.
    // Bis zum Abschluss dieser Schutzphase kann keine neue Alarm-Session entstehen.
    private const STARTUP_SYNC_WAIT_MS = 8000;
    private const STARTUP_SYNC_RETRY_MS = 5000;
    private const STARTUP_SYNC_MAX_ATTEMPTS = 2;
    private const ACK_TOKEN_LIFETIME_SECONDS = 86400;

    // Exakt aus dem real getesteten Samsung Alarmvideo Test 0.2.6 übernommen.
    private const AVT_SERVICE = 'urn:schemas-upnp-org:service:AVTransport:1';
    private const CM_SERVICE = 'urn:schemas-upnp-org:service:ConnectionManager:1';
    private const MEDIA_TOKEN = 'F0F30B1D-B2BC-4657-8E63-D8E46E1E425F';
    private const FORMAT_ID_MPEG = '00000061-A9AF-4584-84E2-55BFEF0A7D7E';
    private const FORMAT_ID_MP4 = '00000041-A9AF-4584-84E2-55BFEF0A7D7E';
    private const MPEG_FEATURES = 'DLNA.ORG_PN=AVC_TS_MP_HD_AAC_MULT5_ISO;DLNA.ORG_OP=10;DLNA.ORG_CI=1;DLNA.ORG_FLAGS=01700000000000000000000000000000';
    private const MP4_FEATURES = 'DLNA.ORG_PN=AVC_MP4_HP_HD_AAC;DLNA.ORG_OP=01;DLNA.ORG_CI=0;DLNA.ORG_FLAGS=01700000000000000000000000000000';
    private const MPEG_DURATION = 60.053;
    private const MP4_DURATION = 60.010;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Sensors', '[]');
        $this->RegisterPropertyInteger('AlarmDurationSeconds', 300);
        $this->RegisterPropertyInteger('RearmDelaySeconds', 60);
        $this->RegisterPropertyInteger('PanicGroupVariableID', 0);
        $this->RegisterPropertyString('AcknowledgeLights', '[]');
        // Neue externe Benachrichtigungen sind nach einem Update bewusst AUS, bis
        // die zugehörigen Symcon-Instanzen geprüft und explizit aktiviert wurden.
        $this->RegisterPropertyBoolean('PushEnabled', false);
        $this->RegisterPropertyInteger('PushVisualizationID', 0);
        $this->RegisterPropertyBoolean('EmailEnabled', false);
        $this->RegisterPropertyInteger('SMTPInstanceID', 0);
        $this->RegisterPropertyString('EmailRecipients', '');
        // Optionaler sicherer Quittierungslink in der Alarmmail. Aus Rollback- und
        // Sicherheitsgründen nach dem Update zunächst AUS. Die Basis-URL ist z. B.
        // die HTTPS-Adresse von Symcon Connect ohne abschließenden /hook-Pfad.
        $this->RegisterPropertyBoolean('EmailAcknowledgeEnabled', false);
        $this->RegisterPropertyString('EmailAcknowledgeBaseURL', '');
        // Samsung-TV ist optional und nach einem Update bewusst AUS. Die Steuerung
        // erfolgt direkt ueber die bereits funktionierenden SamsungTizen-Funktionen,
        // niemals ueber den Alarmkern oder die PowerFix-Impulsvariable.
        $this->RegisterPropertyBoolean('TVEnabled', false);
        $this->RegisterPropertyInteger('TVInstanceID', 0);
        $this->RegisterPropertyInteger('TVStatusVariableID', 0);
        // Neue Video-Parameter entsprechen exakt dem erfolgreich getesteten Testmodul 0.2.6.
        // Die IP-Adressen sind die beim Nutzer real getesteten Werte; sie bleiben im
        // Instanzformular jederzeit änderbar.
        $this->RegisterPropertyString('TVIP', '192.168.103.54');
        $this->RegisterPropertyString('SymconIP', '192.168.103.59');
        $this->RegisterPropertyInteger('MediaServerPort', 8090);
        $this->RegisterPropertyInteger('VideoStartDelayMs', 4000);

        $this->RegisterAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
        $this->RegisterAttributeBoolean('ArmedReady', false);
        $this->RegisterAttributeString('CurrentSession', '{}');
        $this->RegisterAttributeString('LastSession', '{}');
        $this->RegisterAttributeInteger('SessionCounter', 0);
        $this->RegisterAttributeInteger('RearmNotBefore', 0);
        // Persistente Nachlauf-Deadline: startet erst, wenn alle aktuell überwachten
        // GUS frei sind. Jede neue Bewegung verwirft die Deadline vollständig.
        $this->RegisterAttributeInteger('AlarmQuietNotBefore', 0);
        $this->RegisterAttributeString('RegisteredSensorIDs', '[]');
        $this->RegisterAttributeString('RegisteredWatchSensorIDs', '[]');
        // 0.1.16: nur technische Registrierungen fuer die zusaetzliche reine
        // Bewegungsmelder-Statusanzeige. Es werden dafuer KEINE Symcon-Variablen erzeugt.
        $this->RegisterAttributeString('RegisteredMotionStatusIDs', '[]');
        $this->RegisterAttributeString('RegisteredAcknowledgeIDs', '[]');
        $this->RegisterAttributeInteger('RegisteredPanicVariableID', 0);

        // E-Mail-Quittierung: nur Hash und Sessionbindung werden persistent gespeichert.
        // Der Klartext-Token steht ausschließlich im einmalig versendeten Link.
        $this->RegisterAttributeString('EmailAckTokenHash', '');
        $this->RegisterAttributeString('EmailAckSessionID', '');
        $this->RegisterAttributeInteger('EmailAckExpiresAt', 0);

        // TV-Laufzeitstatus ist strikt vom Alarmzustand getrennt. Er dient nur dazu,
        // einen vom Alarm gestarteten TV spaeter wieder sicher auszuschalten.
        $this->RegisterAttributeBoolean('TVOwnedByAlarm', false);
        $this->RegisterAttributeString('TVOwnerSessionID', '');
        $this->RegisterAttributeInteger('TVWakeAttempts', 0);
        $this->RegisterAttributeInteger('TVLastWakeAt', 0);
        // Wird nur gesetzt, wenn der TV waehrend der aktuellen Alarm-Session
        // tatsaechlich als EIN bestaetigt wurde. Verhindert spaete AUS-Befehle
        // gegen einen manuell eingeschalteten TV nach bereits beendetem Alarm.
        $this->RegisterAttributeBoolean('TVSeenOnDuringAlarm', false);
        $this->RegisterAttributeInteger('TVOffDeadline', 0);
        $this->RegisterAttributeInteger('TVOffFalseChecks', 0);

        // Persistenter technischer Zustand des integrierten Alarmvideo-Pfads.
        $this->RegisterAttributeInteger('MediaHelperID', 0);
        $this->RegisterAttributeInteger('MediaServerID', 0);
        $this->RegisterAttributeInteger('MediaServerPortActive', 0);
        $this->RegisterAttributeString('LastMediaServerError', '');
        $this->RegisterAttributeInteger('VideoActive', 0);
        $this->RegisterAttributeString('VideoSessionID', '');
        $this->RegisterAttributeInteger('VideoAttempts', 0);
        $this->RegisterAttributeString('LastMediaMode', '');
        $this->RegisterAttributeInteger('VideoStatsWaitTicks', 0);
        $this->RegisterAttributeInteger('VideoLoopFallbackPending', 0);
        $this->RegisterAttributeInteger('VideoLoopRearmCount', 0);
        $this->RegisterAttributeInteger('VideoLoopRearmFailures', 0);

        $created = $this->RegisterVariableBoolean(
            'Arm',
            'Alarmanlage EIN/AUS',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            10
        );
        if ($created) {
            $this->SetValue('Arm', false);
        }
        $this->EnableAction('Arm');

        $created = $this->RegisterVariableString(
            'Status',
            'Status',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            20
        );
        if ($created) {
            $this->SetValue('Status', 'ALARMANLAGE AUS');
        }

        $created = $this->RegisterVariableBoolean(
            'Automatic',
            'Automatik',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            30
        );
        if ($created) {
            // Sicherheitsvorgabe: niemals nach Erstinstallation automatisch scharf werden.
            $this->SetValue('Automatic', false);
        }
        $this->EnableAction('Automatic');

        $created = $this->RegisterVariableString(
            'AutoFrom',
            'Scharf ab',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT],
            40
        );
        if ($created) {
            $this->SetValue('AutoFrom', '22:00');
        }
        $this->EnableAction('AutoFrom');

        $created = $this->RegisterVariableString(
            'AutoTo',
            'Unscharf ab',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT],
            50
        );
        if ($created) {
            $this->SetValue('AutoTo', '06:00');
        }
        $this->EnableAction('AutoTo');

        $created = $this->RegisterVariableBoolean(
            'AlarmActive',
            'ALARM AUSGELÖST',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            60
        );
        if ($created) {
            $this->SetValue('AlarmActive', false);
        }

        $created = $this->RegisterVariableBoolean(
            'Acknowledge',
            'Alarm deaktivieren',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            70
        );
        if ($created) {
            $this->SetValue('Acknowledge', false);
        }
        $this->EnableAction('Acknowledge');

        $created = $this->RegisterVariableString(
            'FirstTrigger',
            'Erstauslöser',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            80
        );
        if ($created) {
            $this->SetValue('FirstTrigger', '-');
        }

        $created = $this->RegisterVariableString(
            'LastMovement',
            'Letzte Bewegung',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            90
        );
        if ($created) {
            $this->SetValue('LastMovement', '-');
        }

        $created = $this->RegisterVariableInteger(
            'MotionCount',
            'Bewegungen',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            100
        );
        if ($created) {
            $this->SetValue('MotionCount', 0);
        }

        $created = $this->RegisterVariableString(
            'MotionLog',
            'Bewegungsprofil',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            110
        );
        if ($created) {
            $this->SetValue('MotionLog', '-');
        }

        $created = $this->RegisterVariableString(
            'LastAlarm',
            'Letzter Alarm',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            120
        );
        if ($created) {
            $this->SetValue('LastAlarm', '-');
        }

        $this->RegisterTimer('AlarmTimeout', 0, 'LCNALARM_AlarmTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('RearmTimeout', 0, 'LCNALARM_RearmTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('RearmDisplay', 0, 'LCNALARM_RearmDisplay($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ScheduleTimer', 0, 'LCNALARM_ScheduleTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PanicQueue', 0, 'LCNALARM_PanicQueue($_IPS[\'TARGET\']);');
        $this->RegisterTimer('NotificationQueue', 0, 'LCNALARM_NotificationQueue($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVWakeRetry', 0, 'LCNALARM_TVWakeRetry($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVOffCheck', 0, 'LCNALARM_TVOffCheck($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVVideoStart', 0, 'LCNALARM_TVVideoStart($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVVideoRetry', 0, 'LCNALARM_TVVideoRetry($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVVideoLoopGuard', 0, 'LCNALARM_TVVideoLoopGuard($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TVVideoStatsSync', 0, 'LCNALARM_TVVideoStatsSync($_IPS[\'TARGET\']);');
        $this->RegisterTimer('StartupGuard', 0, 'LCNALARM_StartupGuard($_IPS[\'TARGET\']);');

        // Sicherer E-Mail-Quittierungsweg. Der GET-Aufruf zeigt ausschließlich eine
        // Bestätigungsseite; erst ein POST darf die aktuelle Alarm-Session quittieren.
        $this->RegisterHook('/hook/lcnalarm/' . $this->InstanceID);

        // Kompakte, interaktive Kachel via offiziellem HTML-SDK. Die nativen
        // Statusvariablen bleiben als Fallback/Listenansicht vollständig erhalten.
        $this->SetVisualizationType(1);

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Interne Timer sind stateless und werden nach jedem ApplyChanges bewusst neu aufgebaut.
        $this->SetTimerInterval('AlarmTimeout', 0);
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('RearmDisplay', 0);
        $this->SetTimerInterval('ScheduleTimer', 0);
        $this->SetTimerInterval('PanicQueue', 0);
        $this->SetTimerInterval('NotificationQueue', 0);
        $this->SetTimerInterval('TVWakeRetry', 0);
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->SetTimerInterval('StartupGuard', 0);
        $this->StopAllTVVideoTimers();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->SetValue('Status', 'INITIALISIERUNG');
            return;
        }

        $this->InitializeRuntime();
    }

    /**
     * Dynamisches Konfigurationsformular: Push wird auf echte VISU-Module und
     * Samsung auf die tatsächlich installierte SamsungTizen-Modulklasse begrenzt.
     */
    public function GetConfigurationForm(): string
    {
        $path = __DIR__ . '/form.json';
        $json = @file_get_contents($path);
        $form = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($form)) {
            return '{"elements":[{"type":"Label","label":"Konfigurationsformular konnte nicht geladen werden."}]}';
        }

        $visualizationModules = [];
        try {
            foreach (IPS_GetModuleListByType(6) as $moduleID) {
                $module = IPS_GetModule((string) $moduleID);
                if (strtoupper((string) ($module['Prefix'] ?? '')) === 'VISU') {
                    $visualizationModules[] = (string) $moduleID;
                }
            }
        } catch (Throwable $e) {
            $this->SendDebug('ConfigurationForm', 'Kachelvisualisierungen konnten nicht gefiltert werden: ' . $e->getMessage(), 0);
        }

        foreach ($form['elements'] as &$element) {
            if (!is_array($element)) {
                continue;
            }
            if (($element['name'] ?? '') === 'PushVisualizationID' && $visualizationModules !== []) {
                $element['validModules'] = array_values(array_unique($visualizationModules));
            }
            if (($element['name'] ?? '') === 'TVInstanceID') {
                $element['validModules'] = [self::SAMSUNG_TIZEN_MODULE_GUID];
            }
        }
        unset($element);

        return json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function GetVisualizationTile(): string
    {
        $path = __DIR__ . '/module.html';
        $html = @file_get_contents($path);
        if (!is_string($html)) {
            return '<div style="padding:12px">Visualisierung konnte nicht geladen werden.</div>';
        }

        $state = $this->BuildVisualizationState();
        $json = json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($json === false) {
            $json = '{}';
        }

        return str_replace('__INITIAL_STATE__', $json, $html);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->InitializeRuntime();
            return;
        }

        if ($Message === OM_UNREGISTER) {
            if ($this->IsSensorVariable($SenderID)) {
                $this->HandleSensorUnavailable($SenderID);
            } elseif ($this->IsMotionStatusVariable($SenderID)) {
                // Reine Anzeigequelle: niemals Alarm, Scharfzustand oder Session veraendern.
                $this->PushVisualizationState();
            } elseif ($this->IsAcknowledgeVariable($SenderID) || $SenderID === $this->PanicGroupVariableID()) {
                $this->HandleAuxiliaryUnavailable($SenderID);
            }
            return;
        }

        if ($Message !== VM_UPDATE) {
            return;
        }

        // $Data wird absichtlich nicht ausgewertet. Der aktuelle Zustand wird dokumentiert
        // direkt über die SenderID aus der Variable gelesen.
        if ($this->IsSensorVariable($SenderID)) {
            $this->ProcessSensorUpdate($SenderID);
        } elseif ($this->IsMotionStatusVariable($SenderID)) {
            // Nicht ueberwachte/automatisch gefundene GUS aktualisieren ausschliesslich
            // ihren Punkt in der Visualisierung. Keine Alarm- oder Startschutzlogik.
            $this->PushVisualizationState();
        }
        if ($this->IsAcknowledgeVariable($SenderID)) {
            $this->ProcessAcknowledgeLightUpdate($SenderID);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if (str_starts_with($Ident, 'WatchSensor')) {
            $variableID = (int) substr($Ident, strlen('WatchSensor'));
            $this->SetSensorWatchEnabled($variableID, (bool) $Value);
            return;
        }

        switch ($Ident) {
            case 'Arm':
                $armed = (bool) $Value;
                $this->WriteAttributeInteger('ManualOverride', $armed ? self::OVERRIDE_ON : self::OVERRIDE_OFF);
                $this->SetArmedInternal($armed, 'manual');
                $this->ScheduleNextBoundary();
                break;

            case 'Automatic':
                $automatic = (bool) $Value;
                $this->SetValue('Automatic', $automatic);
                $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
                if ($automatic) {
                    $this->ApplyCurrentSchedule('automatic-enabled');
                }
                $this->ScheduleNextBoundary();
                break;

            case 'AutoFrom':
            case 'AutoTo':
                $time = trim((string) $Value);
                if (!$this->IsValidTime($time)) {
                    throw new Exception('Uhrzeit muss im Format HH:MM eingegeben werden.');
                }
                $this->SetValue($Ident, $time);
                $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
                if ((bool) $this->GetValue('Automatic')) {
                    $this->ApplyCurrentSchedule('time-changed');
                }
                $this->ScheduleNextBoundary();
                break;

            case 'Acknowledge':
                if ((bool) $Value) {
                    $this->AcknowledgeAlarm();
                }
                $this->SetValue('Acknowledge', false);
                break;

            default:
                throw new Exception('Ungültige Aktion: ' . $Ident);
        }

        // Wichtig insbesondere für reine Eingabewerte (Scharf/Unscharf ab):
        // die HTML-SDK-Kachel erhält den bestätigten Modulzustand zurück.
        $this->PushVisualizationState();
    }

    /** Externe, dokumentierte Bedienfunktion für spätere Quittierungswege. */
    public function SetArmed(bool $Armed): void
    {
        $this->WriteAttributeInteger('ManualOverride', $Armed ? self::OVERRIDE_ON : self::OVERRIDE_OFF);
        $this->SetArmedInternal($Armed, 'external');
        $this->ScheduleNextBoundary();
    }

    /**
     * Zentraler Quittierungsweg. Eine Quittierung beendet ausschließlich die
     * aktuelle Alarm-Session. Die Alarmanlage selbst bleibt scharfgeschaltet.
     * Spätere GT8/GT2/Push/E-Mail-Quittierungen rufen denselben Pfad auf.
     */
    public function AcknowledgeAlarm(): void
    {
        $this->AcknowledgeAlarmInternal('symcon');
    }

    /**
     * WebHook für die optionale E-Mail-Quittierung.
     * GET ist absichtlich rein lesend und zeigt nur die Bestätigungsseite.
     * Erst POST darf die aktuelle, tokengebundene Alarm-Session quittieren.
     */
    public function ProcessHookData(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

        if (!$this->ReadPropertyBoolean('EmailAcknowledgeEnabled')) {
            http_response_code(404);
            echo $this->RenderEmailAckPage('Nicht verfügbar', 'Die E-Mail-Quittierung ist für diese Alarmanlage nicht aktiviert.', '', '', false);
            return;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $sessionID = trim((string) (($method === 'POST' ? ($_POST['session'] ?? '') : ($_GET['session'] ?? ''))));
        $token = trim((string) (($method === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''))));

        $validation = $this->ValidateEmailAckToken($sessionID, $token);
        if (!(bool) ($validation['ok'] ?? false)) {
            http_response_code((int) ($validation['status'] ?? 410));
            echo $this->RenderEmailAckPage(
                'Link nicht mehr gültig',
                (string) ($validation['message'] ?? 'Dieser Quittierungslink ist ungültig oder bereits verbraucht.'),
                '',
                '',
                false
            );
            return;
        }

        if ($method !== 'POST') {
            $session = $this->ReadSession('CurrentSession');
            $sensor = (string) ($session['firstSensorName'] ?? '-');
            $startedAt = (float) ($session['startedAt'] ?? 0);
            $message = 'Aktiver Alarm'
                . ($sensor !== '' ? ' · ' . $sensor : '')
                . ($startedAt > 0 ? ' · ' . $this->FormatTimestamp($startedAt) : '')
                . '. Erst die folgende Bestätigung beendet die aktuelle Alarm-Session.';
            echo $this->RenderEmailAckPage('Alarm quittieren?', $message, $sessionID, $token, true);
            return;
        }

        try {
            $this->AcknowledgeAlarmInternal('email-link');
            $this->InvalidateEmailAckToken($sessionID);
            echo $this->RenderEmailAckPage(
                'Alarm quittiert',
                'Die aktuelle Alarm-Session wurde beendet. Die Alarmanlage bleibt eingeschaltet und wird nach freien Meldern und der eingestellten Verzögerung wieder scharf.',
                '',
                '',
                false
            );
        } catch (Throwable $e) {
            http_response_code(503);
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'E-Mail-Quittierung fehlgeschlagen: ' . $e->getMessage());
            echo $this->RenderEmailAckPage(
                'Quittierung nicht ausgeführt',
                'Die Quittierung konnte momentan nicht sicher ausgeführt werden. Bitte erneut über die Symcon-App oder einen Lichtschalter quittieren.',
                '',
                '',
                false
            );
        }
    }

    /**
     * Nachlauf-Timer der aktiven Alarm-Session. Er darf erst ablaufen, nachdem alle
     * aktuell überwachten GUS frei sind. Jede neue Bewegung hebt die Deadline auf.
     */
    public function AlarmTimeout(): void
    {
        $this->SetTimerInterval('AlarmTimeout', 0);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('AlarmTimeout');
            return;
        }

        $startRearmTimer = false;
        $endedSessionID = '';
        $rescheduleMs = 0;
        try {
            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) !== self::SESSION_ACTIVE) {
                $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                return;
            }

            $states = $this->ReadSensorStates();
            if (!$this->AreAllMonitoredSensorsClear($states)) {
                // Sicherheitsnetz gegen Timer-/Sensor-Rennen: bei Bewegung darf ein
                // abgelaufener Nachlauf niemals die aktive Signalisierung beenden.
                $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                return;
            }

            $deadline = $this->ReadAttributeInteger('AlarmQuietNotBefore');
            if ($deadline <= 0) {
                $deadline = time() + max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                $this->WriteAttributeInteger('AlarmQuietNotBefore', $deadline);
            }

            $remaining = $deadline - time();
            if ($remaining > 0) {
                // Timer können systembedingt minimal zu früh eintreffen.
                $rescheduleMs = max(1, $remaining * 1000);
            } else {
                $endedSessionID = (string) ($session['id'] ?? '');
                $session['state'] = self::SESSION_REARM_WAIT;
                $session['signalEndedAt'] = microtime(true);
                $session['pendingEndReason'] = 'automatic-afterrun';
                $this->WriteSession('CurrentSession', $session);
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);

                $delay = max(0, $this->ReadPropertyInteger('RearmDelaySeconds'));
                $notBefore = time() + $delay;
                $this->WriteAttributeInteger('RearmNotBefore', $notBefore);
                $startRearmTimer = true;
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($rescheduleMs > 0) {
            $this->SetTimerInterval('AlarmTimeout', $rescheduleMs);
            $this->RefreshDisplay();
            return;
        }

        if ($endedSessionID === '') {
            $this->RefreshDisplay();
            return;
        }

        $this->SetValue('AlarmActive', false);
        $this->SetAlarmControlsVisible(false);
        $this->SetTimerInterval('RearmDisplay', 0);

        $this->InvalidateEmailAckToken($endedSessionID);
        $this->SetPanicForSession($endedSessionID, false, 'automatic-afterrun');
        $this->EndTVForAlarm($endedSessionID, 'automatic-afterrun');

        if ($startRearmTimer) {
            $this->ScheduleRearmFromAttribute();
        }

        $this->RefreshDisplay();
    }

    /** Einmal-Timer: Anlage nach Timeout erst nach freiem Melderfeld wieder bereit setzen. */
    public function RearmTimeout(): void
    {
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('RearmDisplay', 0);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('RearmTimeout');
            return;
        }

        $rearmed = false;
        $reschedule = false;
        try {
            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) === self::SESSION_REARM_WAIT) {
                $states = $this->ReadSensorStates();

                if (!$this->AreAllMonitoredSensorsClear($states)) {
                    // Ein aktiver Melder hebt die laufende Ruhezeit auf. Die nächste
                    // CLEAR-Flanke aller Melder startet sie erneut.
                    $this->WriteAttributeInteger('RearmNotBefore', 0);
                } else {
                    $notBefore = $this->ReadAttributeInteger('RearmNotBefore');
                    if ($notBefore <= 0) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                        $reschedule = true;
                    } elseif ($notBefore > time()) {
                        // Timer können systembedingt minimal zu früh aufgerufen werden.
                        // In diesem Fall wird der verbleibende Einmal-Timer neu gesetzt.
                        $reschedule = true;
                    } else {
                        $session['endedAt'] = microtime(true);
                        $session['endReason'] = (string) ($session['pendingEndReason'] ?? 'automatic-timeout');
                        $session['state'] = 'ended';
                        $this->WriteSession('LastSession', $session);
                        $this->WriteSession('CurrentSession', []);
                        $this->WriteAttributeInteger('RearmNotBefore', 0);

                        $armed = (bool) $this->GetValue('Arm');
                        $ready = $armed && $this->CountMonitoredSensors() > 0 && $this->AreAllMonitoredSensorsClear($states);
                        $this->WriteAttributeBoolean('ArmedReady', $ready);
                        $rearmed = $ready;
                    }
                }
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($reschedule) {
            $this->ScheduleRearmFromAttribute();
        }

        if ($rearmed) {
            $this->SetValue('AlarmActive', false);
        }

        $this->RefreshDisplay();
    }

    /**
     * Reiner Visualisierungs-Timer während der kurzen Wieder-Scharf-Verzögerung.
     * Er erzeugt keinerlei LCN-Verkehr und verändert keine Alarmzustände.
     */
    public function RearmDisplay(): void
    {
        $session = $this->ReadSession('CurrentSession');
        if (($session['state'] ?? self::SESSION_NONE) !== self::SESSION_REARM_WAIT) {
            $this->SetTimerInterval('RearmDisplay', 0);
            return;
        }

        $states = $this->ReadSensorStates();
        if (!$this->AreAllMonitoredSensorsClear($states) || $this->ReadAttributeInteger('RearmNotBefore') <= 0) {
            $this->SetTimerInterval('RearmDisplay', 0);
        }

        $this->RefreshDisplay();
    }

    /**
     * Kurzlebiger 100-ms-Worker fuer die Paniklichter. Er ist nur aktiv, solange
     * tatsaechlich einzelne Lichtbefehle abzuarbeiten sind. Es gibt kein Polling.
     */
    public function PanicQueue(): void
    {
        $this->SetTimerInterval('PanicQueue', 0);

        $queue = json_decode($this->GetBuffer('PanicQueue'), true);
        if (!is_array($queue) || $queue === []) {
            $this->SetBuffer('PanicQueue', '[]');
            return;
        }

        $item = array_shift($queue);
        $this->SetBuffer('PanicQueue', $this->Encode($queue));

        if (!is_array($item)) {
            if ($queue !== []) {
                $this->SetTimerInterval('PanicQueue', 100);
            }
            return;
        }

        $variableID = (int) ($item['id'] ?? 0);
        $instanceID = (int) ($item['instanceID'] ?? 0);
        $target = (bool) ($item['target'] ?? false);
        $mode = (string) ($item['mode'] ?? 'panic-on');
        $sessionID = (string) ($item['sessionID'] ?? '');
        $reason = (string) ($item['reason'] ?? 'panic');

        if ($mode === 'panic-on') {
            // Kein verspätetes PANIK EIN nach Quittierung oder Alarmende.
            if ($sessionID === '' || !$this->IsActiveSession($sessionID)) {
                $this->SetBuffer('PanicQueue', '[]');
                return;
            }
        } elseif ($mode === 'restore') {
            // Eine alte Wiederherstellung darf niemals in eine bereits neue aktive
            // Alarm-Session hineinlaufen.
            if ($this->HasAnyActiveSession()) {
                $this->SetBuffer('PanicQueue', '[]');
                return;
            }
        } else {
            $this->SetBuffer('PanicQueue', '[]');
            return;
        }

        if ($variableID > 0 && $instanceID > 0 && IPS_VariableExists($variableID) && IPS_InstanceExists($instanceID)) {
            try {
                $instance = IPS_GetInstance($instanceID);
                if (strtoupper((string) ($instance['ModuleInfo']['ModuleID'] ?? '')) !== strtoupper(self::LCN_LIGHT_MODULE_GUID)) {
                    throw new Exception('Zielinstanz ist keine LCNLight-Instanz');
                }

                $current = $this->ReadLCNLightState($instanceID, $variableID);
                if ($current === null) {
                    throw new Exception('LCN-Light-Istzustand unbekannt; kein blinder Toggle');
                }

                if ($current !== $target) {
                    $ok = (bool) LCL_SetPower($instanceID, $target);
                    if (!$ok) {
                        throw new Exception('LCL_SetPower meldete FALSE');
                    }
                }
            } catch (Throwable $e) {
                IPS_LogMessage(
                    'LCN Alarmanlage #' . $this->InstanceID,
                    'Licht #' . $variableID . ' -> ' . ($target ? 'EIN' : 'AUS') .
                    ' fehlgeschlagen (' . $reason . '): ' . $e->getMessage()
                );
                $this->SendDebug('Panic', 'Variable #' . $variableID . ': ' . $e->getMessage(), 0);
            }
        }

        $queue = json_decode($this->GetBuffer('PanicQueue'), true);
        if (is_array($queue) && $queue !== []) {
            $this->SetTimerInterval('PanicQueue', 100);
        }
    }

    /**
     * Kurzlebiger Worker für Push/E-Mail. Die Alarm-Engine und Panikbeleuchtung
     * sind zu diesem Zeitpunkt bereits gesetzt; langsames SMTP blockiert daher
     * niemals den sicherheitskritischen Zustandswechsel unter der Semaphore.
     */
    public function NotificationQueue(): void
    {
        $this->SetTimerInterval('NotificationQueue', 0);

        $queue = json_decode($this->GetBuffer('NotificationQueue'), true);
        if (!is_array($queue) || $queue === []) {
            $this->SetBuffer('NotificationQueue', '[]');
            return;
        }

        $item = array_shift($queue);
        $this->SetBuffer('NotificationQueue', $this->Encode($queue));

        if (is_array($item)) {
            $type = (string) ($item['type'] ?? '');
            $sessionID = (string) ($item['sessionID'] ?? '');
            $ok = false;
            $error = '';
            try {
                if ($type === 'push') {
                    $visualizationID = (int) ($item['visualizationID'] ?? 0);
                    if ($visualizationID <= 0 || !IPS_InstanceExists($visualizationID)) {
                        throw new Exception('Kachelvisualisierung nicht verfügbar');
                    }
                    $result = VISU_PostNotificationEx(
                        $visualizationID,
                        (string) ($item['title'] ?? 'ALARM AUSGELÖST!'),
                        (string) ($item['text'] ?? ''),
                        'Alert',
                        'siren',
                        $this->InstanceID
                    );
                    if ($result === false) {
                        throw new Exception('VISU_PostNotificationEx meldete FALSE');
                    }
                    $ok = true;
                } elseif ($type === 'email') {
                    $smtpID = (int) ($item['smtpID'] ?? 0);
                    $recipient = trim((string) ($item['recipient'] ?? ''));
                    if ($smtpID <= 0 || !IPS_InstanceExists($smtpID)) {
                        throw new Exception('SMTP-Instanz nicht verfügbar');
                    }
                    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('ungültige Empfängeradresse');
                    }
                    $ok = SMTP_SendMailEx(
                        $smtpID,
                        $recipient,
                        (string) ($item['subject'] ?? 'ALARM ausgelöst!'),
                        (string) ($item['body'] ?? '')
                    );
                    if (!$ok) {
                        throw new Exception('SMTP_SendMailEx meldete FALSE');
                    }
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
                IPS_LogMessage(
                    'LCN Alarmanlage #' . $this->InstanceID,
                    'Benachrichtigung ' . $type . ' fehlgeschlagen: ' . $error
                );
                $this->SendDebug('Notification', $type . ': ' . $error, 0);
            }

            $this->RecordNotificationResult($sessionID, $item, $ok, $error);
        }

        $queue = json_decode($this->GetBuffer('NotificationQueue'), true);
        if (is_array($queue) && $queue !== []) {
            // Kleine lokale Entkopplung; erzeugt keinerlei LCN-Verkehr.
            $this->SetTimerInterval('NotificationQueue', 100);
        }
    }

    /**
     * Einmaliger, begrenzter Wake-Retry. Der erste WakeUp wird direkt beim
     * Alarmstart gesendet. Nur wenn der lokale TV-Status nach 5 s weiterhin AUS
     * meldet, wird genau ein zweiter WakeUp gesendet. Keine Endlosschleife.
     */
    public function TVWakeRetry(): void
    {
        $this->SetTimerInterval('TVWakeRetry', 0);

        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false)) {
            return;
        }

        $ownerSessionID = $this->ReadAttributeString('TVOwnerSessionID');
        if ($ownerSessionID === '' || !$this->ReadAttributeBoolean('TVOwnedByAlarm')) {
            return;
        }

        // Eine beendete oder inzwischen ersetzte Session darf keinen spaeten WOL
        // mehr senden.
        if (!$this->IsActiveSession($ownerSessionID)) {
            return;
        }

        if ($this->TVIsOn($config)) {
            $this->WriteAttributeBoolean('TVSeenOnDuringAlarm', true);
            return;
        }

        $attempts = $this->ReadAttributeInteger('TVWakeAttempts');
        if ($attempts >= 2) {
            return;
        }

        if ($this->SendTVWakeUp($config, 'retry-5s')) {
            $this->WriteAttributeInteger('TVWakeAttempts', $attempts + 1);
            $this->WriteAttributeInteger('TVLastWakeAt', time());
        }
    }

    /**
     * Nachlaufkontrolle nach Alarmende. Sie liest nur die vorhandene lokale
     * Statusvariable. Ein TV, der vom Alarm gestartet wurde, wird bei Bedarf mit
     * KEY_POWER ausgeschaltet und nach 10 s erneut kontrolliert. Dieser Helfer
     * veraendert niemals Arm/AlarmActive/Session/Countdown.
     */
    public function TVOffCheck(): void
    {
        $this->SetTimerInterval('TVOffCheck', 0);

        // v0.1.13: Dieser Timer ist nur noch eine EINMALIGE Nachkontrolle.
        // Er darf niemals selbst einen weiteren KEY_POWER-Befehl senden.
        // Hintergrund: In v0.1.11/0.1.12 konnte die 60-s-Nachkontrolle einen
        // verspätet gestarteten oder inzwischen manuell eingeschalteten TV erneut
        // ausschalten. Dadurch sah ein korrekt gesendetes WOL wie ein WOL-Fehler aus.
        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false) || !$this->ReadAttributeBoolean('TVOwnedByAlarm')) {
            $this->ClearTVOwnership();
            return;
        }

        // Ein neuer aktiver Alarm hat Vorrang; StartTVForAlarm uebernimmt dann
        // ohnehin die Ownership und beendet den alten Abschaltauftrag.
        $current = $this->ReadSession('CurrentSession');
        if (($current['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE) {
            return;
        }

        $isOn = $this->TVIsOn($config);
        $this->SendDebug(
            'TV',
            $isOn
                ? '10-s-Nachkontrolle: TV meldet noch EIN – kein weiterer AUS-Befehl'
                : '10-s-Nachkontrolle: TV ist AUS',
            0
        );

        // Unabhaengig vom Ergebnis Ownership jetzt beenden. Es gibt bewusst
        // keinen zweiten/periodischen KEY_POWER-Befehl mehr.
        $this->ClearTVOwnership();
    }

    /** Startet den integrierten v0.2.6-Videoablauf nach der konfigurierten Verzögerung. */
    public function TVVideoStart(): void
    {
        $this->SetTimerInterval('TVVideoStart', 0);
        $sessionID = $this->ReadAttributeString('VideoSessionID');
        if ($sessionID === '' || !$this->IsActiveSession($sessionID)) {
            $this->ResetTVVideoRuntime();
            return;
        }
        if ($this->ReadAttributeString('TVOwnerSessionID') !== $sessionID) {
            $this->ResetTVVideoRuntime();
            return;
        }
        $this->StartAlarmVideoNowInternal($sessionID, false);
    }

    /** Begrenzter Videostart-Retry: maximal drei Versuche wie im getesteten 0.2.6. */
    public function TVVideoRetry(): void
    {
        $this->SetTimerInterval('TVVideoRetry', 0);
        if ($this->ReadAttributeInteger('VideoActive') === 1) {
            return;
        }

        $sessionID = $this->ReadAttributeString('VideoSessionID');
        if ($sessionID === '' || !$this->IsActiveSession($sessionID) || $this->ReadAttributeString('TVOwnerSessionID') !== $sessionID) {
            $this->ResetTVVideoRuntime();
            return;
        }
        if ($this->ReadAttributeInteger('VideoAttempts') >= 3) {
            $this->SendDebug('TVVideo', 'Videostart nach 3 Versuchen beendet', 0);
            return;
        }
        $this->StartAlarmVideoNowInternal($sessionID, true);
    }

    /**
     * Hält ausschließlich während eines aktiven Alarmvideos den Samsung-NextURI-
     * Puffer gefüllt. Das ist kein GUS-/LCN-Polling; alle 30 s wird nur ein kleiner
     * UPnP-Befehl an den TV gesendet, solange die Alarm-Session aktiv ist.
     */
    public function TVVideoLoopGuard(): void
    {
        $this->SetTimerInterval('TVVideoLoopGuard', 0);
        if ($this->ReadAttributeInteger('VideoActive') !== 1) {
            return;
        }

        $sessionID = $this->ReadAttributeString('VideoSessionID');
        if ($sessionID === '' || !$this->IsActiveSession($sessionID) || $this->ReadAttributeString('TVOwnerSessionID') !== $sessionID) {
            $this->ResetTVVideoRuntime();
            return;
        }

        $mode = $this->ReadAttributeString('LastMediaMode');
        $rearm = $this->ArmNextMedia($mode !== '' ? $mode : 'mpeg');
        if ((bool) ($rearm['ok'] ?? false)) {
            $this->WriteAttributeInteger('VideoLoopRearmCount', $this->ReadAttributeInteger('VideoLoopRearmCount') + 1);
            $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
            $this->SetTimerInterval('TVVideoLoopGuard', 30000);
            $this->SendDebug('TVVideoLoop', 'NextURI fuer weiteren Durchlauf nachgeladen', 0);
            return;
        }

        $failures = $this->ReadAttributeInteger('VideoLoopRearmFailures') + 1;
        $this->WriteAttributeInteger('VideoLoopRearmFailures', $failures);
        $this->SendDebug('TVVideoLoop', 'NextURI-Nachladung fehlgeschlagen: ' . (string) ($rearm['message'] ?? ''), 0);
        $this->SetTimerInterval('TVVideoLoopGuard', $failures <= 2 ? 5000 : 30000);
    }

    /** Bestätigt den echten Medienabruf des Samsung und aktiviert danach die Endlosschleife. */
    public function TVVideoStatsSync(): void
    {
        $this->SetTimerInterval('TVVideoStatsSync', 0);
        $stats = $this->SyncMediaStats();
        if ($this->ReadAttributeInteger('VideoActive') !== 1) {
            return;
        }

        $sessionID = $this->ReadAttributeString('VideoSessionID');
        if ($sessionID === '' || !$this->IsActiveSession($sessionID) || $this->ReadAttributeString('TVOwnerSessionID') !== $sessionID) {
            $this->ResetTVVideoRuntime();
            return;
        }

        $count = (int) ($stats['count'] ?? 0);
        $bytes = (int) ($stats['bytesSent'] ?? 0);
        if ($count > 0 && $bytes > 0) {
            $this->WriteAttributeBoolean('TVSeenOnDuringAlarm', true);
        }
        if ($count > 0 && $bytes > 0 && $this->ReadAttributeInteger('VideoLoopFallbackPending') === 1) {
            $this->WriteAttributeInteger('VideoLoopFallbackPending', 0);
            $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
            $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
            $this->SetTimerInterval('TVVideoLoopGuard', 30000);
            $this->SendDebug('TVVideo', 'Samsung ruft Alarmvideo ab – Endlosschleife aktiviert', 0);
        }

        $ticks = $this->ReadAttributeInteger('VideoStatsWaitTicks') + 1;
        $this->WriteAttributeInteger('VideoStatsWaitTicks', $ticks);
        if ($ticks < 20) {
            $this->SetTimerInterval('TVVideoStatsSync', 500);
        } elseif ($count === 0) {
            $this->SendDebug('TVVideo', 'Startbefehl akzeptiert, aber 10 s kein Medienabruf', 0);
            $this->WriteAttributeInteger('VideoActive', 0);
        }
    }

    /** Einmal-Timer für die nächste EIN- oder AUS-Zeitgrenze. */
    public function ScheduleTimer(): void
    {
        $this->SetTimerInterval('ScheduleTimer', 0);

        if (!(bool) $this->GetValue('Automatic')) {
            return;
        }

        // Eine Zeitgrenze beendet eine bestehende manuelle Übersteuerung.
        $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
        $this->ApplyCurrentSchedule('schedule-boundary');
        $this->ScheduleNextBoundary();
    }

    /**
     * Boot-/ApplyChanges-Schutzphase. Sensorwerte werden frisch eingelesen und
     * erst danach für neue Alarme freigegeben. Eine vorhandene aktive Session
     * wird fortgeführt, eine neue Session kann hier niemals erzeugt werden.
     */
    public function StartupGuard(): void
    {
        $this->SetTimerInterval('StartupGuard', 0);

        if ($this->GetBuffer('ConfigurationOK') !== '1') {
            return;
        }

        $initializing = $this->GetBuffer('RuntimeReady') !== '1';
        $expected = $this->ReadStartupIDBuffer('StartupExpectedSensorIDs');
        $seen = $this->ReadStartupIDBuffer('StartupSeenSensorIDs');
        $missing = array_values(array_diff($expected, $seen));
        $attempt = max(0, (int) $this->GetBuffer('StartupSyncAttempt'));

        if ($initializing && $missing !== [] && $attempt < self::STARTUP_SYNC_MAX_ATTEMPTS) {
            // Genau ein begrenzter zweiter Statusabgleich; kein zyklisches Polling.
            $this->RequestStartupSensorStatus($this->ReadSensorMap());
            $this->SetBuffer('StartupSyncAttempt', (string) ($attempt + 1));
            $this->SetTimerInterval('StartupGuard', self::STARTUP_SYNC_RETRY_MS);
            $this->SetValue('Status', 'INITIALISIERUNG – Sensorstatus wird abgeglichen');
            $this->PushVisualizationState();
            return;
        }

        $syncComplete = ($missing === []);
        $this->SetBuffer('StartupSyncIncomplete', $syncComplete ? '0' : '1');

        if (!$syncComplete) {
            IPS_LogMessage(
                'LCN Alarmanlage #' . $this->InstanceID,
                'Startschutz: keine frische Statusmeldung von GUS #' . implode(', #', $missing) .
                '. Anlage bleibt fail-safe nicht auslösebereit, bis diese Sensoren aktualisiert wurden.'
            );
            $this->SendDebug('StartupSync', 'Fehlende Sensorupdates: ' . implode(', ', $missing), 0);
        }

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('StartupGuard');
            $this->SetTimerInterval('StartupGuard', 1000);
            return;
        }

        $sessionID = '';
        $sessionState = self::SESSION_NONE;
        $resumeActiveAlarm = false;
        $restoreRearmLights = false;
        $runAlarmTimeoutNow = false;
        $scheduleAlarmMs = 0;
        $scheduleRearm = false;

        try {
            // Direkt vor Freigabe nochmals alle aktuellen Werte einlesen. Damit ist
            // die Baseline nicht von einem frühen Wert während KR_READY abhängig.
            $states = [];
            foreach ($this->ReadSensorMap() as $variableID => $sensor) {
                $id = (int) ($sensor['id'] ?? $variableID);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $states[(string) $id] = (bool) GetValue($id);
                }
            }
            $this->SetBuffer('SensorStates', $this->Encode($states));

            $session = $this->ReadSession('CurrentSession');
            $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);
            $sessionID = (string) ($session['id'] ?? '');

            if ($sessionState === self::SESSION_ACTIVE) {
                $this->SetValue('AlarmActive', true);
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->SetAlarmControlsVisible(true);
                $resumeActiveAlarm = $initializing && $sessionID !== '';

                if ($syncComplete && $this->AreAllMonitoredSensorsClear($states)) {
                    $deadline = $this->ReadAttributeInteger('AlarmQuietNotBefore');
                    if ($deadline <= 0) {
                        $deadline = time() + max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                        $this->WriteAttributeInteger('AlarmQuietNotBefore', $deadline);
                    }
                    if ($deadline <= time()) {
                        $runAlarmTimeoutNow = true;
                    } else {
                        $scheduleAlarmMs = max(1, ($deadline - time()) * 1000);
                    }
                } else {
                    // Unvollständiger/aktiver Sensorstatus darf eine bestehende
                    // Alarm-Session niemals automatisch beenden.
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                }
            } elseif ($sessionState === self::SESSION_REARM_WAIT) {
                $this->SetValue('AlarmActive', false);
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->SetAlarmControlsVisible(false);
                $restoreRearmLights = $initializing && $sessionID !== '';

                if ($syncComplete && $this->AreAllMonitoredSensorsClear($states)) {
                    if ($this->ReadAttributeInteger('RearmNotBefore') <= 0) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                    }
                    $scheduleRearm = true;
                } else {
                    $this->WriteAttributeInteger('RearmNotBefore', 0);
                }
            } else {
                $this->SetValue('AlarmActive', false);
                $this->SetAlarmControlsVisible(false);

                $armed = (bool) $this->GetValue('Arm');
                $ready = $armed
                    && $syncComplete
                    && $this->CountMonitoredSensors() > 0
                    && $this->AreAllMonitoredSensorsClear($states);
                $this->WriteAttributeBoolean('ArmedReady', $ready);
            }

            // Erst NACH Baseline-Aufbau und Zustandsrekonstruktion dürfen neue
            // Sensorflanken wieder als echte Ereignisse ausgewertet werden.
            $this->SetBuffer('RuntimeReady', '1');
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($scheduleAlarmMs > 0) {
            $this->SetTimerInterval('AlarmTimeout', $scheduleAlarmMs);
        }
        if ($runAlarmTimeoutNow) {
            $this->AlarmTimeout();
        } elseif ($scheduleRearm) {
            $this->ScheduleRearmFromAttribute();
        }

        // Externe Aktionen erst nach der internen Freigabe. Eine alte aktive
        // Alarm-Session wird damit zuverlässig fortgeführt, ohne einen neuen Alarm
        // aus Sensorwerten des Bootvorgangs zu erzeugen.
        $fresh = $this->ReadSession('CurrentSession');
        $freshState = (string) ($fresh['state'] ?? self::SESSION_NONE);
        $freshSessionID = (string) ($fresh['id'] ?? '');
        if ($resumeActiveAlarm && $freshState === self::SESSION_ACTIVE && $freshSessionID === $sessionID) {
            $this->SetPanicForSession($sessionID, true, 'restart');
            $this->StartTVForAlarm($sessionID);
        } elseif ($restoreRearmLights && $freshState === self::SESSION_REARM_WAIT && $freshSessionID === $sessionID) {
            $this->SetPanicForSession($sessionID, false, 'restart-rearm-wait');
        }

        $this->ScheduleNextBoundary();
        $this->RefreshDisplay();
    }

    private function InitializeRuntime(): void
    {
        // Während der Rekonstruktion dürfen eintreffende Sensorupdates nur die Baseline
        // aktualisieren, aber niemals einen historischen Alarm erzeugen.
        $this->SetBuffer('RuntimeReady', '0');
        $this->SetBuffer('StartupExpectedSensorIDs', '[]');
        $this->SetBuffer('StartupSeenSensorIDs', '[]');
        $this->SetBuffer('StartupSyncAttempt', '0');
        $this->SetBuffer('StartupSyncIncomplete', '0');
        $this->SetBuffer('PanicQueue', '[]');
        $this->SetBuffer('PanicLightMap', '{}');
        $this->SetBuffer('NotificationQueue', '[]');
        $this->SetBuffer('TVConfig', '{}');

        $this->SetTimerInterval('AlarmTimeout', 0);
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('RearmDisplay', 0);
        $this->SetTimerInterval('ScheduleTimer', 0);
        $this->SetTimerInterval('PanicQueue', 0);
        $this->SetTimerInterval('NotificationQueue', 0);
        $this->SetTimerInterval('TVWakeRetry', 0);
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->SetTimerInterval('StartupGuard', 0);
        $this->StopAllTVVideoTimers();

        $this->UnregisterOldSensorMessages();
        $this->UnregisterOldMotionStatusMessages();
        $this->UnregisterOldAcknowledgeMessages();
        $this->UnregisterOldPanicReference();

        [$sensorMap, $errors] = $this->BuildSensorMap();
        // Reine Visualisierungsquelle: alle konfigurierten GUS plus automatisch
        // gefundene native LCN-Units mit Bewegungsmelder-/GUS-Bezeichnung.
        $motionStatusMap = $this->BuildMotionStatusMap($sensorMap);
        // Die bisherige Property AcknowledgeLights bleibt aus Update-/Rollback-Gründen
        // unverändert erhalten, beschreibt ab 0.1.14 aber ausschließlich die Lichter,
        // die bei Alarm als Panikbeleuchtung eingeschaltet werden. Quittieren darf jede
        // installierte LCNLight-Instanz; diese Liste wird automatisch ermittelt.
        [$panicLightMap, $panicLightErrors] = $this->BuildPanicLightMap($sensorMap);
        $acknowledgeMap = $this->BuildAllLCNLightMap($sensorMap, $panicLightMap);
        [$panicVariableID, $panicErrors] = $this->BuildPanicConfig();
        [$notificationConfig, $notificationWarnings] = $this->BuildNotificationConfig();
        [$tvConfig, $tvWarnings] = $this->BuildTVConfig();
        if ((bool) ($tvConfig['enabled'] ?? false)) {
            // Der DLNA-Server wird einmalig bzw. nach Konfigurationsänderungen
            // vorbereitet. Bei belegtem Wunschport wird automatisch der nächste
            // freie Port verwendet, damit das alte Testmodul während der Migration
            // parallel installiert bleiben kann.
            $media = $this->EnsureAlarmMediaServer();
            if (!(bool) ($media['ok'] ?? false)) {
                $tvConfig['enabled'] = false;
                $tvWarnings[] = 'Alarmvideo-Medienserver nicht bereit: ' . (string) ($media['message'] ?? 'unbekannter Fehler');
            } else {
                $tvConfig['mediaPortActive'] = (int) ($media['port'] ?? $this->ReadAttributeInteger('MediaServerPortActive'));
            }
        }
        $errors = array_merge($errors, $panicLightErrors, $panicErrors);

        $this->SetBuffer('SensorMap', $this->Encode($sensorMap));
        $this->SetBuffer('MotionStatusMap', $this->Encode($motionStatusMap));
        $this->SetBuffer('PanicLightMap', $this->Encode($panicLightMap));
        $this->SetBuffer('AcknowledgeMap', $this->Encode($acknowledgeMap));
        $this->SetBuffer('PanicGroupVariableID', (string) $panicVariableID);
        $this->SetBuffer('NotificationConfig', $this->Encode($notificationConfig));
        $this->SetBuffer('TVConfig', $this->Encode($tvConfig));
        $this->SetBuffer('NotificationWarnings', $this->Encode($notificationWarnings));
        $this->SetBuffer('TVWarnings', $this->Encode($tvWarnings));

        foreach ($notificationWarnings as $warning) {
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Benachrichtigung: ' . $warning);
            $this->SendDebug('NotificationConfig', $warning, 0);
        }
        foreach ($tvWarnings as $warning) {
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Samsung-TV: ' . $warning);
            $this->SendDebug('TVConfig', $warning, 0);
        }
        if (!(bool) ($tvConfig['enabled'] ?? false)) {
            // Deaktivierte/ungueltige optionale TV-/Videofunktion darf keinerlei
            // alten Wiedergabe- oder Nachlaufauftrag behalten. Ein eventuell noch
            // laufendes Alarmvideo wird best-effort gestoppt, ohne den Alarmkern
            // zu beeinflussen.
            $videoSessionID = $this->ReadAttributeString('VideoSessionID');
            if ($videoSessionID !== '' || $this->ReadAttributeInteger('VideoActive') === 1) {
                $this->StopAlarmVideoForSession($videoSessionID, 'configuration-disabled', true);
            }
            $this->ClearTVOwnership();
        }

        if ($errors !== []) {
            $this->SetBuffer('ConfigurationOK', '0');
            $this->SetTimerInterval('ScheduleTimer', 0);
            $this->SetTimerInterval('PanicQueue', 0);
            $this->SetTimerInterval('NotificationQueue', 0);
            $this->SetSummary('Konfigurationsfehler');
            try {
                $this->SetArmedInternal(false, 'configuration-error');
            } catch (Throwable $e) {
                IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Abschalten bei Konfigurationsfehler fehlgeschlagen: ' . $e->getMessage());
            }
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);
            $this->SetValue('Status', 'STÖRUNG – ' . implode('; ', $errors));
            $this->PushVisualizationState();
            $this->SendDebug('Configuration', implode(' | ', $errors), 0);
            return;
        }

        if ($sensorMap === []) {
            $this->SetBuffer('ConfigurationOK', '0');
            $this->SetTimerInterval('ScheduleTimer', 0);
            $this->SetTimerInterval('PanicQueue', 0);
            $this->SetTimerInterval('NotificationQueue', 0);
            $this->SetSummary('Keine GUS');
            try {
                $this->SetArmedInternal(false, 'no-sensors');
            } catch (Throwable $e) {
                IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Abschalten ohne konfigurierte Melder fehlgeschlagen: ' . $e->getMessage());
            }
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);
            $this->SetValue('Status', 'KONFIGURATION – keine GUS ausgewählt');
            $this->PushVisualizationState();
            return;
        }

        $this->EnsureSensorWatchVariables($sensorMap);
        $this->SetStaticVariablePositions();

        $this->SetBuffer('ConfigurationOK', '1');

        $states = [];
        foreach ($sensorMap as $variableID => $sensor) {
            $states[(string) $variableID] = (bool) GetValue((int) $variableID);
            $this->RegisterMessage((int) $variableID, VM_UPDATE);
            $this->RegisterMessage((int) $variableID, OM_UNREGISTER);
            $this->RegisterReference((int) $variableID);
        }
        $this->SetBuffer('SensorStates', $this->Encode($states));
        $this->WriteAttributeString('RegisteredSensorIDs', $this->Encode(array_map('intval', array_keys($sensorMap))));

        // Fuer bereits als Alarmquelle registrierte GUS ist keine zweite Message-
        // Registrierung notwendig. Nur zusaetzlich automatisch gefundene Melder
        // bekommen eine reine Anzeige-Subscription. Das erzeugt keinen LCN-Traffic.
        $registeredMotionStatusIDs = [];
        foreach ($motionStatusMap as $variableID => $sensor) {
            $variableID = (int) $variableID;
            if ($variableID <= 0 || isset($sensorMap[(string) $variableID]) || !IPS_VariableExists($variableID)) {
                continue;
            }
            $this->RegisterMessage($variableID, VM_UPDATE);
            $this->RegisterMessage($variableID, OM_UNREGISTER);
            $this->RegisterReference($variableID);
            $registeredMotionStatusIDs[] = $variableID;
        }
        $this->WriteAttributeString('RegisteredMotionStatusIDs', $this->Encode($registeredMotionStatusIDs));

        $acknowledgeStates = [];
        foreach ($acknowledgeMap as $variableID => $light) {
            $acknowledgeStates[(string) $variableID] = (bool) GetValue((int) $variableID);
            $this->RegisterMessage((int) $variableID, VM_UPDATE);
            $this->RegisterMessage((int) $variableID, OM_UNREGISTER);
            $this->RegisterReference((int) $variableID);
        }
        $this->SetBuffer('AcknowledgeStates', $this->Encode($acknowledgeStates));
        $this->WriteAttributeString('RegisteredAcknowledgeIDs', $this->Encode(array_map('intval', array_keys($acknowledgeMap))));

        if ($panicVariableID > 0) {
            $this->RegisterMessage($panicVariableID, OM_UNREGISTER);
            $this->RegisterReference($panicVariableID);
            $this->WriteAttributeInteger('RegisteredPanicVariableID', $panicVariableID);
        }

        $this->RefreshSummary();

        $session = $this->ReadSession('CurrentSession');
        $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);

        if ($sessionState !== self::SESSION_ACTIVE) {
            $this->InvalidateEmailAckToken('');
            $staleVideoSessionID = $this->ReadAttributeString('VideoSessionID');
            if ($staleVideoSessionID !== '' || $this->ReadAttributeInteger('VideoActive') === 1) {
                $this->StopAlarmVideoForSession($staleVideoSessionID, 'startup-no-active-session', true);
            }
            $this->ClearTVOwnership();
        }

        if ($sessionState === self::SESSION_ACTIVE && !(bool) $this->GetValue('Arm')) {
            // Nur eine tatsächlich persistierte Inkonsistenz wird bereinigt. Ein
            // Kernelstart selbst verändert den vorherigen EIN/AUS-Zustand nicht.
            $this->SetArmedInternal(false, 'restart-reconcile');
            $session = $this->ReadSession('CurrentSession');
            $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);
        }

        if ($sessionState === self::SESSION_ACTIVE) {
            $this->SetValue('AlarmActive', true);
            $this->WriteAttributeBoolean('ArmedReady', false);
            $this->SetAlarmControlsVisible(true);
        } elseif ($sessionState === self::SESSION_REARM_WAIT) {
            $this->SetValue('AlarmActive', false);
            $this->WriteAttributeBoolean('ArmedReady', false);
            $this->SetAlarmControlsVisible(false);
        } else {
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);

            // Statusvariablen sind in Symcon persistent. Beim Kernelstart wird daher
            // bewusst der tatsächlich vor dem Ausfall gespeicherte Arm-Zustand
            // übernommen. Die Zeitautomatik wird NICHT rückwirkend ausgewertet,
            // sondern setzt ab der nächsten regulären Zeitgrenze fort.
            $this->ApplyDesiredArmState((bool) $this->GetValue('Arm'));
        }

        // Einmalige aktive Statussynchronisation der nativen LCN-Module. LCN_RequestStatus
        // fragt laut Symcon-Dokumentation u. a. alle binären Sensoren eines LCN-Moduls ab.
        // Bis die erwarteten Antworten angekommen sind, bleibt ArmedReady immer FALSE.
        $this->SetBuffer('StartupSeenSensorIDs', '[]');
        $this->SetBuffer('StartupSyncAttempt', '0');
        $this->SetBuffer('StartupSyncIncomplete', '0');
        $sync = $this->RequestStartupSensorStatus($sensorMap);
        $this->SetBuffer('StartupExpectedSensorIDs', $this->Encode($sync['expectedSensorIDs'] ?? []));
        $this->SetBuffer('StartupSyncAttempt', '1');

        // Während dieses Zeitfensters werden VM_UPDATE-Meldungen ausschließlich als
        // Baseline übernommen. Dadurch kann eine beim Booten nachgelieferte TRUE-Meldung
        // eines bereits aktiven GUS niemals als neue FALSE->TRUE-Alarmflanke gelten.
        $this->SetTimerInterval('StartupGuard', self::STARTUP_SYNC_WAIT_MS);

        $this->ScheduleNextBoundary();
        $this->RefreshDisplay();
    }

    private function ProcessSensorUpdate(int $VariableID): void
    {
        $sensorMap = $this->ReadSensorMap();
        if (!isset($sensorMap[(string) $VariableID]) && !isset($sensorMap[$VariableID])) {
            return;
        }

        if (!IPS_VariableExists($VariableID)) {
            $this->HandleSensorUnavailable($VariableID);
            return;
        }

        $newValue = (bool) GetValue($VariableID);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('ProcessSensorUpdate #' . $VariableID);
            return;
        }

        $firstAlarmTrigger = false;
        $alarmSessionID = '';
        $scheduleRearm = false;
        $cancelRearm = false;
        $scheduleAlarmAfterrun = false;
        $cancelAlarmAfterrun = false;
        $alarmAfterrunMs = 0;
        $changed = false;

        try {
            // Während der Startschutzphase zählt auch ein VM_UPDATE ohne Wertänderung
            // als frische Statusbestätigung des nativen LCN-Sensors.
            $this->MarkStartupSensorSeen($VariableID);

            $states = $this->ReadSensorStates();
            $key = (string) $VariableID;

            // Fehlender Baseline-Wert ist Initialisierung, kein Alarmereignis.
            if (!array_key_exists($key, $states)) {
                $states[$key] = $newValue;
                $this->SetBuffer('SensorStates', $this->Encode($states));
                return;
            }

            $oldValue = (bool) $states[$key];
            if ($oldValue === $newValue) {
                return;
            }

            $states[$key] = $newValue;
            $this->SetBuffer('SensorStates', $this->Encode($states));
            $changed = true;

            // ApplyChanges/Kernelstart: Statusänderungen werden als aktuelle Baseline
            // übernommen. Damit kann ein bereits aktiver GUS niemals durch die
            // Initialisierung einen historischen Fehlalarm auslösen.
            if ($this->GetBuffer('RuntimeReady') !== '1') {
                return;
            }

            $session = $this->ReadSession('CurrentSession');
            $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);
            $watched = $this->IsSensorWatchEnabled($VariableID);

            if ($sessionState !== self::SESSION_NONE) {
                if ($watched) {
                    $this->AppendSessionEvent($session, $VariableID, $newValue ? 'motion' : 'clear');
                    $this->WriteSession('CurrentSession', $session);
                }

                if ($sessionState === self::SESSION_ACTIVE && $watched) {
                    if ($newValue) {
                        // Jede neue Bewegung hebt den laufenden Nachlauf sofort auf.
                        $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                        $cancelAlarmAfterrun = true;
                    } elseif ($this->IsStartupSensorSyncComplete() && $this->AreAllMonitoredSensorsClear($states)) {
                        // Erst wenn wirklich alle aktuell überwachten Räume frei und
                        // die Start-Synchronisation vollständig bestätigt ist,
                        // beginnt die volle Nachlaufzeit von vorn.
                        $duration = max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                        $deadline = time() + $duration;
                        $this->WriteAttributeInteger('AlarmQuietNotBefore', $deadline);
                        $alarmAfterrunMs = $duration * 1000;
                        $scheduleAlarmAfterrun = true;
                    }
                }

                if ($sessionState === self::SESSION_REARM_WAIT && $watched) {
                    if ($newValue) {
                        $this->WriteAttributeInteger('RearmNotBefore', 0);
                        $cancelRearm = true;
                    } elseif ($this->IsStartupSensorSyncComplete() && $this->AreAllMonitoredSensorsClear($states)) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                        $scheduleRearm = true;
                    }
                }
            } elseif ((bool) $this->GetValue('Arm')) {
                if (!(bool) $this->ReadAttributeBoolean('ArmedReady')) {
                    if (
                        $this->IsStartupSensorSyncComplete()
                        && $this->CountMonitoredSensors() > 0
                        && $this->AreAllMonitoredSensorsClear($states)
                    ) {
                        $this->WriteAttributeBoolean('ArmedReady', true);
                    }
                } elseif ($watched && $newValue) {
                    $session = $this->CreateAlarmSession($VariableID);
                    $this->WriteSession('CurrentSession', $session);
                    $this->WriteAttributeBoolean('ArmedReady', false);

                    // Alle schnellen, lokalen Zustandsänderungen der ersten Auslösung
                    // werden noch unter derselben Semaphore gesetzt. Dadurch kann eine
                    // exakt gleichzeitig eintreffende manuelle Abschaltung sie danach
                    // zuverlässig wieder aufheben; außerhalb der Semaphore wird nichts
                    // erneut auf ALARM gesetzt.
                    $this->SetValue('AlarmActive', true);
                    $this->SetValue('Acknowledge', false);
                    $this->SetAlarmControlsVisible(true);
                    // Kein fester Timer ab Alarmstart. Die Nachlaufzeit beginnt erst,
                    // wenn alle überwachten GUS wieder frei melden.
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                    $this->SetTimerInterval('AlarmTimeout', 0);
                    $firstAlarmTrigger = true;
                    $alarmSessionID = (string) ($session['id'] ?? '');
                }
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if (!$changed) {
            return;
        }

        if ($cancelAlarmAfterrun) {
            $this->SetTimerInterval('AlarmTimeout', 0);
        }
        if ($scheduleAlarmAfterrun) {
            $this->SetTimerInterval('AlarmTimeout', max(1, $alarmAfterrunMs));
        }

        if ($cancelRearm) {
            $this->SetTimerInterval('RearmTimeout', 0);
            $this->SetTimerInterval('RearmDisplay', 0);
        }

        if ($scheduleRearm) {
            $this->ScheduleRearmFromAttribute();
        }

        if ($firstAlarmTrigger) {
            // Externe/langsamere Aktionen laufen außerhalb der Engine-Semaphore.
            // Vor und nach PANIK EIN wird die Session-ID geprüft, damit eine nahezu
            // gleichzeitige Quittierung kein dauerhaftes Licht-EIN hinterlässt.
            if ($alarmSessionID !== '') {
                // Der direkte Samsung-WakeUp wird zuerst ausgeführt und kann dadurch
                // weder von Paniklicht noch von Push/SMTP verzögert werden.
                $this->StartTVForAlarm($alarmSessionID);
                $this->SetPanicForSession($alarmSessionID, true, 'alarm-start');
                $this->QueueAlarmNotifications($alarmSessionID);
            }
            $this->SendDebug('ALARM', 'Neue Alarm-Session durch Variable #' . $VariableID, 0);
        }

        $this->RefreshDisplay();
    }

    private function ProcessAcknowledgeLightUpdate(int $VariableID): void
    {
        if (!IPS_VariableExists($VariableID)) {
            $this->HandleAuxiliaryUnavailable($VariableID);
            return;
        }

        $newValue = (bool) GetValue($VariableID);
        $source = '';
        $shouldAcknowledge = false;

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('ProcessAcknowledgeLightUpdate #' . $VariableID);
            return;
        }

        try {
            $states = $this->ReadAcknowledgeStates();
            $key = (string) $VariableID;

            if (!array_key_exists($key, $states)) {
                $states[$key] = $newValue;
                $this->SetBuffer('AcknowledgeStates', $this->Encode($states));
                return;
            }

            $oldValue = (bool) $states[$key];
            if ($oldValue === $newValue) {
                return;
            }

            $states[$key] = $newValue;
            $this->SetBuffer('AcknowledgeStates', $this->Encode($states));

            if ($this->GetBuffer('RuntimeReady') !== '1') {
                return;
            }

            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) !== self::SESSION_ACTIVE) {
                return;
            }

            $panicMap = $this->ReadPanicLightMap();
            $isPanicLight = isset($panicMap[$key]);

            if ($isPanicLight) {
                // Paniklichter werden beim Alarm automatisch nur AUS -> EIN geschaltet.
                // Diese Flanke darf den Alarm niemals selbst quittieren. Ein echter
                // Tastendruck auf ein während des Alarms leuchtendes Paniklicht erzeugt
                // dagegen EIN -> AUS und quittiert sicher.
                $shouldAcknowledge = $oldValue && !$newValue;
            } else {
                // Alle übrigen LCNLight-Instanzen werden vom Alarm nicht automatisch
                // geschaltet. Deshalb ist jede echte Zustandsänderung während einer
                // aktiven Alarm-Session ein zulässiger Quittierungsweg. Damit kann z. B.
                // OG Schlafen 1 unabhängig von der Paniklichtgruppe quittieren.
                $shouldAcknowledge = true;
            }

            if ($shouldAcknowledge) {
                $source = 'LCN-Licht: ' . $this->AcknowledgeLightName($VariableID);
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($shouldAcknowledge) {
            $this->AcknowledgeAlarmInternal($source);
        }
    }

    private function SetPanicForSession(string $SessionID, bool $On, string $Reason): void
    {
        if (!$On) {
            // AUS bedeutet ab 0.1.14 nicht mehr "alle Paniklichter AUS", sondern:
            // exakt den vor Alarm gespeicherten Zustand ALLER LCN-Lichter herstellen.
            // Dadurch bleiben zuvor eingeschaltete Lichter eingeschaltet und auch der
            // zur Quittierung betätigte GT8 wird auf seinen Ursprungszustand zurückgesetzt.
            $this->RestoreLightsForSession($SessionID, $Reason);
            return;
        }

        if (!$this->IsActiveSession($SessionID)) {
            return;
        }

        $panicMap = $this->ReadPanicLightMap();
        if ($panicMap === []) {
            return;
        }

        $snapshot = $this->ReadLightSnapshotForSession($SessionID);
        if ($snapshot === []) {
            $this->SendDebug('Panic', 'Kein Licht-Snapshot für Session ' . $SessionID . ' vorhanden; keine blinden Toggle-Befehle.', 0);
            return;
        }

        $queue = [];
        foreach ($panicMap as $key => $light) {
            if (!isset($snapshot[(string) $key]) || !array_key_exists('state', $snapshot[(string) $key])) {
                continue;
            }

            // Nur Lichter, die VOR dem Alarm sicher AUS waren, dürfen vom Alarm
            // eingeschaltet werden. Vorher bereits eingeschaltete Lichter erhalten
            // überhaupt keinen Befehl und können daher nicht versehentlich toggeln.
            $original = (bool) $snapshot[(string) $key]['state'];
            if ($original) {
                continue;
            }

            $instanceID = (int) ($light['instanceID'] ?? 0);
            $variableID = (int) ($light['id'] ?? 0);
            $current = $this->ReadLCNLightState($instanceID, $variableID);
            if ($current !== false) {
                // TRUE = bereits EIN; NULL = unbekannt -> niemals blind toggeln.
                continue;
            }

            $queue[] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'target' => true,
                'mode' => 'panic-on',
                'sessionID' => $SessionID,
                'reason' => $Reason
            ];
        }

        $this->QueueLightCommands($queue);
    }

    private function RestoreLightsForSession(string $SessionID, string $Reason): void
    {
        if ($SessionID === '') {
            return;
        }

        $snapshot = $this->ReadLightSnapshotForSession($SessionID);
        if ($snapshot === []) {
            return;
        }

        $queue = [];
        foreach ($snapshot as $light) {
            if (!is_array($light) || !array_key_exists('state', $light)) {
                continue;
            }

            $instanceID = (int) ($light['instanceID'] ?? 0);
            $variableID = (int) ($light['id'] ?? 0);
            if ($instanceID <= 0 || $variableID <= 0) {
                continue;
            }

            $target = (bool) $light['state'];
            $current = $this->ReadLCNLightState($instanceID, $variableID);
            if ($current === null || $current === $target) {
                continue;
            }

            $queue[] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'target' => $target,
                'mode' => 'restore',
                'sessionID' => $SessionID,
                'reason' => $Reason
            ];
        }

        $this->QueueLightCommands($queue);
    }

    private function QueueLightCommands(array $Queue): void
    {
        // Ein neuer definierter Zustand ersetzt einen eventuell noch laufenden alten
        // Auftrag. Quittierung kann damit eine noch laufende PANIK-EIN-Serie sofort
        // in die Wiederherstellung des Ursprungszustands umwandeln.
        $this->SetTimerInterval('PanicQueue', 0);
        $this->SetBuffer('PanicQueue', $this->Encode(array_values($Queue)));
        if ($Queue !== []) {
            $this->PanicQueue();
        }
    }

    private function HasAnyActiveSession(): bool
    {
        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('HasAnyActiveSession');
            // Bei Unsicherheit niemals ein mögliches neues Alarmlicht ausschalten.
            return true;
        }

        try {
            $session = $this->ReadSession('CurrentSession');
            return ($session['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE;
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }
    }

    private function IsActiveSession(string $SessionID): bool
    {
        if ($SessionID === '') {
            return false;
        }

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('IsActiveSession');
            return false;
        }

        try {
            $session = $this->ReadSession('CurrentSession');
            return (string) ($session['id'] ?? '') === $SessionID
                && ($session['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE;
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }
    }

    private function HandleSensorUnavailable(int $VariableID): void
    {
        // Ab diesem Zeitpunkt darf keine Automatik die Anlage wieder scharf setzen.
        $this->SetBuffer('ConfigurationOK', '0');
        $this->SetBuffer('RuntimeReady', '0');
        $this->SetTimerInterval('ScheduleTimer', 0);
        $this->SetTimerInterval('PanicQueue', 0);
        $this->SetTimerInterval('NotificationQueue', 0);
        $this->SetBuffer('NotificationQueue', '[]');
        $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_OFF);

        try {
            $this->SetArmedInternal(false, 'sensor-unavailable');
        } catch (Throwable $e) {
            IPS_LogMessage(
                'LCN Alarmanlage #' . $this->InstanceID,
                'Sicheres Abschalten nach Sensorausfall fehlgeschlagen: ' . $e->getMessage()
            );
        }

        $this->SetValue('AlarmActive', false);
        $this->SetAlarmControlsVisible(false);
        $this->SetSummary('Sensorstörung');
        $this->SetValue('Status', 'STÖRUNG – Bewegungsmelder nicht verfügbar (#' . $VariableID . ')');
        $this->PushVisualizationState();
        IPS_LogMessage(
            'LCN Alarmanlage #' . $this->InstanceID,
            'Konfigurierter Bewegungsmelder ist nicht mehr verfügbar: #' . $VariableID
        );
    }

    private function HandleAuxiliaryUnavailable(int $VariableID): void
    {
        IPS_LogMessage(
            'LCN Alarmanlage #' . $this->InstanceID,
            'Optionale Panik-/Quittierfunktion nicht mehr verfügbar: Variable #' . $VariableID
        );
        $this->SendDebug('AuxiliaryUnavailable', 'Variable #' . $VariableID, 0);
    }

    private function AcknowledgeAlarmInternal(string $Source): void
    {
        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('AcknowledgeAlarm/' . $Source);
            throw new Exception('Aktueller Alarm konnte wegen einer internen Zugriffskollision nicht sicher quittiert werden.');
        }

        $startRearmTimer = false;
        $endedSessionID = '';
        try {
            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) !== self::SESSION_ACTIVE) {
                return;
            }

            $endedSessionID = (string) ($session['id'] ?? '');

            // Die Alarmanlage selbst bleibt EIN. Nur die aktuelle Signalisierung wird
            // beendet und die Session für neue Auslösungen verriegelt, bis alle Melder
            // frei waren und die Wieder-Scharf-Verzögerung abgelaufen ist.
            $session['state'] = self::SESSION_REARM_WAIT;
            $session['signalEndedAt'] = microtime(true);
            $session['acknowledgedAt'] = microtime(true);
            $session['acknowledgedBy'] = $Source;
            $session['pendingEndReason'] = 'acknowledged';
            $this->WriteSession('CurrentSession', $session);
            $this->WriteAttributeBoolean('ArmedReady', false);
            $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);

            $states = $this->ReadSensorStates();
            if ($this->AreAllMonitoredSensorsClear($states)) {
                $delay = max(0, $this->ReadPropertyInteger('RearmDelaySeconds'));
                $this->WriteAttributeInteger('RearmNotBefore', time() + $delay);
                $startRearmTimer = true;
            } else {
                $this->WriteAttributeInteger('RearmNotBefore', 0);
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        $this->SetTimerInterval('AlarmTimeout', 0);
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('RearmDisplay', 0);
        $this->SetValue('AlarmActive', false);
        $this->SetValue('Acknowledge', false);
        $this->SetAlarmControlsVisible(false);

        if ($endedSessionID !== '') {
            $this->InvalidateEmailAckToken($endedSessionID);
            $this->SetPanicForSession($endedSessionID, false, 'acknowledged/' . $Source);
            $this->EndTVForAlarm($endedSessionID, 'acknowledged/' . $Source);
        }

        if ($startRearmTimer) {
            $this->ScheduleRearmFromAttribute();
        }

        $this->RefreshDisplay();
    }

    /**
     * Einziger Pfad für Scharf/Unscharf. Arm, ArmedReady und CurrentSession werden
     * unter derselben Semaphore geändert wie Sensorereignisse. Damit kann eine
     * gleichzeitige GUS-Flanke nicht mit einer manuellen/automatischen Abschaltung
     * kollidieren.
     */
    private function SetArmedInternal(bool $Armed, string $Reason): void
    {
        if ($Armed && $this->GetBuffer('ConfigurationOK') !== '1') {
            throw new Exception('Alarmanlage kann wegen fehlerhafter oder fehlender Melderkonfiguration nicht scharf geschaltet werden.');
        }

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('SetArmedInternal/' . $Reason);
            throw new Exception('Alarmanlage konnte wegen einer internen Zugriffskollision nicht sicher geschaltet werden.');
        }

        $stopAlarmTimer = false;
        $stopRearmTimer = false;
        $stopRearmDisplayTimer = false;
        $hideAlarmControls = false;
        $alarmBecameInactive = false;
        $panicOffSessionID = '';
        $tvOffSessionID = '';

        try {
            $this->SetValue('Arm', $Armed);

            if (!$Armed) {
                // Zuerst intern unscharf und Session beenden. Externe/langsame Aktionen
                // werden erst NACH diesem kritischen Abschnitt ausgeführt.
                $current = $this->ReadSession('CurrentSession');
                if (($current['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE) {
                    $panicOffSessionID = (string) ($current['id'] ?? '');
                    $tvOffSessionID = $panicOffSessionID;
                }
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->WriteAttributeInteger('RearmNotBefore', 0);
                $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                $this->FinalizeCurrentSession($Reason);

                $stopAlarmTimer = true;
                $stopRearmTimer = true;
                $stopRearmDisplayTimer = true;
                $hideAlarmControls = true;
                $alarmBecameInactive = true;
            } else {
                $session = $this->ReadSession('CurrentSession');
                if ($session === []) {
                    $states = $this->ReadSensorStates();
                    $ready = $this->GetBuffer('RuntimeReady') === '1'
                        && $this->IsStartupSensorSyncComplete()
                        && $this->CountMonitoredSensors() > 0
                        && $this->AreAllMonitoredSensorsClear($states);
                    $this->WriteAttributeBoolean('ArmedReady', $ready);
                }
                // Eine vorhandene aktive/rearm_wait-Session wird niemals durch ein
                // erneutes EIN überschrieben.
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($stopAlarmTimer) {
            $this->SetTimerInterval('AlarmTimeout', 0);
        }
        if ($stopRearmTimer) {
            $this->SetTimerInterval('RearmTimeout', 0);
        }
        if ($stopRearmDisplayTimer) {
            $this->SetTimerInterval('RearmDisplay', 0);
        }
        if ($alarmBecameInactive) {
            $this->SetValue('AlarmActive', false);
        }
        if ($hideAlarmControls) {
            $this->SetAlarmControlsVisible(false);
        }
        if ($panicOffSessionID !== '') {
            $this->InvalidateEmailAckToken($panicOffSessionID);
            $this->SetPanicForSession($panicOffSessionID, false, 'anlage-aus/' . $Reason);
        } else {
            // Auch eine bereits in rearm_wait befindliche Session bzw. ein alter Link
            // darf nach vollständigem Unscharfschalten nicht weiter quittierbar sein.
            $this->InvalidateEmailAckToken('');
        }
        if ($tvOffSessionID !== '') {
            $this->EndTVForAlarm($tvOffSessionID, 'anlage-aus/' . $Reason);
        }

        $this->RefreshDisplay();
    }

    private function ApplyDesiredArmState(bool $Armed): void
    {
        if (!$Armed) {
            $this->SetValue('Arm', false);
            $this->WriteAttributeBoolean('ArmedReady', false);
            return;
        }

        $this->SetValue('Arm', true);
        $states = $this->ReadSensorStates();
        $ready = $this->GetBuffer('RuntimeReady') === '1'
            && $this->IsStartupSensorSyncComplete()
            && $this->CountMonitoredSensors() > 0
            && $this->AreAllMonitoredSensorsClear($states);
        $this->WriteAttributeBoolean('ArmedReady', $ready);
    }

    private function ApplyCurrentSchedule(string $Reason): void
    {
        if (!(bool) $this->GetValue('Automatic')) {
            return;
        }

        $from = (string) $this->GetValue('AutoFrom');
        $to = (string) $this->GetValue('AutoTo');
        if (!$this->IsValidTime($from) || !$this->IsValidTime($to)) {
            $this->SetValue('Status', 'STÖRUNG – ungültige Automatikzeit');
            return;
        }

        $this->SetArmedInternal($this->IsInsideSchedule($from, $to), $Reason);
    }

    private function ScheduleNextBoundary(): void
    {
        $this->SetTimerInterval('ScheduleTimer', 0);

        if (!(bool) $this->GetValue('Automatic') || $this->GetBuffer('ConfigurationOK') !== '1') {
            return;
        }

        $from = (string) $this->GetValue('AutoFrom');
        $to = (string) $this->GetValue('AutoTo');
        if (!$this->IsValidTime($from) || !$this->IsValidTime($to) || $from === $to) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $nextFrom = $this->NextOccurrence($now, $from);
        $nextTo = $this->NextOccurrence($now, $to);
        $next = ($nextFrom <= $nextTo) ? $nextFrom : $nextTo;
        $milliseconds = max(1000, (int) (($next->getTimestamp() - $now->getTimestamp()) * 1000));
        $this->SetTimerInterval('ScheduleTimer', $milliseconds);
    }

    private function ScheduleRearmFromAttribute(): void
    {
        $notBefore = $this->ReadAttributeInteger('RearmNotBefore');
        if ($notBefore <= 0) {
            $this->SetTimerInterval('RearmTimeout', 0);
            $this->SetTimerInterval('RearmDisplay', 0);
            return;
        }

        $milliseconds = max(1, ($notBefore - time()) * 1000);
        $this->SetTimerInterval('RearmTimeout', $milliseconds);
        // Nur während dieser kurzen Phase einmal pro Sekunde die sichtbare Restzeit
        // aktualisieren. Dies ist rein lokal in Symcon und erzeugt keinen LCN-Traffic.
        $this->SetTimerInterval('RearmDisplay', 1000);
        $this->RefreshDisplay();
    }

    private function CreateAlarmSession(int $VariableID): array
    {
        $counter = $this->ReadAttributeInteger('SessionCounter') + 1;
        $this->WriteAttributeInteger('SessionCounter', $counter);

        $sensorName = $this->SensorName($VariableID);
        $now = microtime(true);

        return [
            'id' => date('Ymd-His') . '-' . sprintf('%04d', $counter % 10000),
            'state' => self::SESSION_ACTIVE,
            'startedAt' => $now,
            'signalEndedAt' => 0,
            'endedAt' => 0,
            'endReason' => '',
            'firstSensorID' => $VariableID,
            'firstSensorName' => $sensorName,
            // Exakter Licht-Istzustand unmittelbar VOR jeder Alarmaktion. Nur sicher
            // bekannte LCNLight-Zustände werden gespeichert; unbekannte Zustände werden
            // weder beim Panik-EIN noch bei der Wiederherstellung blind getoggelt.
            'lightSnapshot' => $this->CaptureLCNLightSnapshot(),
            'notifications' => [],
            'events' => [
                [
                    'seq' => 1,
                    'ts' => $now,
                    'sensorID' => $VariableID,
                    'sensor' => $sensorName,
                    'event' => 'motion'
                ]
            ]
        ];
    }

    private function AppendSessionEvent(array &$Session, int $VariableID, string $Event): void
    {
        if (!isset($Session['events']) || !is_array($Session['events'])) {
            $Session['events'] = [];
        }

        if (count($Session['events']) >= self::MAX_EVENTS_PER_SESSION) {
            return;
        }

        $Session['events'][] = [
            'seq' => count($Session['events']) + 1,
            'ts' => microtime(true),
            'sensorID' => $VariableID,
            'sensor' => $this->SensorName($VariableID),
            'event' => $Event
        ];
    }

    private function FinalizeCurrentSession(string $Reason): void
    {
        $session = $this->ReadSession('CurrentSession');
        if ($session === []) {
            return;
        }

        $session['endedAt'] = microtime(true);
        $session['endReason'] = $Reason;
        $session['state'] = 'ended';
        $this->WriteSession('LastSession', $session);
        $this->WriteSession('CurrentSession', []);
        $this->WriteAttributeInteger('RearmNotBefore', 0);
        $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
    }

    private function RefreshDisplay(): void
    {
        if ($this->GetBuffer('ConfigurationOK') !== '1') {
            return;
        }

        $current = $this->ReadSession('CurrentSession');
        $last = $this->ReadSession('LastSession');
        $session = ($current !== []) ? $current : $last;

        $currentState = (string) ($current['state'] ?? self::SESSION_NONE);
        if ($currentState === self::SESSION_ACTIVE) {
            $this->SetValue('Status', 'ALARM AUSGELÖST');
        } elseif ($currentState === self::SESSION_REARM_WAIT) {
            if ($this->CountMonitoredSensors() === 0) {
                $this->SetValue('Status', 'Alarm beendet – keine GUS aktiv');
            } elseif ($this->AreAllMonitoredSensorsClear($this->ReadSensorStates())) {
                $notBefore = $this->ReadAttributeInteger('RearmNotBefore');
                if ($notBefore > 0) {
                    $remaining = max(0, $notBefore - time());
                    $this->SetValue('Status', 'Wieder scharf in ' . $remaining . ' s');
                } else {
                    $this->SetValue('Status', 'Warte auf erneute Scharfschaltung');
                }
            } else {
                $this->SetValue('Status', 'Warte auf freie Bewegungsmelder');
            }
        } elseif (!(bool) $this->GetValue('Arm')) {
            $this->SetValue('Status', 'ALARMANLAGE AUS');
        } elseif ($this->GetBuffer('RuntimeReady') !== '1') {
            $this->SetValue('Status', 'INITIALISIERUNG – Sensorstatus wird abgeglichen');
        } elseif (!$this->IsStartupSensorSyncComplete()) {
            $this->SetValue('Status', 'SCHARFSCHALTUNG – warte auf aktuellen Sensorstatus');
        } elseif ($this->CountMonitoredSensors() === 0) {
            $this->SetValue('Status', 'ALARMANLAGE EIN – keine GUS aktiv');
        } elseif ($this->ReadAttributeBoolean('ArmedReady')) {
            $this->SetValue('Status', 'ALARMANLAGE SCHARF');
        } else {
            $this->SetValue('Status', 'SCHARFSCHALTUNG – warte auf freie Melder');
        }

        if ($session === []) {
            $this->SetValue('FirstTrigger', '-');
            $this->SetValue('LastMovement', '-');
            $this->SetValue('MotionCount', 0);
            $this->SetValue('MotionLog', '-');
            $this->PushVisualizationState();
            return;
        }

        $firstName = (string) ($session['firstSensorName'] ?? '-');
        $startedAt = (float) ($session['startedAt'] ?? 0);
        $this->SetValue('FirstTrigger', $firstName . (($startedAt > 0) ? ' – ' . $this->FormatTimestamp($startedAt) : ''));

        $events = (isset($session['events']) && is_array($session['events'])) ? $session['events'] : [];
        $motionCount = 0;
        $lastMovement = '-';
        $lines = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventType = (string) ($event['event'] ?? '');
            if ($eventType === 'motion') {
                $motionCount++;
                $lastMovement = (string) ($event['sensor'] ?? '-') . ' – ' . $this->FormatTimestamp((float) ($event['ts'] ?? 0));
            }
            $label = ($eventType === 'motion') ? 'Bewegung' : 'frei';
            $lines[] = sprintf(
                '%03d  %s  %s  %s',
                (int) ($event['seq'] ?? 0),
                $this->FormatTimestamp((float) ($event['ts'] ?? 0)),
                (string) ($event['sensor'] ?? '-'),
                $label
            );
        }

        $this->SetValue('MotionCount', $motionCount);
        $this->SetValue('LastMovement', $lastMovement);
        $this->SetValue('MotionLog', ($lines === []) ? '-' : implode("\n", $lines));

        if ($last !== []) {
            // Technische Endgründe (z. B. "acknowledged") bleiben intern in LastSession
            // erhalten, werden in der Benutzeroberfläche aber bewusst nicht angezeigt.
            $endedAt = (float) ($last['endedAt'] ?? 0);
            $this->SetValue(
                'LastAlarm',
                (string) ($last['id'] ?? '-') . (($endedAt > 0) ? ' – ' . $this->FormatTimestamp($endedAt) : '')
            );
        }

        $this->PushVisualizationState();
    }

    private function BuildVisualizationState(): array
    {
        $sensors = [];
        $states = $this->ReadSensorStates();
        foreach ($this->ReadSensorMap() as $sensor) {
            $variableID = (int) ($sensor['id'] ?? 0);
            if ($variableID <= 0) {
                continue;
            }
            $sensors[] = [
                'id' => $variableID,
                'ident' => $this->SensorWatchIdent($variableID),
                'name' => (string) ($sensor['name'] ?? ('GUS #' . $variableID)),
                'enabled' => $this->IsSensorWatchEnabled($variableID),
                'motion' => (bool) ($states[(string) $variableID] ?? false)
            ];
        }

        $motionSensors = [];
        foreach ($this->ReadMotionStatusMap() as $sensor) {
            $variableID = (int) ($sensor['id'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                continue;
            }
            try {
                $variable = IPS_GetVariable($variableID);
                if ((int) ($variable['VariableType'] ?? -1) !== VARIABLETYPE_BOOLEAN) {
                    continue;
                }
                $active = (bool) GetValue($variableID);
            } catch (Throwable $e) {
                continue;
            }
            $motionSensors[] = [
                'id' => $variableID,
                'name' => (string) ($sensor['name'] ?? ('GUS #' . $variableID)),
                'active' => $active
            ];
        }

        $current = $this->ReadSession('CurrentSession');
        $last = $this->ReadSession('LastSession');
        $historySession = ($current !== []) ? $current : $last;
        $history = [];
        $events = (isset($historySession['events']) && is_array($historySession['events'])) ? $historySession['events'] : [];
        foreach ($events as $event) {
            if (!is_array($event) || (string) ($event['event'] ?? '') !== 'motion') {
                continue;
            }
            $history[] = [
                'seq' => (int) ($event['seq'] ?? 0),
                'name' => (string) ($event['sensor'] ?? '-'),
                'time' => $this->FormatTimestamp((float) ($event['ts'] ?? 0))
            ];
        }

        return [
            'arm' => (bool) $this->GetValue('Arm'),
            'status' => (string) $this->GetValue('Status'),
            'automatic' => (bool) $this->GetValue('Automatic'),
            'autoFrom' => (string) $this->GetValue('AutoFrom'),
            'autoTo' => (string) $this->GetValue('AutoTo'),
            'alarmActive' => (bool) $this->GetValue('AlarmActive'),
            'firstTrigger' => (string) $this->GetValue('FirstTrigger'),
            'lastMovement' => (string) $this->GetValue('LastMovement'),
            'motionCount' => (int) $this->GetValue('MotionCount'),
            'lastAlarm' => (string) $this->GetValue('LastAlarm'),
            'alarmQuietDeadline' => $this->ReadAttributeInteger('AlarmQuietNotBefore'),
            'rearmDeadline' => $this->ReadAttributeInteger('RearmNotBefore'),
            'sensors' => $sensors,
            'motionSensors' => $motionSensors,
            'history' => $history
        ];
    }

    private function PushVisualizationState(): void
    {
        try {
            // Komplexe PHP-Arrays werden von der Symcon-RPC-Grenze nicht in allen
            // Laufzeitkonstellationen automatisch konvertiert. Die HTML-Kachel
            // bekommt deshalb bewusst einen JSON-String. handleMessage() in
            // module.html dekodiert Strings bereits mit JSON.parse().
            $payload = json_encode(
                $this->BuildVisualizationState(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            if ($payload === false) {
                throw new RuntimeException('Visualisierungszustand konnte nicht als JSON codiert werden');
            }
            $this->UpdateVisualizationValue($payload);
        } catch (Throwable $e) {
            // Die individuelle Darstellung ist rein optional. Ein Darstellungsfehler
            // darf niemals den Alarmkern oder eine Aktoraktion beeinflussen.
            $this->SendDebug('Visualization', 'Aktualisierung fehlgeschlagen: ' . $e->getMessage(), 0);
        }
    }

    private function SetAlarmControlsVisible(bool $Visible): void
    {
        $alarmID = $this->GetIDForIdent('AlarmActive');
        $ackID = $this->GetIDForIdent('Acknowledge');
        IPS_SetHidden($alarmID, !$Visible);
        IPS_SetHidden($ackID, !$Visible);
    }

    private function BuildTVConfig(): array
    {
        $warnings = [];
        $requested = $this->ReadPropertyBoolean('TVEnabled');
        $instanceID = $this->ReadPropertyInteger('TVInstanceID');
        $statusVariableID = $this->ReadPropertyInteger('TVStatusVariableID');
        $tvIP = trim($this->ReadPropertyString('TVIP'));
        $symconIP = trim($this->ReadPropertyString('SymconIP'));
        $mediaPort = $this->ReadPropertyInteger('MediaServerPort');
        $startDelayMs = max(250, $this->ReadPropertyInteger('VideoStartDelayMs'));
        $enabled = false;

        if ($requested) {
            if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
                $warnings[] = 'aktiviert, aber keine gueltige SamsungTizen-Instanz ausgewaehlt';
            } elseif (!in_array($instanceID, IPS_GetInstanceListByModuleID(self::SAMSUNG_TIZEN_MODULE_GUID), true)) {
                $warnings[] = 'ausgewaehlte TV-Instanz ist keine SamsungTizen-Instanz';
            } elseif ($statusVariableID <= 0 || !IPS_VariableExists($statusVariableID)) {
                $warnings[] = 'aktiviert, aber keine gueltige TV-Statusvariable ausgewaehlt';
            } else {
                $variable = IPS_GetVariable($statusVariableID);
                if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                    $warnings[] = 'TV-Statusvariable muss Boolean sein';
                } elseif (IPS_GetParent($statusVariableID) !== $instanceID) {
                    $warnings[] = 'TV-Statusvariable gehoert nicht zur ausgewaehlten SamsungTizen-Instanz';
                } elseif (!function_exists('SamsungTizen_WakeUp') || !function_exists('SamsungTizen_SendKeys')) {
                    $warnings[] = 'SamsungTizen_WakeUp/SendKeys sind nicht verfuegbar';
                } elseif ($tvIP === '' || filter_var($tvIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    $warnings[] = 'Samsung-TV-IP ist ungueltig';
                } elseif ($symconIP === '' || filter_var($symconIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    $warnings[] = 'SymBox-IP fuer den DLNA-Medienserver ist ungueltig';
                } elseif ($mediaPort < 1025 || $mediaPort > 65535) {
                    $warnings[] = 'DLNA-Medienserver-Port muss zwischen 1025 und 65535 liegen';
                } elseif (!IPS_ModuleExists(self::MEDIA_HELPER_MODULE_GUID)) {
                    $warnings[] = 'interner Alarmvideo-MediaServer ist nicht geladen';
                } elseif (!is_file($this->GetSharedMediaPath('mpeg')) || !is_file($this->GetSharedMediaPath('mp4'))) {
                    $warnings[] = 'Alarmvideo-Dateien fehlen in der Alarmanlagen-Bibliothek';
                } else {
                    $enabled = true;
                }
            }
        }

        return [[
            'enabled' => $enabled,
            'instanceID' => $instanceID,
            'statusVariableID' => $statusVariableID,
            'tvIP' => $tvIP,
            'symconIP' => $symconIP,
            'mediaPort' => $mediaPort,
            'startDelayMs' => $startDelayMs
        ], $warnings];
    }

    private function ReadTVConfig(): array
    {
        $decoded = json_decode($this->GetBuffer('TVConfig'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function TVIsOn(array $Config): bool
    {
        $statusVariableID = (int) ($Config['statusVariableID'] ?? 0);
        if ($statusVariableID <= 0 || !IPS_VariableExists($statusVariableID)) {
            return false;
        }
        try {
            return (bool) GetValue($statusVariableID);
        } catch (Throwable $e) {
            $this->SendDebug('TV', 'Status lesen fehlgeschlagen: ' . $e->getMessage(), 0);
            return false;
        }
    }

    private function StartTVForAlarm(string $SessionID): void
    {
        if ($SessionID === '' || !$this->IsActiveSession($SessionID)) {
            return;
        }

        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false)) {
            return;
        }

        // Vor jeder Alarm-Session den bereits bei ApplyChanges vorbereiteten
        // Medienserver nochmals leicht verifizieren. Ein Fehler betrifft nur die
        // optionale TV-Aktion und niemals den Alarmkern/Paniklicht.
        $media = $this->EnsureAlarmMediaServer();
        if (!(bool) ($media['ok'] ?? false)) {
            $message = 'Alarmvideo-Medienserver nicht bereit: ' . (string) ($media['message'] ?? 'unbekannter Fehler');
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, $message);
            $this->SendDebug('TVVideo', $message, 0);
            return;
        }

        // Ein eventuell noch laufender AUS-Nachlauf einer alten Session darf die
        // neue Alarm-Session niemals ausschalten.
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->WriteAttributeInteger('TVOffDeadline', 0);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);

        $alreadyOwned = $this->ReadAttributeBoolean('TVOwnedByAlarm');
        $isOn = $this->TVIsOn($config);
        $this->WriteAttributeBoolean('TVSeenOnDuringAlarm', $isOn);

        if ($alreadyOwned) {
            // TV gehoert bereits einem vorherigen Alarm (z.B. neue Session waehrend
            // eines noch laufenden AUS-Nachlaufs). Ownership wird auf die neue
            // Session uebertragen. Ist er inzwischen AUS, wird erneut geweckt.
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
            if ($isOn) {
                $this->SetTimerInterval('TVWakeRetry', 0);
            }
        } elseif ($isOn) {
            // Vor Alarm bereits EIN: Video wird trotzdem wie ein weiteres Paniklicht
            // gestartet, der TV selbst bleibt nach Alarmende jedoch EIN.
            $this->WriteAttributeBoolean('TVOwnedByAlarm', false);
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
            $this->WriteAttributeInteger('TVWakeAttempts', 0);
            $this->WriteAttributeInteger('TVLastWakeAt', 0);
            $this->SendDebug('TV', 'Bei Alarmstart bereits EIN – Alarmvideo startet, TV bleibt nach Alarm EIN', 0);
        } else {
            $this->WriteAttributeBoolean('TVOwnedByAlarm', true);
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
        }

        if (!$isOn) {
            // Exakt derselbe WOL-Pfad wie bisher bzw. im erfolgreich getesteten
            // Samsung Alarmvideo Test 0.2.6: sofort WakeUp, einmaliger Retry nach 5 s.
            $this->WriteAttributeInteger('TVWakeAttempts', 1);
            $this->WriteAttributeInteger('TVLastWakeAt', time());
            $this->SendTVWakeUp($config, 'alarm-start');
            $this->SetTimerInterval('TVWakeRetry', 5000);
        }

        // Das Video wird fuer EIN- und AUS-Ausgangszustand identisch eingeplant.
        // 4000 ms Default und die folgenden Video-Retries entsprechen dem real
        // getesteten v0.2.6-Gesamttest.
        $this->StartAlarmVideoForSession($SessionID);
    }

    private function EndTVForAlarm(string $SessionID, string $Reason): void
    {
        $this->SetTimerInterval('TVWakeRetry', 0);

        if ($SessionID === '') {
            return;
        }

        // Video verhält sich exakt wie ein weiteres Paniklicht: bei Ende derselben
        // Alarm-Session zuerst Wiedergabe/Endlosschleife stoppen. Das gilt auch,
        // wenn der TV bereits vor dem Alarm eingeschaltet war.
        $this->StopAlarmVideoForSession($SessionID, $Reason, false);

        $ownerSessionID = $this->ReadAttributeString('TVOwnerSessionID');
        if ($ownerSessionID !== $SessionID) {
            // Ownership wurde bereits auf eine neue Alarm-Session uebertragen oder
            // der TV war nicht Teil dieser Session.
            return;
        }

        if (!$this->ReadAttributeBoolean('TVOwnedByAlarm')) {
            // TV war bereits vor Alarm EIN. Video ist gestoppt, TV bleibt EIN.
            $this->WriteAttributeString('TVOwnerSessionID', '');
            return;
        }

        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false)) {
            $this->ClearTVOwnership();
            return;
        }

        $this->WriteAttributeInteger('TVOffDeadline', 0);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);

        $isOnNow = $this->TVIsOn($config);
        $seenOnDuringAlarm = $this->ReadAttributeBoolean('TVSeenOnDuringAlarm');

        // Nur ein waehrend dieser Alarm-Session tatsaechlich bestaetigter/jetzt
        // sichtbarer TV darf ausgeschaltet werden. Ist der TV beim Alarmende noch
        // AUS (z.B. WOL-Boot noch nicht abgeschlossen), wird KEIN spaeter
        // Abschaltauftrag hinterlassen. So kann die Wieder-scharf-Phase niemals
        // einen spaeter manuell gestarteten TV abschalten.
        if ($isOnNow || $seenOnDuringAlarm) {
            if ($isOnNow) {
                $this->SendTVPowerOff($config, $Reason);
                // Einmalige reine Statuskontrolle nach 10 s; kein weiterer
                // KEY_POWER-Befehl aus diesem Timer.
                $this->SetTimerInterval('TVOffCheck', 10000);
                return;
            }
        }

        $this->ClearTVOwnership();
    }

    private function SendTVWakeUp(array $Config, string $Reason): bool
    {
        $instanceID = (int) ($Config['instanceID'] ?? 0);
        if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
            return false;
        }
        try {
            SamsungTizen_WakeUp($instanceID);
            $this->SendDebug('TV', 'WakeUp gesendet (' . $Reason . ')', 0);
            return true;
        } catch (Throwable $e) {
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Samsung WakeUp fehlgeschlagen: ' . $e->getMessage());
            $this->SendDebug('TV', 'WakeUp fehlgeschlagen: ' . $e->getMessage(), 0);
            return false;
        }
    }

    private function SendTVPowerOff(array $Config, string $Reason): bool
    {
        $instanceID = (int) ($Config['instanceID'] ?? 0);
        if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
            return false;
        }
        try {
            SamsungTizen_SendKeys($instanceID, 'KEY_POWER');
            $this->SendDebug('TV', 'KEY_POWER AUS gesendet (' . $Reason . ')', 0);
            return true;
        } catch (Throwable $e) {
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Samsung PowerOff fehlgeschlagen: ' . $e->getMessage());
            $this->SendDebug('TV', 'PowerOff fehlgeschlagen: ' . $e->getMessage(), 0);
            return false;
        }
    }

    private function StartAlarmVideoForSession(string $SessionID): void
    {
        if ($SessionID === '' || !$this->IsActiveSession($SessionID)) {
            return;
        }
        if ($this->ReadAttributeString('TVOwnerSessionID') !== $SessionID) {
            return;
        }

        $this->StopAllTVVideoTimers();
        $this->WriteAttributeString('VideoSessionID', $SessionID);
        $this->WriteAttributeInteger('VideoActive', 0);
        $this->WriteAttributeInteger('VideoAttempts', 0);
        $this->WriteAttributeInteger('VideoStatsWaitTicks', 0);
        $this->WriteAttributeInteger('VideoLoopFallbackPending', 0);
        $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
        $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
        $this->ResetMediaStats();

        $config = $this->ReadTVConfig();
        $delay = max(250, (int) ($config['startDelayMs'] ?? $this->ReadPropertyInteger('VideoStartDelayMs')));
        $this->SetTimerInterval('TVVideoStart', $delay);
        $this->SendDebug('TVVideo', sprintf('Alarmvideo fuer Session %s in %.1f s eingeplant', $SessionID, $delay / 1000), 0);
    }

    private function StartAlarmVideoNowInternal(string $SessionID, bool $IsRetry): void
    {
        $this->SetTimerInterval('TVVideoStart', 0);
        if ($SessionID === '' || !$this->IsActiveSession($SessionID) || $this->ReadAttributeString('VideoSessionID') !== $SessionID) {
            return;
        }
        if ($this->ReadAttributeString('TVOwnerSessionID') !== $SessionID) {
            return;
        }

        $media = $this->EnsureAlarmMediaServer();
        if (!(bool) ($media['ok'] ?? false)) {
            $this->SendDebug('TVVideo', 'Medienserver nicht bereit: ' . (string) ($media['message'] ?? ''), 0);
            return;
        }

        $attempt = $this->ReadAttributeInteger('VideoAttempts') + 1;
        $this->WriteAttributeInteger('VideoAttempts', $attempt);

        $preferred = $this->DetectPreferredMediaMode();
        $order = $preferred === 'mp4' ? ['mp4', 'mpeg'] : ['mpeg', 'mp4'];
        $errors = [];

        foreach ($order as $mode) {
            $result = $this->StartMediaMode($mode);
            if ((bool) ($result['ok'] ?? false)) {
                $this->WriteAttributeInteger('VideoActive', 1);
                $this->WriteAttributeString('LastMediaMode', $mode);
                $this->WriteAttributeInteger('VideoAttempts', 0);
                $this->SetTimerInterval('TVVideoRetry', 0);
                $this->WriteAttributeInteger('VideoLoopFallbackPending', 1);
                $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
                $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
                $this->WriteAttributeInteger('VideoStatsWaitTicks', 0);
                $this->SetTimerInterval('TVVideoLoopGuard', 0);
                $this->SetTimerInterval('TVVideoStatsSync', 500);
                $this->SendDebug('TVVideo', 'Startbefehl akzeptiert (' . strtoupper($mode) . ') – warte auf Medienabruf', 0);
                return;
            }
            $errors[] = strtoupper($mode) . ': ' . (string) ($result['message'] ?? 'unbekannter Fehler');
        }

        $message = 'Videostart fehlgeschlagen: ' . implode(' | ', $errors);
        $this->SendDebug('TVVideo', $message, 0);
        if ($attempt < 3) {
            $this->SetTimerInterval('TVVideoRetry', 2000);
            $this->SendDebug('TVVideo', 'Retry ' . ($attempt + 1) . '/3 in 2 s' . ($IsRetry ? ' (Folgeversuch)' : ''), 0);
        }
    }

    private function StopAlarmVideoForSession(string $SessionID, string $Reason, bool $Force): void
    {
        $videoSessionID = $this->ReadAttributeString('VideoSessionID');
        if (!$Force && ($SessionID === '' || $videoSessionID !== $SessionID)) {
            return;
        }

        $hadVideoState = $videoSessionID !== '' || $this->ReadAttributeInteger('VideoActive') === 1;
        $this->StopAllTVVideoTimers();

        if ($hadVideoState) {
            $stop = $this->SendAVTransport('Stop', '<InstanceID>0</InstanceID>');
            if (!(bool) ($stop['ok'] ?? false)) {
                $this->SendDebug('TVVideo', 'Video-Stopp nicht bestaetigt (' . $Reason . '): ' . (string) ($stop['message'] ?? ''), 0);
            } else {
                $this->SendDebug('TVVideo', 'Alarmvideo gestoppt (' . $Reason . ')', 0);
            }
        }

        $this->WriteAttributeInteger('VideoActive', 0);
        $this->WriteAttributeString('VideoSessionID', '');
        $this->WriteAttributeInteger('VideoAttempts', 0);
        $this->WriteAttributeInteger('VideoStatsWaitTicks', 0);
        $this->WriteAttributeInteger('VideoLoopFallbackPending', 0);
        $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
        $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
    }

    private function ResetTVVideoRuntime(): void
    {
        $this->StopAllTVVideoTimers();
        $this->WriteAttributeInteger('VideoActive', 0);
        $this->WriteAttributeString('VideoSessionID', '');
        $this->WriteAttributeInteger('VideoAttempts', 0);
        $this->WriteAttributeInteger('VideoStatsWaitTicks', 0);
        $this->WriteAttributeInteger('VideoLoopFallbackPending', 0);
        $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
        $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
    }

    private function StopAllTVVideoTimers(): void
    {
        $this->SetTimerInterval('TVVideoStart', 0);
        $this->SetTimerInterval('TVVideoRetry', 0);
        $this->SetTimerInterval('TVVideoLoopGuard', 0);
        $this->SetTimerInterval('TVVideoStatsSync', 0);
    }

    private function StartMediaMode(string $Mode): array
    {
        $url = $this->GetMediaURL($Mode);
        $metadata = $this->BuildWindowsLikeMetadata($Mode, $url);

        $set = $this->SendAVTransport(
            'SetAVTransportURI',
            '<InstanceID>0</InstanceID>' .
            '<CurrentURI>' . $this->XmlEscape($url) . '</CurrentURI>' .
            '<CurrentURIMetaData>' . $this->XmlEscape($metadata) . '</CurrentURIMetaData>'
        );
        if (!(bool) ($set['ok'] ?? false)) {
            return $set;
        }

        // REPEAT_ONE ist beim getesteten Q95T nicht zuverlässig. Die reale
        // Dauerschleife nutzt deshalb SetNextAVTransportURI und wird spaeter alle
        // 30 s durch TVVideoLoopGuard nachgeladen.
        $this->SendAVTransport(
            'SetNextAVTransportURI',
            '<InstanceID>0</InstanceID>' .
            '<NextURI>' . $this->XmlEscape($url) . '</NextURI>' .
            '<NextURIMetaData>' . $this->XmlEscape($metadata) . '</NextURIMetaData>'
        );

        return $this->SendAVTransport('Play', '<InstanceID>0</InstanceID><Speed>1</Speed>');
    }

    private function ArmNextMedia(string $Mode): array
    {
        if ($Mode !== 'mpeg' && $Mode !== 'mp4') {
            $Mode = 'mpeg';
        }
        $url = $this->GetMediaURL($Mode);
        $metadata = $this->BuildWindowsLikeMetadata($Mode, $url);
        return $this->SendAVTransport(
            'SetNextAVTransportURI',
            '<InstanceID>0</InstanceID>' .
            '<NextURI>' . $this->XmlEscape($url) . '</NextURI>' .
            '<NextURIMetaData>' . $this->XmlEscape($metadata) . '</NextURIMetaData>'
        );
    }

    private function DetectPreferredMediaMode(): string
    {
        $protocols = $this->GetRendererSinkProtocols();
        if ($protocols === '') {
            return 'mpeg';
        }
        if (stripos($protocols, 'AVC_TS_MP_HD_AAC_MULT5_ISO') !== false) {
            return 'mpeg';
        }
        if (stripos($protocols, 'AVC_MP4_HP_HD_AAC') !== false) {
            return 'mp4';
        }
        return 'mpeg';
    }

    private function GetRendererSinkProtocols(): string
    {
        $config = $this->ReadTVConfig();
        $tvIP = trim((string) ($config['tvIP'] ?? $this->ReadPropertyString('TVIP')));
        if ($tvIP === '') {
            return '';
        }
        $result = $this->SendSOAP(
            self::CM_SERVICE,
            'http://' . $tvIP . ':9197/upnp/control/ConnectionManager1',
            'GetProtocolInfo',
            ''
        );
        if (!(bool) ($result['ok'] ?? false)) {
            $this->SendDebug('TVVideo/GetProtocolInfo', (string) ($result['message'] ?? ''), 0);
            return '';
        }
        if (preg_match('/<Sink>(.*?)<\/Sink>/is', (string) ($result['body'] ?? ''), $m) !== 1) {
            return '';
        }
        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function BuildWindowsLikeMetadata(string $Mode, string $Url): string
    {
        $path = $this->GetSharedMediaPath($Mode);
        $size = is_file($path) ? (int) filesize($path) : 0;
        $duration = $Mode === 'mpeg' ? self::MPEG_DURATION : self::MP4_DURATION;
        $bitrate = $duration > 0 ? (int) round(($size * 8) / $duration) : 0;

        if ($Mode === 'mpeg') {
            $protocol = 'http-get:*:video/mpeg:' . self::MPEG_FEATURES;
            $sampleRate = 44100;
            $channels = 6;
            $trackID = 3;
        } else {
            $protocol = 'http-get:*:video/mp4:' . self::MP4_FEATURES;
            $sampleRate = 48000;
            $channels = 2;
            $trackID = 2;
        }

        return '<DIDL-Lite ' .
            'xmlns:dc="http://purl.org/dc/elements/1.1/" ' .
            'xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" ' .
            'xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/" ' .
            'xmlns:microsoft="urn:schemas-microsoft-com:WMPNSS-1-0/" ' .
            'xmlns:dlna="urn:schemas-dlna-org:metadata-1-0/">' .
            '<item id="1000" restricted="1" parentID="0" ' .
            'microsoft:cpId="{9B7D1343-41ED-433D-B7CA-C5F305F4E181}" microsoft:trackId="' . $trackID . '">' .
            '<dc:title>ALARM</dc:title>' .
            '<res size="' . $size . '" duration="0:01:00.000" bitrate="' . $bitrate . '" resolution="1280x720" ' .
            'protocolInfo="' . $this->XmlEscape($protocol) . '" sampleFrequency="' . $sampleRate . '" nrAudioChannels="' . $channels . '" ' .
            'microsoft:codec="{34363248-0000-0010-8000-00AA00389B71}">' . $this->XmlEscape($Url) . '</res>' .
            '<upnp:class>object.item.videoItem</upnp:class>' .
            '</item></DIDL-Lite>';
    }

    private function SendAVTransport(string $Action, string $Arguments): array
    {
        $config = $this->ReadTVConfig();
        $tvIP = trim((string) ($config['tvIP'] ?? $this->ReadPropertyString('TVIP')));
        if ($tvIP === '') {
            return ['ok' => false, 'message' => 'Samsung-TV-IP fehlt', 'body' => ''];
        }
        return $this->SendSOAP(
            self::AVT_SERVICE,
            'http://' . $tvIP . ':9197/upnp/control/AVTransport1',
            $Action,
            $Arguments
        );
    }

    private function SendSOAP(string $Service, string $Url, string $Action, string $Arguments): array
    {
        $soap = '<?xml version="1.0" encoding="utf-8"?>' .
            '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">' .
            '<s:Body><u:' . $Action . ' xmlns:u="' . $Service . '">' . $Arguments . '</u:' . $Action . '></s:Body></s:Envelope>';

        $headers = [
            'Content-Type: text/xml; charset="utf-8"',
            'SOAPACTION: "' . $Service . '#' . $Action . '"',
            'Connection: close'
        ];

        $status = 0;
        $body = '';
        $transportError = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($Url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $soap,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_FAILONERROR => false
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                $transportError = curl_error($ch);
            } else {
                $body = (string) $response;
            }
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $soap,
                    'timeout' => 6,
                    'ignore_errors' => true
                ]
            ]);
            $response = @file_get_contents($Url, false, $context);
            if ($response === false) {
                $transportError = 'HTTP-Verbindung fehlgeschlagen';
            } else {
                $body = (string) $response;
            }
            if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $hm) === 1) {
                $status = (int) $hm[1];
            }
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'message' => 'OK', 'body' => $body];
        }

        $upnpCode = '';
        $upnpDescription = '';
        if (preg_match('/<errorCode>([^<]+)<\/errorCode>/i', $body, $em) === 1) {
            $upnpCode = trim($em[1]);
        }
        if (preg_match('/<errorDescription>([^<]+)<\/errorDescription>/i', $body, $dm) === 1) {
            $upnpDescription = trim($dm[1]);
        }

        $message = $transportError !== '' ? $transportError : ('HTTP ' . $status);
        if ($upnpCode !== '') {
            $message .= ' / UPnP ' . $upnpCode;
        }
        if ($upnpDescription !== '') {
            $message .= ' ' . $upnpDescription;
        }
        return ['ok' => false, 'message' => $message, 'body' => $body];
    }

    private function EnsureAlarmMediaServer(): array
    {
        $this->WriteAttributeString('LastMediaServerError', '');
        $preferredPort = max(1025, min(65535, $this->ReadPropertyInteger('MediaServerPort')));

        if (!is_file($this->GetSharedMediaPath('mpeg')) || !is_file($this->GetSharedMediaPath('mp4'))) {
            return ['ok' => false, 'message' => 'Alarmvideo-Dateien fehlen in der Bibliothek', 'port' => 0];
        }

        // Schneller Normalpfad: Nach der einmaligen Einrichtung keinerlei ApplyChanges
        // oder Socket-Neustart beim Alarmtrigger. Dadurch bleibt der direkte WOL-Pfad
        // genauso schnell wie in der bisherigen Alarmanlage.
        $knownServerID = $this->ReadAttributeInteger('MediaServerID');
        $knownHelperID = $this->ReadAttributeInteger('MediaHelperID');
        $knownPort = $this->ReadAttributeInteger('MediaServerPortActive');
        if (
            $knownPort >= $preferredPort && $knownPort <= min(65535, $preferredPort + 20)
            && $this->InstanceHasModule($knownServerID, self::SERVER_SOCKET_MODULE_GUID)
            && $this->InstanceHasModule($knownHelperID, self::MEDIA_HELPER_MODULE_GUID)
        ) {
            try {
                $helper = IPS_GetInstance($knownHelperID);
                $server = IPS_GetInstance($knownServerID);
                $serverConfig = json_decode(IPS_GetConfiguration($knownServerID), true);
                $portMatches = is_array($serverConfig) && (int) ($serverConfig['Port'] ?? 0) === $knownPort;
                $open = !is_array($serverConfig) || !array_key_exists('Open', $serverConfig) || (bool) $serverConfig['Open'];
                if ((int) ($helper['ConnectionID'] ?? 0) === $knownServerID && (int) ($server['InstanceStatus'] ?? 999) < 200 && $portMatches && $open) {
                    return ['ok' => true, 'message' => 'integrierter DLNA-Medienserver bereit auf Port ' . $knownPort, 'port' => $knownPort];
                }
            } catch (Throwable $e) {
                // Fallback auf Reparaturpfad unten.
            }
        }

        $serverID = $this->FindOrCreateOwnedInstance(
            'MediaServerID',
            self::SERVER_SOCKET_MODULE_GUID,
            'LCN Alarmanlage Video HTTP',
            'LCNALARM_SOCKET_OWNER:' . $this->InstanceID
        );
        if ($serverID <= 0) {
            return ['ok' => false, 'message' => 'Interner Server Socket konnte nicht erstellt werden', 'port' => 0];
        }

        $activePort = 0;
        for ($offset = 0; $offset <= 20; $offset++) {
            $candidate = $preferredPort + $offset;
            if ($candidate > 65535) {
                break;
            }
            if ($this->MediaPortClaimedByOtherServer($candidate, $serverID)) {
                continue;
            }
            if ($this->ConfigureServerSocket($serverID, $candidate)) {
                $activePort = $candidate;
                break;
            }
        }
        if ($activePort <= 0) {
            return ['ok' => false, 'message' => 'Server Socket ab Port ' . $preferredPort . ' konnte nicht geöffnet werden', 'port' => 0];
        }
        $this->WriteAttributeInteger('MediaServerPortActive', $activePort);

        $helperID = $this->FindOrCreateOwnedInstance(
            'MediaHelperID',
            self::MEDIA_HELPER_MODULE_GUID,
            'LCN Alarmanlage MediaServer (intern)',
            'LCNALARM_HELPER_OWNER:' . $this->InstanceID
        );
        if ($helperID <= 0) {
            $detail = trim($this->ReadAttributeString('LastMediaServerError'));
            return ['ok' => false, 'message' => 'Interner MediaServer-Helper konnte nicht erstellt werden' . ($detail !== '' ? ': ' . $detail : ''), 'port' => 0];
        }

        try {
            $helper = IPS_GetInstance($helperID);
            $currentParent = (int) ($helper['ConnectionID'] ?? 0);
            if ($currentParent !== $serverID) {
                if ($currentParent > 0) {
                    IPS_DisconnectInstance($helperID);
                }
                IPS_ConnectInstance($helperID, $serverID);
            }
            IPS_ApplyChanges($helperID);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'MediaServer-Helper konnte nicht mit Server Socket verbunden werden: ' . $e->getMessage(), 'port' => 0];
        }

        $message = 'integrierter DLNA-Medienserver bereit auf Port ' . $activePort;
        if ($activePort !== $preferredPort) {
            $message .= ' (Wunschport ' . $preferredPort . ' war belegt)';
        }
        $this->SendDebug('TVVideo', $message, 0);
        return ['ok' => true, 'message' => $message, 'port' => $activePort];
    }

    private function FindOrCreateOwnedInstance(string $AttributeName, string $ModuleGUID, string $Name, string $OwnerInfo): int
    {
        $id = $this->ReadAttributeInteger($AttributeName);
        if ($this->InstanceHasModule($id, $ModuleGUID)) {
            return $id;
        }

        foreach (IPS_GetInstanceListByModuleID($ModuleGUID) as $candidate) {
            try {
                $object = IPS_GetObject($candidate);
                if ((string) ($object['ObjectInfo'] ?? '') === $OwnerInfo) {
                    $this->WriteAttributeInteger($AttributeName, $candidate);
                    return $candidate;
                }
            } catch (Throwable $e) {
                // Weiter suchen.
            }
        }

        try {
            if (!IPS_ModuleExists($ModuleGUID)) {
                $error = 'Modul nicht geladen: ' . $ModuleGUID . ' (' . $Name . ')';
                $this->WriteAttributeString('LastMediaServerError', $error);
                return 0;
            }
            $id = IPS_CreateInstance($ModuleGUID);
            if ($id <= 0 || !IPS_InstanceExists($id)) {
                $this->WriteAttributeString('LastMediaServerError', 'Instanz konnte nicht erzeugt werden: ' . $Name);
                return 0;
            }
            IPS_SetName($id, $Name);
            IPS_SetInfo($id, $OwnerInfo);
            IPS_SetHidden($id, true);
            $this->WriteAttributeInteger($AttributeName, $id);
            return $id;
        } catch (Throwable $e) {
            $this->WriteAttributeString('LastMediaServerError', 'Instanz ' . $Name . ' konnte nicht erstellt werden: ' . $e->getMessage());
            return 0;
        }
    }

    private function InstanceHasModule(int $InstanceID, string $ModuleGUID): bool
    {
        if ($InstanceID <= 0 || !IPS_InstanceExists($InstanceID)) {
            return false;
        }
        try {
            $instance = IPS_GetInstance($InstanceID);
            return (string) ($instance['ModuleInfo']['ModuleID'] ?? '') === $ModuleGUID;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function MediaPortClaimedByOtherServer(int $Port, int $OwnServerID): bool
    {
        foreach (IPS_GetInstanceListByModuleID(self::SERVER_SOCKET_MODULE_GUID) as $serverID) {
            if ($serverID === $OwnServerID || !IPS_InstanceExists($serverID)) {
                continue;
            }
            try {
                $configuration = json_decode(IPS_GetConfiguration($serverID), true);
                if (!is_array($configuration)) {
                    continue;
                }
                $configuredPort = (int) ($configuration['Port'] ?? 0);
                $open = !array_key_exists('Open', $configuration) || (bool) $configuration['Open'];
                if ($open && $configuredPort === $Port) {
                    return true;
                }
            } catch (Throwable $e) {
                // Unlesbare fremde Instanz nicht als sicher belegt behandeln.
            }
        }
        return false;
    }

    private function ConfigureServerSocket(int $ServerID, int $Port): bool
    {
        try {
            $configuration = json_decode(IPS_GetConfiguration($ServerID), true);
            if (!is_array($configuration)) {
                $configuration = [];
            }
            if (array_key_exists('Port', $configuration)) {
                IPS_SetProperty($ServerID, 'Port', $Port);
            }
            if (array_key_exists('Open', $configuration)) {
                IPS_SetProperty($ServerID, 'Open', true);
            }
            IPS_ApplyChanges($ServerID);
            return (int) (IPS_GetInstance($ServerID)['InstanceStatus'] ?? 0) < 200;
        } catch (Throwable $e) {
            $this->SendDebug('TVVideo', 'Server Socket Port ' . $Port . ': ' . $e->getMessage(), 0);
            return false;
        }
    }

    private function ResetMediaStats(): void
    {
        $helperID = $this->ReadAttributeInteger('MediaHelperID');
        if ($helperID > 0 && IPS_InstanceExists($helperID) && function_exists('LCNALARMMS_ResetStats')) {
            try {
                LCNALARMMS_ResetStats($helperID);
            } catch (Throwable $e) {
                $this->SendDebug('TVVideoStats', $e->getMessage(), 0);
            }
        }
    }

    private function SyncMediaStats(): array
    {
        $helperID = $this->ReadAttributeInteger('MediaHelperID');
        if ($helperID <= 0 || !IPS_InstanceExists($helperID) || !function_exists('LCNALARMMS_GetStats')) {
            return [];
        }
        try {
            $json = LCNALARMMS_GetStats($helperID);
            $stats = json_decode($json, true);
            return is_array($stats) ? $stats : [];
        } catch (Throwable $e) {
            $this->SendDebug('TVVideoStats', $e->getMessage(), 0);
            return [];
        }
    }

    private function GetMediaURL(string $Mode): string
    {
        $config = $this->ReadTVConfig();
        $host = trim((string) ($config['symconIP'] ?? $this->ReadPropertyString('SymconIP')));
        $port = $this->ReadAttributeInteger('MediaServerPortActive');
        if ($port < 1025 || $port > 65535) {
            $port = max(1025, min(65535, (int) ($config['mediaPort'] ?? $this->ReadPropertyInteger('MediaServerPort'))));
        }
        $file = $Mode === 'mp4' ? '1000.mp4' : '1000.mpeg';
        $formatID = $Mode === 'mp4' ? self::FORMAT_ID_MP4 : self::FORMAT_ID_MPEG;
        return sprintf(
            'http://%s:%d/MDEServer/%s/%s?formatID=%s',
            $host,
            $port,
            self::MEDIA_TOKEN,
            $file,
            $formatID
        );
    }

    private function GetSharedMediaPath(string $Mode): string
    {
        $root = dirname(__DIR__);
        return $root . DIRECTORY_SEPARATOR . 'LCNAlarmanlageMediaServer' . DIRECTORY_SEPARATOR . ($Mode === 'mp4' ? 'ALARM.mp4' : 'ALARM_DLNA.mpeg');
    }

    private function XmlEscape(string $Value): string
    {
        return htmlspecialchars($Value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function ClearTVOwnership(): void
    {
        $this->SetTimerInterval('TVWakeRetry', 0);
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->StopAllTVVideoTimers();
        $this->WriteAttributeBoolean('TVOwnedByAlarm', false);
        $this->WriteAttributeString('TVOwnerSessionID', '');
        $this->WriteAttributeInteger('TVWakeAttempts', 0);
        $this->WriteAttributeInteger('TVLastWakeAt', 0);
        $this->WriteAttributeBoolean('TVSeenOnDuringAlarm', false);
        $this->WriteAttributeInteger('TVOffDeadline', 0);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);
        $this->WriteAttributeInteger('VideoActive', 0);
        $this->WriteAttributeString('VideoSessionID', '');
        $this->WriteAttributeInteger('VideoAttempts', 0);
        $this->WriteAttributeInteger('VideoStatsWaitTicks', 0);
        $this->WriteAttributeInteger('VideoLoopFallbackPending', 0);
        $this->WriteAttributeInteger('VideoLoopRearmCount', 0);
        $this->WriteAttributeInteger('VideoLoopRearmFailures', 0);
    }

    private function BuildNotificationConfig(): array
    {
        $warnings = [];
        $pushRequested = $this->ReadPropertyBoolean('PushEnabled');
        $pushVisualizationID = $this->ReadPropertyInteger('PushVisualizationID');
        $pushEnabled = false;
        if ($pushRequested) {
            if ($pushVisualizationID <= 0 || !IPS_InstanceExists($pushVisualizationID)) {
                $warnings[] = 'Push aktiviert, aber keine gültige Kachelvisualisierung ausgewählt';
            } else {
                $pushEnabled = true;
            }
        }

        $emailRequested = $this->ReadPropertyBoolean('EmailEnabled');
        $smtpID = $this->ReadPropertyInteger('SMTPInstanceID');
        $recipients = $this->ParseEmailRecipients($this->ReadPropertyString('EmailRecipients'));
        $emailEnabled = false;
        if ($emailRequested) {
            if ($smtpID <= 0 || !IPS_InstanceExists($smtpID)) {
                $warnings[] = 'E-Mail aktiviert, aber keine gültige SMTP-Instanz ausgewählt';
            } elseif ($recipients === []) {
                $warnings[] = 'E-Mail aktiviert, aber keine gültige Empfängeradresse vorhanden';
            } else {
                $emailEnabled = true;
            }
        }

        $emailAckRequested = $this->ReadPropertyBoolean('EmailAcknowledgeEnabled');
        $emailAckBaseURL = $this->NormalizeEmailAckBaseURL($this->ReadPropertyString('EmailAcknowledgeBaseURL'));
        $emailAckEnabled = false;
        if ($emailAckRequested) {
            if (!$emailEnabled) {
                $warnings[] = 'E-Mail-Quittierung aktiviert, aber der E-Mail-Versand ist nicht vollständig konfiguriert';
            } elseif ($emailAckBaseURL === '') {
                $warnings[] = 'E-Mail-Quittierung aktiviert, aber keine gültige HTTPS-Basis-URL eingetragen';
            } else {
                $emailAckEnabled = true;
            }
        }

        return [[
            'pushEnabled' => $pushEnabled,
            'pushVisualizationID' => $pushVisualizationID,
            'emailEnabled' => $emailEnabled,
            'smtpID' => $smtpID,
            'emailRecipients' => $recipients,
            'emailAckEnabled' => $emailAckEnabled,
            'emailAckBaseURL' => $emailAckBaseURL
        ], $warnings];
    }

    private function ParseEmailRecipients(string $Raw): array
    {
        $parts = preg_split('/[;,\r\n]+/', $Raw) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $mail = trim((string) $part);
            if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $key = strtolower($mail);
            $result[$key] = $mail;
        }
        return array_values($result);
    }

    private function NormalizeEmailAckBaseURL(string $Raw): string
    {
        $url = rtrim(trim($Raw), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return '';
        }
        if ((string) ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }

        return $url;
    }

    private function IssueEmailAckToken(string $SessionID): string
    {
        if ($SessionID === '' || !$this->IsActiveSession($SessionID)) {
            return '';
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Sicherer E-Mail-Quittierungstoken konnte nicht erzeugt werden: ' . $e->getMessage());
            return '';
        }

        $this->WriteAttributeString('EmailAckTokenHash', hash('sha256', $token));
        $this->WriteAttributeString('EmailAckSessionID', $SessionID);
        $this->WriteAttributeInteger('EmailAckExpiresAt', time() + self::ACK_TOKEN_LIFETIME_SECONDS);
        return $token;
    }

    private function ValidateEmailAckToken(string $SessionID, string $Token): array
    {
        if ($SessionID === '' || $Token === '') {
            return ['ok' => false, 'status' => 400, 'message' => 'Der Quittierungslink ist unvollständig.'];
        }

        $storedSession = $this->ReadAttributeString('EmailAckSessionID');
        $storedHash = $this->ReadAttributeString('EmailAckTokenHash');
        $expiresAt = $this->ReadAttributeInteger('EmailAckExpiresAt');

        if ($storedSession === '' || $storedHash === '' || $storedSession !== $SessionID) {
            return ['ok' => false, 'status' => 410, 'message' => 'Dieser Quittierungslink gehört nicht mehr zur aktuellen Alarm-Session.'];
        }
        if ($expiresAt <= 0 || time() > $expiresAt) {
            $this->InvalidateEmailAckToken($SessionID);
            return ['ok' => false, 'status' => 410, 'message' => 'Dieser Quittierungslink ist abgelaufen.'];
        }
        if (!hash_equals($storedHash, hash('sha256', $Token))) {
            return ['ok' => false, 'status' => 403, 'message' => 'Der Sicherheitstoken ist ungültig.'];
        }

        $session = $this->ReadSession('CurrentSession');
        if (
            (string) ($session['id'] ?? '') !== $SessionID
            || (string) ($session['state'] ?? self::SESSION_NONE) !== self::SESSION_ACTIVE
        ) {
            return ['ok' => false, 'status' => 409, 'message' => 'Der Alarm wurde bereits beendet oder quittiert.'];
        }

        return ['ok' => true, 'status' => 200, 'message' => 'OK'];
    }

    private function InvalidateEmailAckToken(string $SessionID): void
    {
        $storedSession = $this->ReadAttributeString('EmailAckSessionID');
        if ($SessionID !== '' && $storedSession !== '' && $storedSession !== $SessionID) {
            return;
        }
        $this->WriteAttributeString('EmailAckTokenHash', '');
        $this->WriteAttributeString('EmailAckSessionID', '');
        $this->WriteAttributeInteger('EmailAckExpiresAt', 0);
    }

    private function BuildAlarmEmailBody(string $SessionID, string $SensorName, string $Time, string $AckURL): string
    {
        $session = htmlspecialchars($SessionID, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sensor = htmlspecialchars($SensorName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $time = htmlspecialchars($Time, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $button = '';
        $hint = 'Zum Quittieren die Symcon-App oder einen freigegebenen LCN-Lichtschalter verwenden.';
        if ($AckURL !== '') {
            $url = htmlspecialchars($AckURL, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $button = '<p style="margin:24px 0"><a href="' . $url . '" style="display:inline-block;background:#d32f2f;color:#fff;text-decoration:none;font-weight:700;padding:13px 20px;border-radius:8px">Alarm quittieren</a></p>';
            $hint = 'Der Link öffnet zuerst eine Bestätigungsseite. Ein bloßer Linkabruf quittiert den Alarm nicht.';
        }

        return '<html><body style="font-family:Arial,sans-serif;color:#222;line-height:1.5">'
            . '<h2 style="color:#d32f2f">ALARM AUSGELÖST!</h2>'
            . '<p><strong>Zeit:</strong> ' . $time . '<br>'
            . '<strong>Erstauslöser:</strong> ' . $sensor . '<br>'
            . '<strong>Alarm-ID:</strong> ' . $session . '</p>'
            . $button
            . '<p>' . htmlspecialchars($hint, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
            . '<p>Die Alarmanlage selbst bleibt nach einer Quittierung eingeschaltet und geht nach freien Meldern wieder in den Scharfzustand.</p>'
            . '</body></html>';
    }

    private function RenderEmailAckPage(string $Title, string $Message, string $SessionID, string $Token, bool $ShowConfirm): string
    {
        $title = htmlspecialchars($Title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = htmlspecialchars($Message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $session = htmlspecialchars($SessionID, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $token = htmlspecialchars($Token, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $form = '';
        if ($ShowConfirm) {
            $form = '<form method="post" style="margin-top:24px">'
                . '<input type="hidden" name="session" value="' . $session . '">'
                . '<input type="hidden" name="token" value="' . $token . '">'
                . '<button type="submit" style="width:100%;border:0;border-radius:8px;background:#d32f2f;color:#fff;padding:14px;font-size:17px;font-weight:700;cursor:pointer">Alarm jetzt quittieren</button>'
                . '</form>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title></head>'
            . '<body style="margin:0;background:#f3f3f3;font-family:Arial,sans-serif;color:#222">'
            . '<main style="max-width:520px;margin:40px auto;padding:22px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.12)">'
            . '<h2 style="margin-top:0">' . $title . '</h2>'
            . '<p style="line-height:1.55">' . $message . '</p>'
            . $form
            . '</main></body></html>';
    }

    private function ReadNotificationConfig(): array
    {
        $decoded = json_decode($this->GetBuffer('NotificationConfig'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function QueueAlarmNotifications(string $SessionID): void
    {
        if ($SessionID === '') {
            return;
        }

        $session = $this->ReadSession('CurrentSession');
        if ((string) ($session['id'] ?? '') !== $SessionID) {
            return;
        }

        $config = $this->ReadNotificationConfig();
        $queue = [];
        $sensorName = (string) ($session['firstSensorName'] ?? '-');
        $startedAt = (float) ($session['startedAt'] ?? microtime(true));
        $time = $this->FormatTimestamp($startedAt);

        if ((bool) ($config['pushEnabled'] ?? false)) {
            $queue[] = [
                'type' => 'push',
                'sessionID' => $SessionID,
                'visualizationID' => (int) ($config['pushVisualizationID'] ?? 0),
                'title' => 'ALARM AUSGELÖST!',
                'text' => 'Bewegung: ' . $sensorName . ' · ' . $time
            ];
        }

        if ((bool) ($config['emailEnabled'] ?? false)) {
            $subject = 'ALARM ausgelöst!';
            $ackURL = '';
            if ((bool) ($config['emailAckEnabled'] ?? false)) {
                $token = $this->IssueEmailAckToken($SessionID);
                if ($token !== '') {
                    $baseURL = rtrim((string) ($config['emailAckBaseURL'] ?? ''), '/');
                    $ackURL = $baseURL
                        . '/hook/lcnalarm/' . $this->InstanceID
                        . '?session=' . rawurlencode($SessionID)
                        . '&token=' . rawurlencode($token);
                }
            }

            $body = $this->BuildAlarmEmailBody($SessionID, $sensorName, $time, $ackURL);
            foreach ((array) ($config['emailRecipients'] ?? []) as $recipient) {
                $queue[] = [
                    'type' => 'email',
                    'sessionID' => $SessionID,
                    'smtpID' => (int) ($config['smtpID'] ?? 0),
                    'recipient' => (string) $recipient,
                    'subject' => $subject,
                    'body' => $body
                ];
            }
        }

        if ($queue === []) {
            return;
        }

        // Pro Session wird diese Warteschlange nur beim ersten Alarmtrigger erzeugt.
        // Sie ist kurzlebig und wird nach einem Neustart NICHT rekonstruiert, damit
        // ein bereits versendeter Alarm nicht doppelt zugestellt wird.
        $this->SetBuffer('NotificationQueue', $this->Encode($queue));
        $this->SetTimerInterval('NotificationQueue', 1);
    }

    private function RecordNotificationResult(string $SessionID, array $Item, bool $Success, string $Error): void
    {
        if ($SessionID === '') {
            return;
        }

        if (!$this->AcquireEngineLock()) {
            // Benachrichtigungs-Metadaten sind nicht sicherheitskritisch. Ein
            // gescheiterter Logeintrag darf den sichtbaren Alarmstatus niemals
            // auf STÖRUNG setzen oder die Alarm-Engine beeinflussen.
            IPS_LogMessage(
                'LCN Alarmanlage #' . $this->InstanceID,
                'Benachrichtigungsergebnis konnte wegen Semaphore-Kollision nicht gespeichert werden'
            );
            return;
        }

        try {
            foreach (['CurrentSession', 'LastSession'] as $attribute) {
                $session = $this->ReadSession($attribute);
                if ((string) ($session['id'] ?? '') !== $SessionID) {
                    continue;
                }
                if (!isset($session['notifications']) || !is_array($session['notifications'])) {
                    $session['notifications'] = [];
                }
                $session['notifications'][] = [
                    'ts' => microtime(true),
                    'type' => (string) ($Item['type'] ?? ''),
                    'recipient' => (string) ($Item['recipient'] ?? ''),
                    'success' => $Success,
                    'error' => $Error
                ];
                $this->WriteSession($attribute, $session);
                break;
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }
    }

    private function BuildSensorMap(): array
    {
        $decoded = json_decode($this->ReadPropertyString('Sensors'), true);
        if (!is_array($decoded)) {
            return [[], ['Sensorliste ist ungültig']];
        }

        $map = [];
        $errors = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row) || !(bool) ($row['Enabled'] ?? false)) {
                continue;
            }

            $variableID = (int) ($row['VariableID'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                $errors[] = 'Melderzeile ' . ($index + 1) . ': Variable fehlt';
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                $errors[] = 'Melderzeile ' . ($index + 1) . ': keine Boolean-Variable';
                continue;
            }

            if (isset($map[(string) $variableID])) {
                $errors[] = 'Variable #' . $variableID . ' ist doppelt eingetragen';
                continue;
            }

            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '') {
                $name = IPS_GetName($variableID);
            }

            $map[(string) $variableID] = [
                'id' => $variableID,
                'name' => $name
            ];
        }

        return [$map, $errors];
    }

    /**
     * Baut die reine Bewegungsmelder-Statusliste ohne neue Symcon-Variablen.
     * Sichere Quelle sind immer die bereits konfigurierten GUS. Zusaetzlich werden
     * native LCN-Unit-Instanzen automatisch aufgenommen, wenn ihre Bezeichnung klar
     * auf Bewegungsmelder/GUS hinweist und sie eine Boolean-Statusvariable besitzen.
     */
    private function BuildMotionStatusMap(array $SensorMap): array
    {
        $map = [];

        // Alle konfigurierten Alarm-GUS muessen immer in der Statusliste stehen,
        // unabhaengig davon, ob ihre Bezeichnung dem automatischen Namensfilter folgt.
        foreach ($SensorMap as $key => $sensor) {
            $variableID = (int) ($sensor['id'] ?? $key);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                continue;
            }
            $map[(string) $variableID] = [
                'id' => $variableID,
                'instanceID' => (int) IPS_GetParent($variableID),
                'name' => (string) ($sensor['name'] ?? ('GUS #' . $variableID))
            ];
        }

        try {
            $instances = IPS_GetInstanceListByModuleID(self::LCN_UNIT_MODULE_GUID);
        } catch (Throwable $e) {
            $this->SendDebug('MotionStatus', 'LCN-Unit-Instanzen konnten nicht ermittelt werden: ' . $e->getMessage(), 0);
            $instances = [];
        }

        foreach ($instances as $instanceID) {
            $instanceID = (int) $instanceID;
            if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
                continue;
            }

            $name = trim((string) IPS_GetName($instanceID));
            $nameLower = strtolower($name);
            if (
                strpos($nameLower, 'bewegungsmelder') === false
                && strpos($nameLower, 'bewegungsmeder') === false
                && preg_match('/(^|[^a-z0-9])gus([^a-z0-9]|$)/i', $name) !== 1
            ) {
                continue;
            }

            $variableID = $this->FindBooleanStatusVariable($instanceID);
            if ($variableID <= 0 || isset($map[(string) $variableID])) {
                continue;
            }

            $map[(string) $variableID] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'name' => ($name !== '') ? $name : ('GUS #' . $variableID)
            ];
        }

        uasort($map, static function (array $a, array $b): int {
            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $map;
    }

    private function FindBooleanStatusVariable(int $InstanceID): int
    {
        foreach (['Status', 'STATUS'] as $ident) {
            try {
                $candidate = (int) IPS_GetObjectIDByIdent($ident, $InstanceID);
                if ($candidate > 0 && IPS_VariableExists($candidate)) {
                    $variable = IPS_GetVariable($candidate);
                    if ((int) ($variable['VariableType'] ?? -1) === VARIABLETYPE_BOOLEAN) {
                        return $candidate;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $children = IPS_GetChildrenIDs($InstanceID);
        } catch (Throwable $e) {
            return 0;
        }

        foreach ($children as $childID) {
            $childID = (int) $childID;
            if ($childID <= 0 || !IPS_VariableExists($childID)) {
                continue;
            }
            try {
                $variable = IPS_GetVariable($childID);
                if ((int) ($variable['VariableType'] ?? -1) !== VARIABLETYPE_BOOLEAN) {
                    continue;
                }
                $object = IPS_GetObject($childID);
                $ident = strtolower((string) ($object['ObjectIdent'] ?? ''));
                $childName = strtolower((string) ($object['ObjectName'] ?? ''));
                if ($ident === 'status' || $childName === 'status') {
                    return $childID;
                }
            } catch (Throwable $e) {
            }
        }

        return 0;
    }

    private function BuildPanicLightMap(array $SensorMap): array
    {
        $decoded = json_decode($this->ReadPropertyString('AcknowledgeLights'), true);
        if (!is_array($decoded)) {
            return [[], ['Panik-Lichterliste ist ungültig']];
        }

        $map = [];
        $errors = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row) || !(bool) ($row['Enabled'] ?? false)) {
                continue;
            }

            $variableID = (int) ($row['VariableID'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': Variable fehlt';
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': keine Boolean-Variable';
                continue;
            }

            $object = IPS_GetObject($variableID);
            $instanceID = (int) ($object['ParentID'] ?? 0);
            if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': Status gehört zu keiner Instanz';
                continue;
            }
            $instance = IPS_GetInstance($instanceID);
            if (strtoupper((string) ($instance['ModuleInfo']['ModuleID'] ?? '')) !== strtoupper(self::LCN_LIGHT_MODULE_GUID)) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': Variable ist kein LCN Licht -> Status';
                continue;
            }

            $ident = (string) (IPS_GetObject($variableID)['ObjectIdent'] ?? '');
            if (strcasecmp($ident, 'Status') !== 0) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': Variable ist nicht der Status der LCN-Lichtinstanz';
                continue;
            }

            if (isset($SensorMap[(string) $variableID])) {
                $errors[] = 'Variable #' . $variableID . ' darf nicht zugleich GUS und Panik-Licht sein';
                continue;
            }
            if (isset($map[(string) $variableID])) {
                $errors[] = 'Panik-Licht Variable #' . $variableID . ' ist doppelt eingetragen';
                continue;
            }

            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '') {
                $name = IPS_GetName($instanceID);
            }

            $map[(string) $variableID] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'name' => $name
            ];
        }

        return [$map, $errors];
    }

    private function BuildAllLCNLightMap(array $SensorMap, array $PanicLightMap): array
    {
        $map = [];

        try {
            $instances = IPS_GetInstanceListByModuleID(self::LCN_LIGHT_MODULE_GUID);
        } catch (Throwable $e) {
            $this->SendDebug('LCNLightDiscovery', 'LCNLight-Instanzen konnten nicht ermittelt werden: ' . $e->getMessage(), 0);
            $instances = [];
        }

        foreach ($instances as $instanceID) {
            $instanceID = (int) $instanceID;
            if ($instanceID <= 0 || !IPS_InstanceExists($instanceID)) {
                continue;
            }

            $variableID = @IPS_GetObjectIDByIdent('Status', $instanceID);
            if ($variableID === false || $variableID <= 0 || !IPS_VariableExists((int) $variableID)) {
                continue;
            }
            $variableID = (int) $variableID;
            $variable = IPS_GetVariable($variableID);
            if ((int) ($variable['VariableType'] ?? -1) !== VARIABLETYPE_BOOLEAN) {
                continue;
            }
            if (isset($SensorMap[(string) $variableID])) {
                continue;
            }

            $map[(string) $variableID] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'name' => IPS_GetName($instanceID),
                'panic' => isset($PanicLightMap[(string) $variableID])
            ];
        }

        // Explizit konfigurierte Paniklichter bleiben auch dann registriert, falls
        // Symcon die Instanzliste während ApplyChanges kurzzeitig unvollständig liefert.
        foreach ($PanicLightMap as $key => $light) {
            if (!isset($map[(string) $key])) {
                $light['panic'] = true;
                $map[(string) $key] = $light;
            }
        }

        return $map;
    }

    private function BuildPanicConfig(): array
    {
        $variableID = $this->ReadPropertyInteger('PanicGroupVariableID');
        if ($variableID <= 0) {
            return [0, []];
        }
        if (!IPS_VariableExists($variableID)) {
            return [0, ['Panikgruppe: Statusvariable fehlt']];
        }

        $variable = IPS_GetVariable($variableID);
        if ((int) $variable['VariableType'] !== VARIABLETYPE_INTEGER) {
            return [0, ['Panikgruppe: Statusvariable muss Integer sein']];
        }

        // Die LCNLightGroup-Statusvariable ist eine reine Integer-Rueckmeldung
        // (0=AUS, 1=EIN, 2=GEMISCHT, 3=UNBEKANNT) und muss keine VariableAction
        // besitzen. Sie dient hier nur als optionale Referenz/Kontrollanzeige.
        return [$variableID, []];
    }

    private function UnregisterOldSensorMessages(): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredSensorIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }

        foreach ($old as $sensorID) {
            $sensorID = (int) $sensorID;
            if ($sensorID <= 0) {
                continue;
            }
            $this->UnregisterMessage($sensorID, VM_UPDATE);
            $this->UnregisterMessage($sensorID, OM_UNREGISTER);
            $this->UnregisterReference($sensorID);
        }

        $this->WriteAttributeString('RegisteredSensorIDs', '[]');
    }

    private function UnregisterOldMotionStatusMessages(): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredMotionStatusIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }

        foreach ($old as $variableID) {
            $variableID = (int) $variableID;
            if ($variableID <= 0) {
                continue;
            }
            $this->UnregisterMessage($variableID, VM_UPDATE);
            $this->UnregisterMessage($variableID, OM_UNREGISTER);
            $this->UnregisterReference($variableID);
        }

        $this->WriteAttributeString('RegisteredMotionStatusIDs', '[]');
    }

    private function UnregisterOldAcknowledgeMessages(): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredAcknowledgeIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }

        foreach ($old as $variableID) {
            $variableID = (int) $variableID;
            if ($variableID <= 0) {
                continue;
            }
            $this->UnregisterMessage($variableID, VM_UPDATE);
            $this->UnregisterMessage($variableID, OM_UNREGISTER);
            $this->UnregisterReference($variableID);
        }

        $this->WriteAttributeString('RegisteredAcknowledgeIDs', '[]');
    }

    private function UnregisterOldPanicReference(): void
    {
        $old = $this->ReadAttributeInteger('RegisteredPanicVariableID');
        if ($old > 0) {
            $this->UnregisterMessage($old, OM_UNREGISTER);
            $this->UnregisterReference($old);
        }
        $this->WriteAttributeInteger('RegisteredPanicVariableID', 0);
    }

    private function ReadSensorMap(): array
    {
        $decoded = json_decode($this->GetBuffer('SensorMap'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ReadMotionStatusMap(): array
    {
        $decoded = json_decode($this->GetBuffer('MotionStatusMap'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ReadPanicLightMap(): array
    {
        $decoded = json_decode($this->GetBuffer('PanicLightMap'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function CaptureLCNLightSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->ReadAcknowledgeMap() as $key => $light) {
            $instanceID = (int) ($light['instanceID'] ?? 0);
            $variableID = (int) ($light['id'] ?? 0);
            $state = $this->ReadLCNLightState($instanceID, $variableID);
            if ($state === null) {
                $this->SendDebug('LightSnapshot', 'Licht #' . $variableID . ' hat unbekannten Istzustand und wird nicht automatisch verändert.', 0);
                continue;
            }

            $snapshot[(string) $key] = [
                'id' => $variableID,
                'instanceID' => $instanceID,
                'name' => (string) ($light['name'] ?? IPS_GetName($instanceID)),
                'state' => $state
            ];
        }
        return $snapshot;
    }

    private function ReadLightSnapshotForSession(string $SessionID): array
    {
        if ($SessionID === '') {
            return [];
        }

        foreach (['CurrentSession', 'LastSession'] as $attribute) {
            $session = $this->ReadSession($attribute);
            if ((string) ($session['id'] ?? '') !== $SessionID) {
                continue;
            }
            $snapshot = $session['lightSnapshot'] ?? [];
            return is_array($snapshot) ? $snapshot : [];
        }
        return [];
    }

    private function ReadLCNLightState(int $InstanceID, int $VariableID): ?bool
    {
        if ($InstanceID <= 0 || !IPS_InstanceExists($InstanceID)) {
            return null;
        }
        if ($VariableID <= 0 || !IPS_VariableExists($VariableID)) {
            return null;
        }

        try {
            $state = (int) LCL_GetPowerState($InstanceID);
            if ($state < 0) {
                return null;
            }
            return $state === 1;
        } catch (Throwable $e) {
            $this->SendDebug('LCNLightState', 'Instanz #' . $InstanceID . ': ' . $e->getMessage(), 0);
            return null;
        }
    }

    private function ReadAcknowledgeMap(): array
    {
        $decoded = json_decode($this->GetBuffer('AcknowledgeMap'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ReadAcknowledgeStates(): array
    {
        $decoded = json_decode($this->GetBuffer('AcknowledgeStates'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function PanicGroupVariableID(): int
    {
        return (int) $this->GetBuffer('PanicGroupVariableID');
    }

    private function IsSensorVariable(int $VariableID): bool
    {
        return isset($this->ReadSensorMap()[(string) $VariableID]);
    }

    private function IsMotionStatusVariable(int $VariableID): bool
    {
        return isset($this->ReadMotionStatusMap()[(string) $VariableID])
            && !$this->IsSensorVariable($VariableID);
    }

    private function IsAcknowledgeVariable(int $VariableID): bool
    {
        return isset($this->ReadAcknowledgeMap()[(string) $VariableID]);
    }

    private function AcknowledgeLightName(int $VariableID): string
    {
        $map = $this->ReadAcknowledgeMap();
        $key = (string) $VariableID;
        if (isset($map[$key]['name'])) {
            return (string) $map[$key]['name'];
        }
        return IPS_VariableExists($VariableID) ? IPS_GetName($VariableID) : ('#' . $VariableID);
    }

    private function ReadSensorStates(): array
    {
        $decoded = json_decode($this->GetBuffer('SensorStates'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Einmalige LCN-Statusanforderung pro tatsächlichem Aktormodul. Es wird nicht pro
     * Sensor gepollt. Erwartet werden nur native LCN-Booleanvariablen, deren technische
     * Kette LCN Unit -> ConnectionID -> LCN Modul eindeutig validiert werden kann.
     */
    private function RequestStartupSensorStatus(array $SensorMap): array
    {
        $expected = [];
        $actorModules = [];

        foreach ($SensorMap as $key => $sensor) {
            $variableID = (int) ($sensor['id'] ?? $key);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                continue;
            }

            // Jeder konfigurierte GUS muss nach einem Start mindestens eine frische
            // VM_UPDATE-Bestätigung liefern. Auch wenn seine technische LCN-Kette
            // unerwartet nicht auflösbar ist, darf er deshalb NICHT stillschweigend
            // aus der Erwartungsliste fallen. In diesem Fehlerfall bleibt die Anlage
            // fail-safe nicht auslösebereit, bis der Sensor selbst aktualisiert wurde.
            $expected[$variableID] = $variableID;

            try {
                $object = IPS_GetObject($variableID);
                $unitID = (int) ($object['ParentID'] ?? 0);
                if ($unitID <= 0 || !IPS_InstanceExists($unitID)) {
                    continue;
                }

                $unit = IPS_GetInstance($unitID);
                if (strtoupper((string) ($unit['ModuleInfo']['ModuleID'] ?? '')) !== strtoupper(self::LCN_UNIT_MODULE_GUID)) {
                    continue;
                }

                $actorModuleID = (int) ($unit['ConnectionID'] ?? 0);
                if ($actorModuleID <= 0 || !IPS_InstanceExists($actorModuleID)) {
                    continue;
                }

                $actor = IPS_GetInstance($actorModuleID);
                if (strtoupper((string) ($actor['ModuleInfo']['ModuleID'] ?? '')) !== strtoupper(self::LCN_MODULE_MODULE_GUID)) {
                    continue;
                }

                $actorModules[$actorModuleID] = $actorModuleID;
            } catch (Throwable $e) {
                $this->SendDebug('StartupSync', 'Sensor #' . $variableID . ': ' . $e->getMessage(), 0);
            }
        }

        // Erwartungsliste VOR dem Request veröffentlichen, damit auch eine sehr
        // schnelle/synchrone VM_UPDATE-Antwort bereits gezählt werden kann.
        if ($this->GetBuffer('RuntimeReady') !== '1' && (int) $this->GetBuffer('StartupSyncAttempt') === 0) {
            $this->SetBuffer('StartupExpectedSensorIDs', $this->Encode(array_values($expected)));
        }

        foreach ($actorModules as $actorModuleID) {
            try {
                if (!function_exists('LCN_RequestStatus') || !LCN_RequestStatus((int) $actorModuleID)) {
                    $this->SendDebug('StartupSync', 'LCN_RequestStatus #' . $actorModuleID . ' nicht bestätigt', 0);
                }
            } catch (Throwable $e) {
                $this->SendDebug('StartupSync', 'LCN_RequestStatus #' . $actorModuleID . ': ' . $e->getMessage(), 0);
            }
        }

        return [
            'expectedSensorIDs' => array_values($expected),
            'actorModuleIDs' => array_values($actorModules)
        ];
    }

    private function ReadStartupIDBuffer(string $Name): array
    {
        $decoded = json_decode($this->GetBuffer($Name), true);
        if (!is_array($decoded)) {
            return [];
        }
        $ids = [];
        foreach ($decoded as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function IsStartupSensorSyncComplete(): bool
    {
        $expected = $this->ReadStartupIDBuffer('StartupExpectedSensorIDs');
        if ($expected === []) {
            return true;
        }
        $seen = $this->ReadStartupIDBuffer('StartupSeenSensorIDs');
        return array_diff($expected, $seen) === [];
    }

    private function MarkStartupSensorSeen(int $VariableID): void
    {
        if ($VariableID <= 0) {
            return;
        }

        $expected = $this->ReadStartupIDBuffer('StartupExpectedSensorIDs');
        if ($expected === [] || !in_array($VariableID, $expected, true)) {
            return;
        }

        $seen = $this->ReadStartupIDBuffer('StartupSeenSensorIDs');
        if (in_array($VariableID, $seen, true)) {
            return;
        }

        $seen[] = $VariableID;
        $this->SetBuffer('StartupSeenSensorIDs', $this->Encode(array_values(array_unique($seen))));

        if ($this->IsStartupSensorSyncComplete()) {
            $this->SetBuffer('StartupSyncIncomplete', '0');
            // Falls die letzte erwartete Rückmeldung erst nach Ende der Schutzphase
            // eintrifft, wird der Zustand nochmals ohne Alarmtrigger rekonstruiert.
            if ($this->GetBuffer('RuntimeReady') === '1') {
                $this->SetTimerInterval('StartupGuard', 1);
            }
        }
    }

    private function AreAllMonitoredSensorsClear(array $States): bool
    {
        foreach ($this->ReadSensorMap() as $sensor) {
            $variableID = (int) ($sensor['id'] ?? 0);
            if ($variableID <= 0 || !$this->IsSensorWatchEnabled($variableID)) {
                continue;
            }
            if ((bool) ($States[(string) $variableID] ?? false)) {
                return false;
            }
        }
        return true;
    }

    private function CountMonitoredSensors(): int
    {
        $count = 0;
        foreach ($this->ReadSensorMap() as $sensor) {
            $variableID = (int) ($sensor['id'] ?? 0);
            if ($variableID > 0 && $this->IsSensorWatchEnabled($variableID)) {
                $count++;
            }
        }
        return $count;
    }

    private function SensorWatchIdent(int $VariableID): string
    {
        return 'WatchSensor' . $VariableID;
    }

    private function IsSensorWatchEnabled(int $VariableID): bool
    {
        if ($VariableID <= 0) {
            return false;
        }
        $ident = $this->SensorWatchIdent($VariableID);
        try {
            return (bool) $this->GetValue($ident);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function EnsureSensorWatchVariables(array $SensorMap): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredWatchSensorIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }
        $currentIDs = array_map('intval', array_keys($SensorMap));

        foreach ($old as $oldID) {
            $oldID = (int) $oldID;
            if ($oldID <= 0 || in_array($oldID, $currentIDs, true)) {
                continue;
            }
            try {
                $this->UnregisterVariable($this->SensorWatchIdent($oldID));
            } catch (Throwable $e) {
                $this->SendDebug('SensorWatch', 'Alter GUS-Schalter konnte nicht entfernt werden: ' . $e->getMessage(), 0);
            }
        }

        $position = 60;
        foreach ($SensorMap as $sensor) {
            $variableID = (int) ($sensor['id'] ?? 0);
            $name = (string) ($sensor['name'] ?? ('GUS #' . $variableID));
            if ($variableID <= 0) {
                continue;
            }
            $ident = $this->SensorWatchIdent($variableID);
            $created = $this->RegisterVariableBoolean(
                $ident,
                $name,
                ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
                $position
            );
            if ($created) {
                $this->SetValue($ident, true);
            }
            $this->EnableAction($ident);
            $watchID = $this->GetIDForIdent($ident);
            IPS_SetName($watchID, $name);
            IPS_SetPosition($watchID, $position);
            $position++;
        }

        $this->WriteAttributeString('RegisteredWatchSensorIDs', $this->Encode($currentIDs));
    }

    private function SetStaticVariablePositions(): void
    {
        $positions = [
            'AlarmActive' => 200,
            'Acknowledge' => 210,
            'FirstTrigger' => 220,
            'LastMovement' => 230,
            'MotionCount' => 240,
            'MotionLog' => 250,
            'LastAlarm' => 260
        ];
        foreach ($positions as $ident => $position) {
            try {
                IPS_SetPosition($this->GetIDForIdent($ident), $position);
            } catch (Throwable $e) {
            }
        }
    }

    private function SetSensorWatchEnabled(int $VariableID, bool $Enabled): void
    {
        $map = $this->ReadSensorMap();
        if (!isset($map[(string) $VariableID])) {
            throw new Exception('Unbekannter GUS-Schalter.');
        }

        $ident = $this->SensorWatchIdent($VariableID);
        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('SetSensorWatchEnabled #' . $VariableID);
            throw new Exception('GUS-Ueberwachung konnte wegen einer internen Zugriffskollision nicht geaendert werden.');
        }

        $scheduleRearm = false;
        $cancelRearm = false;
        $scheduleAlarmAfterrun = false;
        $cancelAlarmAfterrun = false;
        $alarmAfterrunMs = 0;
        try {
            $this->SetValue($ident, $Enabled);
            $states = $this->ReadSensorStates();
            $session = $this->ReadSession('CurrentSession');
            $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);

            if ($sessionState === self::SESSION_ACTIVE) {
                if ($this->IsStartupSensorSyncComplete() && $this->AreAllMonitoredSensorsClear($states)) {
                    $duration = max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', time() + $duration);
                    $scheduleAlarmAfterrun = true;
                    $alarmAfterrunMs = $duration * 1000;
                } else {
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                    $cancelAlarmAfterrun = true;
                }
            } elseif ($sessionState === self::SESSION_REARM_WAIT) {
                if ($this->IsStartupSensorSyncComplete() && $this->AreAllMonitoredSensorsClear($states)) {
                    $this->WriteAttributeInteger(
                        'RearmNotBefore',
                        time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                    );
                    $scheduleRearm = true;
                } else {
                    $this->WriteAttributeInteger('RearmNotBefore', 0);
                    $cancelRearm = true;
                }
            } elseif ($sessionState === self::SESSION_NONE && (bool) $this->GetValue('Arm')) {
                $ready = $this->GetBuffer('RuntimeReady') === '1'
                    && $this->IsStartupSensorSyncComplete()
                    && $this->CountMonitoredSensors() > 0
                    && $this->AreAllMonitoredSensorsClear($states);
                $this->WriteAttributeBoolean('ArmedReady', $ready);
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($cancelAlarmAfterrun) {
            $this->SetTimerInterval('AlarmTimeout', 0);
        }
        if ($scheduleAlarmAfterrun) {
            $this->SetTimerInterval('AlarmTimeout', max(1, $alarmAfterrunMs));
        }
        if ($cancelRearm) {
            $this->SetTimerInterval('RearmTimeout', 0);
            $this->SetTimerInterval('RearmDisplay', 0);
        }
        if ($scheduleRearm) {
            $this->ScheduleRearmFromAttribute();
        }
        $this->RefreshSummary();
        $this->RefreshDisplay();
    }

    private function RefreshSummary(): void
    {
        $sensorMap = $this->ReadSensorMap();
        $acknowledgeMap = $this->ReadAcknowledgeMap();
        $notificationConfig = $this->ReadNotificationConfig();
        $tvConfig = $this->ReadTVConfig();

        $summary = count($sensorMap) . ' GUS · ' . $this->CountMonitoredSensors() . ' aktiv';
        if ($acknowledgeMap !== []) {
            $summary .= ' · Panik ' . count($acknowledgeMap) . ' Lichter';
        }
        if ($this->PanicGroupVariableID() > 0) {
            $summary .= ' · Gruppenstatus';
        }
        if ((bool) ($notificationConfig['pushEnabled'] ?? false)) {
            $summary .= ' · Push';
        }
        $notificationWarnings = json_decode($this->GetBuffer('NotificationWarnings'), true);
        if (is_array($notificationWarnings) && $notificationWarnings !== []) {
            $summary .= ' · Hinweis Benachr.';
        }
        $mailCount = count((array) ($notificationConfig['emailRecipients'] ?? []));
        if ((bool) ($notificationConfig['emailEnabled'] ?? false) && $mailCount > 0) {
            $summary .= ' · Mail ' . $mailCount;
        }
        if ((bool) ($tvConfig['enabled'] ?? false)) {
            $summary .= ' · TV';
        }
        $tvWarnings = json_decode($this->GetBuffer('TVWarnings'), true);
        if (is_array($tvWarnings) && $tvWarnings !== []) {
            $summary .= ' · Hinweis TV';
        }
        $this->SetSummary($summary);
    }

    private function SensorName(int $VariableID): string
    {
        $map = $this->ReadSensorMap();
        $key = (string) $VariableID;
        if (isset($map[$key]['name'])) {
            return (string) $map[$key]['name'];
        }
        return IPS_VariableExists($VariableID) ? IPS_GetName($VariableID) : ('#' . $VariableID);
    }

    private function ReadSession(string $Attribute): array
    {
        $decoded = json_decode($this->ReadAttributeString($Attribute), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function WriteSession(string $Attribute, array $Session): void
    {
        $this->WriteAttributeString($Attribute, $this->Encode($Session === [] ? new stdClass() : $Session));
    }

    private function Encode(mixed $Value): string
    {
        $encoded = json_encode($Value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new Exception('Interner JSON-Fehler: ' . json_last_error_msg());
        }
        return $encoded;
    }

    private function AcquireEngineLock(): bool
    {
        if (IPS_SemaphoreEnter($this->EngineSemaphoreName(), 2000)) {
            return true;
        }
        // Zweiter Versuch ohne sleep(): kritischer Bereich ist bewusst sehr kurz.
        return IPS_SemaphoreEnter($this->EngineSemaphoreName(), 3000);
    }

    private function EngineSemaphoreName(): string
    {
        return 'LCNAlarmanlage_' . $this->InstanceID;
    }

    private function ReportLockFailure(string $Context): void
    {
        $message = 'Semaphore konnte nicht übernommen werden: ' . $Context;
        IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, $message);
        $this->SendDebug('LockFailure', $message, 0);
        $this->SetValue('Status', 'STÖRUNG – interner Kollisionsschutz');
    }

    private function IsValidTime(string $Time): bool
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $Time)) {
            return false;
        }
        return true;
    }

    private function IsInsideSchedule(string $From, string $To): bool
    {
        if ($From === $To) {
            // Gleichstand wird aus Sicherheitsgründen als AUS interpretiert, nicht als 24h scharf.
            return false;
        }

        $current = ((int) date('H')) * 60 + (int) date('i');
        $from = $this->TimeToMinutes($From);
        $to = $this->TimeToMinutes($To);

        if ($from < $to) {
            return $current >= $from && $current < $to;
        }
        return $current >= $from || $current < $to;
    }

    private function TimeToMinutes(string $Time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $Time));
        return $hour * 60 + $minute;
    }

    private function NextOccurrence(DateTimeImmutable $Now, string $Time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $Time));
        $candidate = $Now->setTime($hour, $minute, 0);
        if ($candidate <= $Now) {
            $candidate = $candidate->modify('+1 day');
        }
        return $candidate;
    }

    private function FormatTimestamp(float $Timestamp): string
    {
        if ($Timestamp <= 0) {
            return '-';
        }

        $seconds = (int) floor($Timestamp);
        $milliseconds = (int) floor(($Timestamp - $seconds) * 1000);
        return date('d.m.Y H:i:s', $seconds) . sprintf('.%03d', $milliseconds);
    }
}
