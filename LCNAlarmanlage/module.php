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
        // Samsung-TV ist optional und nach einem Update bewusst AUS. Die Steuerung
        // erfolgt direkt ueber die bereits funktionierenden SamsungTizen-Funktionen,
        // niemals ueber den Alarmkern oder die PowerFix-Impulsvariable.
        $this->RegisterPropertyBoolean('TVEnabled', false);
        $this->RegisterPropertyInteger('TVInstanceID', 0);
        $this->RegisterPropertyInteger('TVStatusVariableID', 0);

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
        $this->RegisterAttributeString('RegisteredAcknowledgeIDs', '[]');
        $this->RegisterAttributeInteger('RegisteredPanicVariableID', 0);
        // TV-Laufzeitstatus ist strikt vom Alarmzustand getrennt. Er dient nur dazu,
        // einen vom Alarm gestarteten TV spaeter wieder sicher auszuschalten.
        $this->RegisterAttributeBoolean('TVOwnedByAlarm', false);
        $this->RegisterAttributeString('TVOwnerSessionID', '');
        $this->RegisterAttributeInteger('TVWakeAttempts', 0);
        $this->RegisterAttributeInteger('TVLastWakeAt', 0);
        $this->RegisterAttributeInteger('TVOffDeadline', 0);
        $this->RegisterAttributeInteger('TVOffFalseChecks', 0);

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
        $target = (bool) ($item['target'] ?? false);
        $sessionID = (string) ($item['sessionID'] ?? '');
        $reason = (string) ($item['reason'] ?? 'panic');

        // EIN-Befehle einer inzwischen quittierten/abgelaufenen Session duerfen
        // nicht nachlaufen. Ein alter AUS-Worker darf umgekehrt niemals eine
        // eventuell bereits neue aktive Alarm-Session dunkel schalten.
        if ($target) {
            if ($sessionID === '' || !$this->IsActiveSession($sessionID)) {
                $this->SetBuffer('PanicQueue', '[]');
                return;
            }
        } elseif ($this->HasAnyActiveSession()) {
            $this->SetBuffer('PanicQueue', '[]');
            return;
        }

        if ($variableID > 0 && IPS_VariableExists($variableID)) {
            try {
                $variable = IPS_GetVariable($variableID);
                if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                    throw new Exception('Zielvariable ist nicht Boolean');
                }
                if (!HasAction($variableID)) {
                    throw new Exception('Zielvariable besitzt keine Aktion');
                }

                $current = (bool) GetValue($variableID);
                if ($current !== $target) {
                    $ok = RequestActionEx($variableID, $target, 'LCN Alarmanlage');
                    if (!$ok) {
                        throw new Exception('RequestActionEx meldete FALSE');
                    }
                }
            } catch (Throwable $e) {
                IPS_LogMessage(
                    'LCN Alarmanlage #' . $this->InstanceID,
                    'Paniklicht #' . $variableID . ' ' . ($target ? 'EIN' : 'AUS') .
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

        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false) || !$this->ReadAttributeBoolean('TVOwnedByAlarm')) {
            $this->ClearTVOwnership();
            return;
        }

        // Ein neuer aktiver Alarm hat immer Vorrang. StartTVForAlarm uebernimmt
        // in diesem Fall die Ownership fuer die neue Session und stoppt den alten
        // Abschaltauftrag.
        $current = $this->ReadSession('CurrentSession');
        if (($current['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE) {
            return;
        }

        $deadline = $this->ReadAttributeInteger('TVOffDeadline');
        if ($deadline <= 0) {
            $deadline = time() + 60;
            $this->WriteAttributeInteger('TVOffDeadline', $deadline);
        }

        if ($this->TVIsOn($config)) {
            $this->WriteAttributeInteger('TVOffFalseChecks', 0);
            $this->SendTVPowerOff($config, 'off-check');
        } else {
            $falseChecks = $this->ReadAttributeInteger('TVOffFalseChecks') + 1;
            $this->WriteAttributeInteger('TVOffFalseChecks', $falseChecks);

            // Zwei bestaetigte AUS-Pruefungen und mindestens 30 s Abstand zum
            // letzten WakeUp verhindern, dass ein bereits gesendetes WOL-Paket
            // nach zu fruehem Ende der Ueberwachung doch noch einen TV hochfaehrt.
            $lastWake = $this->ReadAttributeInteger('TVLastWakeAt');
            $wakeSettled = $lastWake <= 0 || (time() - $lastWake) >= 30;
            if ($falseChecks >= 2 && $wakeSettled) {
                $this->ClearTVOwnership();
                return;
            }
        }

        if (time() >= $deadline) {
            $this->SendDebug('TV', 'AUS-Nachkontrolle nach 60 s beendet', 0);
            $this->ClearTVOwnership();
            return;
        }

        $this->SetTimerInterval('TVOffCheck', 10000);
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

    private function InitializeRuntime(): void
    {
        // Während der Rekonstruktion dürfen eintreffende Sensorupdates nur die Baseline
        // aktualisieren, aber niemals einen historischen Alarm erzeugen.
        $this->SetBuffer('RuntimeReady', '0');
        $this->SetBuffer('PanicQueue', '[]');
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

        $this->UnregisterOldSensorMessages();
        $this->UnregisterOldAcknowledgeMessages();
        $this->UnregisterOldPanicReference();

        [$sensorMap, $errors] = $this->BuildSensorMap();
        [$acknowledgeMap, $acknowledgeErrors] = $this->BuildAcknowledgeMap($sensorMap);
        [$panicVariableID, $panicErrors] = $this->BuildPanicConfig();
        [$notificationConfig, $notificationWarnings] = $this->BuildNotificationConfig();
        [$tvConfig, $tvWarnings] = $this->BuildTVConfig();
        $errors = array_merge($errors, $acknowledgeErrors, $panicErrors);

        $this->SetBuffer('SensorMap', $this->Encode($sensorMap));
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
            // Deaktivierte/ungueltige optionale TV-Funktion darf keinerlei alten
            // Nachlaufauftrag aus einer frueheren Konfiguration behalten.
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
            $this->ClearTVOwnership();
        }

        if ($sessionState === self::SESSION_ACTIVE) {
            if (!(bool) $this->GetValue('Arm')) {
                // Inkonsistenz nach Abbruch während einer Abschaltung: über denselben
                // kollisionsgeschützten Pfad wie jede andere Abschaltung rekonstruieren.
                $this->SetArmedInternal(false, 'restart-reconcile');
            } else {
                $this->SetValue('AlarmActive', true);
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->SetAlarmControlsVisible(true);
                // Alarmdauer ist eine Nachlaufzeit ab dem Zeitpunkt, an dem alle
                // überwachten GUS frei sind. Bei noch aktiver Bewegung gibt es keinen
                // Alarm-Endtimer. Eine bereits persistierte Deadline wird fortgeführt.
                if ($this->AreAllMonitoredSensorsClear($states)) {
                    $deadline = $this->ReadAttributeInteger('AlarmQuietNotBefore');
                    if ($deadline <= 0) {
                        $deadline = time() + max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                        $this->WriteAttributeInteger('AlarmQuietNotBefore', $deadline);
                    }
                    $remainingMs = max(1, ($deadline - time()) * 1000);
                    if ($deadline <= time()) {
                        $this->AlarmTimeout();
                    } else {
                        $this->SetTimerInterval('AlarmTimeout', $remainingMs);
                    }
                } else {
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                    $this->SetTimerInterval('AlarmTimeout', 0);
                }
            }
        } elseif ($sessionState === self::SESSION_REARM_WAIT) {
            $this->SetValue('AlarmActive', false);
            $this->WriteAttributeBoolean('ArmedReady', false);
            $this->SetAlarmControlsVisible(false);
            if ($this->AreAllMonitoredSensorsClear($states)) {
                if ($this->ReadAttributeInteger('RearmNotBefore') <= 0) {
                    $this->WriteAttributeInteger(
                        'RearmNotBefore',
                        time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                    );
                }
                $this->ScheduleRearmFromAttribute();
            }
        } else {
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);

            if ((bool) $this->GetValue('Automatic') && $this->ReadAttributeInteger('ManualOverride') === self::OVERRIDE_NONE) {
                $this->ApplyCurrentSchedule('startup');
            } else {
                $this->ApplyDesiredArmState((bool) $this->GetValue('Arm'));
            }
        }

        // Ein während der Rekonstruktion eventuell abgelaufener Nachlauf kann die
        // Session bereits beendet haben. Deshalb den Zustand vor externen Aktionen
        // nochmals frisch lesen und niemals mit einem veralteten ACTIVE-Snapshot arbeiten.
        $session = $this->ReadSession('CurrentSession');
        $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);

        // Bei einem Neustart während einer noch aktiven Alarm-Session werden die
        // konfigurierten Paniklichter deterministisch erneut auf EIN angefordert.
        // Bereits eingeschaltete Leuchten erhalten dabei keinen weiteren Befehl.
        if ($sessionState === self::SESSION_ACTIVE) {
            $sessionID = (string) ($session['id'] ?? '');
            if ($sessionID !== '') {
                $this->SetPanicForSession($sessionID, true, 'restart');
                $this->StartTVForAlarm($sessionID);
            }
        }

        // Baseline und persistenter Zustand sind jetzt konsistent. Ab hier dürfen
        // neue FALSE->TRUE-Flanken als echte Bewegungsereignisse ausgewertet werden.
        $this->SetBuffer('RuntimeReady', '1');

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
                    } elseif ($this->AreAllMonitoredSensorsClear($states)) {
                        // Erst wenn wirklich alle aktuell überwachten Räume frei sind,
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
                    } elseif ($this->AreAllMonitoredSensorsClear($states)) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                        $scheduleRearm = true;
                    }
                }
            } elseif ((bool) $this->GetValue('Arm')) {
                if (!(bool) $this->ReadAttributeBoolean('ArmedReady')) {
                    if ($this->CountMonitoredSensors() > 0 && $this->AreAllMonitoredSensorsClear($states)) {
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

            // PANIK EIN erzeugt ausschließlich AUS->EIN. Quittiert wird bewusst nur
            // eine echte EIN->AUS-Flanke während einer AKTIVEN Alarm-Session. Beim
            // späteren PANIK AUS ist die Session bereits rearm_wait und kann sich
            // deshalb nicht selbst quittieren.
            if ($oldValue && !$newValue) {
                $session = $this->ReadSession('CurrentSession');
                if (($session['state'] ?? self::SESSION_NONE) === self::SESSION_ACTIVE) {
                    $shouldAcknowledge = true;
                    $source = 'LCN-Licht: ' . $this->AcknowledgeLightName($VariableID);
                }
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
        // Die sichtbare Integer-Statusvariable der LCNLightGroup ist absichtlich
        // read-only und besitzt keine VariableAction. Geschaltet werden deshalb
        // die explizit konfigurierten LCNLight-Statusvariablen. Damit bilden wir
        // die Gruppenregel deterministisch nach: nur abweichende Leuchten erhalten
        // einen Befehl, und zwischen zwei Befehlen liegen 100 ms.
        $map = $this->ReadAcknowledgeMap();
        if ($map === []) {
            return;
        }

        if ($On && !$this->IsActiveSession($SessionID)) {
            return;
        }

        $queue = [];
        foreach ($map as $light) {
            $variableID = (int) ($light['id'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                continue;
            }

            try {
                $current = (bool) GetValue($variableID);
            } catch (Throwable $e) {
                continue;
            }

            if ($current === $On) {
                continue;
            }

            $queue[] = [
                'id' => $variableID,
                'target' => $On,
                'sessionID' => $SessionID,
                'reason' => $Reason
            ];
        }

        // Jeder neue definierte Panikzustand ersetzt einen eventuell noch laufenden
        // alten Auftrag. So kann insbesondere eine Quittierung eine noch laufende
        // EIN-Serie sofort in eine AUS-Serie umwandeln.
        $this->SetTimerInterval('PanicQueue', 0);
        $this->SetBuffer('PanicQueue', $this->Encode($queue));
        if ($queue !== []) {
            // Erstes Licht sofort, weitere mit 100 ms Abstand.
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
                    $this->WriteAttributeBoolean('ArmedReady', $this->CountMonitoredSensors() > 0 && $this->AreAllMonitoredSensorsClear($states));
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
            $this->SetPanicForSession($panicOffSessionID, false, 'anlage-aus/' . $Reason);
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
        $this->WriteAttributeBoolean('ArmedReady', $this->CountMonitoredSensors() > 0 && $this->AreAllMonitoredSensorsClear($states));
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
            $reason = (string) ($last['endReason'] ?? '');
            $endedAt = (float) ($last['endedAt'] ?? 0);
            $this->SetValue(
                'LastAlarm',
                (string) ($last['id'] ?? '-') . (($endedAt > 0) ? ' – ' . $this->FormatTimestamp($endedAt) : '') . (($reason !== '') ? ' – ' . $reason : '')
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
            'history' => $history
        ];
    }

    private function PushVisualizationState(): void
    {
        try {
            $this->UpdateVisualizationValue($this->BuildVisualizationState());
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
                } else {
                    $enabled = true;
                }
            }
        }

        return [[
            'enabled' => $enabled,
            'instanceID' => $instanceID,
            'statusVariableID' => $statusVariableID
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

        // Ein eventuell noch laufender AUS-Nachlauf einer alten Session darf die
        // neue Alarm-Session niemals ausschalten.
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->WriteAttributeInteger('TVOffDeadline', 0);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);

        $alreadyOwned = $this->ReadAttributeBoolean('TVOwnedByAlarm');
        $isOn = $this->TVIsOn($config);

        if ($alreadyOwned) {
            // TV gehoert bereits einem vorherigen Alarm (z.B. neue Session waehrend
            // eines noch laufenden AUS-Nachlaufs). Ownership wird auf die neue
            // Session uebertragen. Ist er inzwischen AUS, wird erneut geweckt.
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
            if ($isOn) {
                $this->SetTimerInterval('TVWakeRetry', 0);
                return;
            }
        } elseif ($isOn) {
            // Vor Alarm bereits EIN: niemals nach dem Alarm ausschalten.
            $this->WriteAttributeBoolean('TVOwnedByAlarm', false);
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
            $this->WriteAttributeInteger('TVWakeAttempts', 0);
            $this->WriteAttributeInteger('TVLastWakeAt', 0);
            $this->SendDebug('TV', 'Bei Alarmstart bereits EIN – bleibt nach Alarm EIN', 0);
            return;
        } else {
            $this->WriteAttributeBoolean('TVOwnedByAlarm', true);
            $this->WriteAttributeString('TVOwnerSessionID', $SessionID);
        }

        $this->WriteAttributeInteger('TVWakeAttempts', 1);
        $this->WriteAttributeInteger('TVLastWakeAt', time());
        $this->SendTVWakeUp($config, 'alarm-start');
        $this->SetTimerInterval('TVWakeRetry', 5000);
    }

    private function EndTVForAlarm(string $SessionID, string $Reason): void
    {
        $this->SetTimerInterval('TVWakeRetry', 0);

        if ($SessionID === '') {
            return;
        }

        $ownerSessionID = $this->ReadAttributeString('TVOwnerSessionID');
        if ($ownerSessionID !== $SessionID) {
            // Ownership wurde bereits auf eine neue Alarm-Session uebertragen oder
            // der TV war nicht Teil dieser Session.
            return;
        }

        if (!$this->ReadAttributeBoolean('TVOwnedByAlarm')) {
            // TV war bereits vor Alarm EIN. Keine Ausschaltung.
            $this->WriteAttributeString('TVOwnerSessionID', '');
            return;
        }

        $config = $this->ReadTVConfig();
        if (!(bool) ($config['enabled'] ?? false)) {
            $this->ClearTVOwnership();
            return;
        }

        $this->WriteAttributeInteger('TVOffDeadline', time() + 60);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);

        if ($this->TVIsOn($config)) {
            $this->SendTVPowerOff($config, $Reason);
        }

        // Auch wenn der TV aktuell noch AUS meldet, 10-s-Nachkontrolle starten:
        // ein kurz zuvor gesendeter WakeUp darf nicht nach Alarmende spaet hochfahren.
        $this->SetTimerInterval('TVOffCheck', 10000);
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

    private function ClearTVOwnership(): void
    {
        $this->SetTimerInterval('TVWakeRetry', 0);
        $this->SetTimerInterval('TVOffCheck', 0);
        $this->WriteAttributeBoolean('TVOwnedByAlarm', false);
        $this->WriteAttributeString('TVOwnerSessionID', '');
        $this->WriteAttributeInteger('TVWakeAttempts', 0);
        $this->WriteAttributeInteger('TVLastWakeAt', 0);
        $this->WriteAttributeInteger('TVOffDeadline', 0);
        $this->WriteAttributeInteger('TVOffFalseChecks', 0);
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

        return [[
            'pushEnabled' => $pushEnabled,
            'pushVisualizationID' => $pushVisualizationID,
            'emailEnabled' => $emailEnabled,
            'smtpID' => $smtpID,
            'emailRecipients' => $recipients
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
            $body = "ALARM ausgelöst!\n\n"
                . 'Zeit: ' . $time . "\n"
                . 'Erstauslöser: ' . $sensorName . "\n"
                . 'Alarm-ID: ' . $SessionID . "\n\n"
                . 'Die Alarmanlage bleibt scharf. Zum Quittieren die Symcon-App öffnen.';
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

    private function BuildAcknowledgeMap(array $SensorMap): array
    {
        $decoded = json_decode($this->ReadPropertyString('AcknowledgeLights'), true);
        if (!is_array($decoded)) {
            return [[], ['Quittier-Lichterliste ist ungültig']];
        }

        $map = [];
        $errors = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row) || !(bool) ($row['Enabled'] ?? false)) {
                continue;
            }

            $variableID = (int) ($row['VariableID'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                $errors[] = 'Quittier-Licht Zeile ' . ($index + 1) . ': Variable fehlt';
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': keine Boolean-Variable';
                continue;
            }
            if (!HasAction($variableID)) {
                $errors[] = 'Panik-Licht Zeile ' . ($index + 1) . ': Statusvariable besitzt keine nutzbare Aktion';
                continue;
            }

            if (isset($SensorMap[(string) $variableID])) {
                $errors[] = 'Variable #' . $variableID . ' darf nicht zugleich GUS und Quittier-Licht sein';
                continue;
            }
            if (isset($map[(string) $variableID])) {
                $errors[] = 'Quittier-Licht Variable #' . $variableID . ' ist doppelt eingetragen';
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
                if ($this->AreAllMonitoredSensorsClear($states)) {
                    $duration = max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', time() + $duration);
                    $scheduleAlarmAfterrun = true;
                    $alarmAfterrunMs = $duration * 1000;
                } else {
                    $this->WriteAttributeInteger('AlarmQuietNotBefore', 0);
                    $cancelAlarmAfterrun = true;
                }
            } elseif ($sessionState === self::SESSION_REARM_WAIT) {
                if ($this->AreAllMonitoredSensorsClear($states)) {
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
                $ready = $this->CountMonitoredSensors() > 0 && $this->AreAllMonitoredSensorsClear($states);
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
