<?php

declare(strict_types=1);

// General helper functions
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS LightAutomat
 */
class LightAutomat extends IPSModule
{
    use DebugHelper;
    use EventHelper;
    use ProfileHelper;
    use VariableHelper;

    // Schedule constant
    public const SCHEDULE_ON = 1;
    public const SCHEDULE_OFF = 2;
    public const SCHEDULE_NAME = 'Zeitplan';
    public const SCHEDULE_IDENT = 'circuit_diagram';
    public const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
        self::SCHEDULE_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
    ];
    // Time Unites constant
    public const TIME_SECONDS = 0;
    public const TIME_MINUTES = 1;
    public const TIME_HOURS = 2;
    public const TIME_CLOCK = 3;
    public const TIME_UNIT = [
        self::TIME_SECONDS  => ['TLA.Seconds', ' seconds', 1, 59, 1],
        self::TIME_MINUTES  => ['TLA.Minutes', ' minutes', 1, 59, 60],
        self::TIME_HOURS    => ['TLA.Hours', ' hours', 1, 23, 3600],
        self::TIME_CLOCK    => ['~UnixTimestampTime', '', 1, 23, 82800],
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Devices ...
        $this->RegisterPropertyInteger('StateVariable', 0);
        $this->RegisterPropertyInteger('MotionVariable', 0);
        // Time Control ...
        $this->RegisterPropertyInteger('TimeUnit', 1);
        $this->RegisterPropertyInteger('Duration', 10);
        $this->RegisterPropertyString('Time', '{"hour":0,"minute":1,"second":0}');
        $this->RegisterPropertyInteger('EventVariable', 0);
        // Advanced Settings ...
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyBoolean('OnlyScript', false);
        $this->RegisterPropertyBoolean('CheckSchedule', true);
        $this->RegisterPropertyBoolean('CheckDuration', true);
        $this->RegisterPropertyBoolean('CheckPermanent', true);
        // Profile
        foreach (self::TIME_UNIT as $key => $value) {
            if ($key != self::TIME_CLOCK) {
                $this->RegisterProfileInteger($value[0], 'Clock', '', $this->Translate($value[1]), $value[2], $value[3], 1, null);
            }
        }
        // Timer
        $this->RegisterTimer('TLA.Timer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "delay_trigger", "");');
    }

    /**
     * Destroy.
     */
    public function Destroy()
    {
        parent::Destroy();
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Read setup
        $unit = $this->ReadPropertyInteger('TimeUnit');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'unit=' . $unit);
        // Set duration inputs
        if ($unit < self::TIME_CLOCK) {
            // Check duration
            $suf = $this->Translate(self::TIME_UNIT[$unit][1]);
            $min = self::TIME_UNIT[$unit][2];
            $max = self::TIME_UNIT[$unit][3];
            // Set min/max/suffix
            $form['elements'][3]['items'][0]['items'][1]['minimum'] = $min;
            $form['elements'][3]['items'][0]['items'][1]['maximum'] = $max;
            $form['elements'][3]['items'][0]['items'][1]['suffix'] = $suf;
            $form['elements'][3]['items'][0]['items'][1]['visible'] = true;
        } else {
            $form['elements'][3]['items'][0]['items'][2]['visible'] = true;
        }
        // Debug output
        //$this->SendDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        //Register references
        $variable = $this->ReadPropertyInteger('StateVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }
        $variable = $this->ReadPropertyInteger('MotionVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (IPS_EventExists($event)) {
            $this->RegisterReference($event);
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (IPS_ScriptExists($script)) {
            $this->RegisterReference($script);
        }

        //Safty check
        $variable = $this->ReadPropertyInteger('StateVariable');
        if (($variable > 0) && !IPS_VariableExists($variable)) {
            $this->SendDebug(__FUNCTION__, 'StateVariable: ' . $variable);
            $this->SetStatus(104);
            return;
        }
        $variable = $this->ReadPropertyInteger('MotionVariable');
        if (($variable > 0) && !IPS_VariableExists($variable)) {
            $this->SendDebug(__FUNCTION__, 'MotionVariable: ' . $variable);
            $this->SetStatus(104);
            return;
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (($event > 0) && !IPS_EventExists($event)) {
            $this->SendDebug(__FUNCTION__, 'EventVariable: ' . $event);
            $this->SetStatus(104);
            return;
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (($script > 0) && !IPS_ScriptExists($script)) {
            $this->SendDebug(__FUNCTION__, 'ScriptVariable: ' . $script);
            $this->SetStatus(104);
            return;
        }

        //Register update messages = Create our trigger
        if (IPS_VariableExists($this->ReadPropertyInteger('StateVariable'))) {
            $this->RegisterMessage($this->ReadPropertyInteger('StateVariable'), VM_UPDATE);
        }

        // Maintain variables
        $permanent = $this->ReadPropertyBoolean('CheckPermanent');
        $this->MaintainVariable('continuous_operation', $this->Translate('Continuous operation'), VARIABLETYPE_BOOLEAN, '~Switch', 0, $permanent);
        if ($permanent) {
            $this->SetValueBoolean('continuous_operation', false);
            $this->EnableAction('continuous_operation');
        }
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $this->MaintainVariable('duty_cycle', $this->Translate('Duty cycle'), VARIABLETYPE_INTEGER, self::TIME_UNIT[$unit][0], 1, $duration);
        $this->SendDebug(__FUNCTION__, 'Create duration: ' . $duration . ' Create perament: ' . $permanent, 0);
        if ($duration) {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->ReadPropertyInteger('Duration');
                $this->SetValueInteger('duty_cycle', $time);
            } else {
                $time = json_decode($this->ReadPropertyString('Time'), true);
                $this->SetValueInteger('duty_cycle', 82800 + (($time['hour'] * 3600) + ($time['minute'] * 60) + $time['second']));
            }
            $this->EnableAction('duty_cycle');
        }
    }

    /**
     * MessageSink - internal SDK funktion.
     *
     * @param mixed $timeStamp Message timeStamp
     * @param mixed $senderID Sender ID
     * @param mixed $message Message type
     * @param mixed $data data[0] = new value, data[1] = value changed, data[2] = old value, data[3] = timestamp
     */
    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        //$this->SendDebug(__FUNCTION__, 'SenderId: ' . $senderID . ' Data: ' . print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // Safty Check
                if ($senderID != $this->ReadPropertyInteger('StateVariable')) {
                    $this->SendDebug(__FUNCTION__, 'SenderID: ' . $senderID . ' unknown!');
                    break;
                }
                // Countinus operation?
                $permanent = $this->ReadPropertyBoolean('CheckPermanent');
                if ($permanent) {
                    $state = $this->GetValue('continuous_operation');
                    if ($state) {
                        $this->SendDebug(__FUNCTION__, 'Continuous operation is ON!');
                        break;
                    }
                }
                // Weekly schedule!
                $eid = $this->ReadPropertyInteger('EventVariable');
                if ($eid != 0) {
                    $state = $this->GetWeeklyScheduleInfo($eid);
                    if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                        $this->SendDebug(__FUNCTION__, 'Weekly schedule is stored but state is inaktive!');
                        break;
                    }
                }
                // Switch state?
                if ($data[0] == true && $data[1] == true) { // OnChange is TRUE => switched ON
                    $this->SendDebug(__FUNCTION__, 'OnChange is TRUE - ON');
                    $this->SetTimerInterval('TLA.Timer', $this->CalculateTimer());
                } elseif ($data[0] == false && $data[1] == true) { // OnChange is FALSE => switched OFF
                    $this->SendDebug(__FUNCTION__, 'OnChange is FALSE - OFF');
                    $this->SetTimerInterval('TLA.Timer', 0);
                } else { // OnChange - no chenges!
                    $this->SendDebug(__FUNCTION__, 'OnChange - nothing changed!');
                }
                break;
        }
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'continuous_operation':
                $this->SetValueBoolean($ident, $value);
                break;
            case 'duty_cycle':
                $this->SetValueInteger($ident, $value);
                break;
            case 'circuit_diagram':
                $this->Schedule($value);
                break;
            case 'delay_trigger':
                $this->Trigger();
                break;
            default:
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
        //return true;
    }

    /**
     * Import death days data.
     *
     * @param string $value unit and value of duration.
     */
    protected function OnTimeUnit($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        if ($data['unit'] < self::TIME_CLOCK) {
            // min/max/suffix
            $suf = $this->Translate(self::TIME_UNIT[$data['unit']][1]);
            $min = self::TIME_UNIT[$data['unit']][2];
            $max = self::TIME_UNIT[$data['unit']][3];
            // Set min/max/suffix
            $this->UpdateFormField('Duration', 'minimum', $min);
            $this->UpdateFormField('Duration', 'maximum', $max);
            $this->UpdateFormField('Duration', 'suffix', $suf);
            // Check Value
            $value = $data['value'];
            if ($value > $max) {
                $value = 10; //default: 10
                $this->UpdateFormField('Duration', 'value', $value);
            }
        }
        $this->UpdateFormField('Duration', 'visible', ($data['unit'] != self::TIME_CLOCK));
        $this->UpdateFormField('Time', 'visible', ($data['unit'] == self::TIME_CLOCK));
    }

    /**
     * Trigger Timer
     *
     */
    private function Trigger()
    {
        $sv = $this->ReadPropertyInteger('StateVariable');
        if (GetValueBoolean($sv) == true) {
            if ($this->ReadPropertyBoolean('OnlyScript') == false) {
                $mid = $this->ReadPropertyInteger('MotionVariable');
                if ($mid != 0 && GetValue($mid)) {
                    $this->SendDebug(__FUNCTION__, 'Motion detection aktive, still resume!');
                    return;
                } else {
                    $ret = @RequestAction($sv, false);
                    if ($ret === false) {
                        $this->SendDebug(__FUNCTION__, 'Device #' . $sv . ' could not be switched by RequestAction!');
                        $ret = @SetValueBoolean($sv, false);
                        if ($ret === false) {
                            $this->SendDebug(__FUNCTION__, 'Device could not be switched by Boolean!');
                        }
                    }
                    if ($ret === false) {
                        $this->LogMessage('Device could not be switched (UNREACH)!');
                    } else {
                        $this->SendDebug(__FUNCTION__, 'StateVariable (#' . $sv . ') switched to FALSE!');
                    }
                }
            }
            // Script ausführen
            if ($this->ReadPropertyInteger('ScriptVariable') != 0) {
                if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                    $rs = IPS_RunScript($this->ReadPropertyInteger('ScriptVariable'));
                    $this->SendDebug(__FUNCTION__, 'Script Execute Return Value: ' . $rs);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Script #' . $this->ReadPropertyInteger('ScriptVariable') . ' does not exist!');
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'STATE already on FALSE - delete Timer!');
        }
        $this->SetTimerInterval('TLA.Timer', 0);
    }

    /**
     * Schedule Event
     *
     * @param integer $vaue Action value (ON=1, OFF=2)
     */
    private function Schedule(int $value)
    {
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value);
        // Check SChedule on Activate?
        $check = $this->ReadPropertyBoolean('CheckSchedule');
        if (!$check) {
            $this->SendDebug(__FUNCTION__, 'Check: nothing to do!');
            return;
        }
        // Is Activate ON
        if ($value == self::SCHEDULE_OFF) {
            $this->SendDebug(__FUNCTION__, 'Value: nothing to do!');
            return;
        }
        // Is Device State ON
        $sv = $this->ReadPropertyInteger('StateVariable');
        if (GetValueBoolean($sv) == true) {
            // Is a Timer active
            $interval = $this->GetTimerInterval('TriggerTimer');
            $this->SendDebug(__FUNCTION__, 'Timer: ' . $interval);
            if ($interval == 0) {
                $this->SendDebug(__FUNCTION__, 'State is TRUE and no Timer ON');
                $this->SetTimerInterval('TriggerTimer', $this->CalculateTimer());
            }
        }
    }

    /**
     * Calculate duration timer.
     *
     * @return int   Timer intervall in milliseconds
     */
    private function CalculateTimer()
    {
        $interval = 0;
        $unit = $this->ReadPropertyInteger('TimeUnit');
        // Use internal or external variable
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        if ($duration) {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->GetValue('duty_cycle');
                $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
            } else {
                $vid = $this->GetIDForIdent('duty_cycle');
                $time = explode(':', GetValueFormatted($vid));
                $interval = 1000 * (($time[0] * 3600) + ($time[1] * 60) + $time[2]);
            }
        } else {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->ReadPropertyInteger('Duration');
                $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
            } else {
                $time = json_decode($this->ReadPropertyString('Time'), true);
                $interval = 1000 * (($time[0] * 3600) + ($time[1] * 60) + $time[2]);
            }
        }
        return $interval;
    }

    /**
     * Creates a schedule plan.
     *
     * @param string $value instance ID.
     */
    private function OnCreateSchedule($value)
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if ($eid !== false) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }
}
