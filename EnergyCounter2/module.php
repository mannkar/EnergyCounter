<?php

declare(strict_types=1);
	class EnergyCounter2 extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			/*
			 * 0 = Power (W)
			 * 1 = Current (A)
			 */
			$this->RegisterPropertyInteger('SourceVariable', 0);
			$this->RegisterPropertyInteger('Voltage', 0);
			$this->RegisterPropertyInteger('PowerFactor', 0);
			$this->RegisterPropertyInteger('Interval', 60);
	
			$this->RegisterTimer('UpdateTimer', 0, 'ECP_Update($_IPS[\'TARGET\']);');
	
			$this->RegisterVariableFloat('Current', 'Current', 'Watt.3680', 0);
			$this->RegisterVariableFloat('Counter', 'Counter', 'Electricity', 1);
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('Interval') * 1000);
	
			//Delete all registrations in order to readd them
			foreach ($this->GetMessageList() as $senderID => $messages) {
				foreach ($messages as $message) {
					$this->UnregisterMessage($senderID, $message);
				}
			}
			$this->RegisterMessage($this->ReadPropertyInteger('SourceVariable'), VM_UPDATE);
			$this->RegisterMessage($this->ReadPropertyInteger('PowerFactor'), VM_UPDATE);
	
			//Add references
			foreach ($this->GetReferenceList() as $reference) {
				$this->UnregisterReference($reference);
			}
			$sourceID = $this->ReadPropertyInteger('SourceVariable');
			if ($sourceID != 0) {
				$this->RegisterReference($sourceID);
			};
			$VoltageID = $this->ReadPropertyInteger('Voltage');
			if ($VoltageID != 0) {
				$this->RegisterReference($VoltageID);
			};
			$PowerFactorID = $this->ReadPropertyInteger('PowerFactor');
			if ($PowerFactorID != 0) {
				$this->RegisterReference($PowerFactorID);
			}
		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
	
			//guard against messages that were registered from previous configurations

			if (GetValueFloat($this->ReadPropertyInteger('SourceVariable')) == 0 and GetValue($this->GetIDForIdent('Current')) == 0)
			{return;}
			else
				{	if ($SenderID == $this->ReadPropertyInteger('SourceVariable')) {
					$this->Update();
				}
				elseif ($SenderID == $this->ReadPropertyInteger('PowerFactor')) {
					$this->Update();
				
				}
			}		
			
		}
	
		/**
		 * This function will be available automatically after the module is imported with the module control.
		 * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		 *
		 * EnergyCounterPower
		 * 
		 * ECP_Update($id);
		 *
		 */
		public function Update()
		{
			if (IPS_SemaphoreEnter('ECP_' . $this->InstanceID, 1000)) {
				if (IPS_VariableExists($this->ReadPropertyInteger('SourceVariable'))) {
	
					//we use the last updated value to calculate the amount the need to add
					$timeDiff = time() - IPS_GetVariable($this->GetIDForIdent('Current'))['VariableUpdated'];
	
					//get current value
					$currentValue = GetValue($this->GetIDForIdent('Current'));
	
					//fetch last counter
					$lastCounterValue = GetValue($this->GetIDForIdent('Counter'));
	
					//add to our counter
					SetValue($this->GetIDForIdent('Counter'), $lastCounterValue + (($currentValue / 1000) * ($timeDiff / 3600)));
	
					//fetch source value
					$sourceValue = GetValue($this->ReadPropertyInteger('SourceVariable'))/1000;
					$sourceVoltage = GetValue($this->ReadPropertyInteger('Voltage'));
					$sourcePowerFactor = GetValue($this->ReadPropertyInteger('PowerFactor'));
	
					//Convert current to power
					$sourceValue = $sourceValue * $sourceVoltage * $sourcePowerFactor;
						
					SetValue($this->GetIDForIdent('Current'), $sourceValue);
				}
				IPS_SemaphoreLeave('ECP_' . $this->InstanceID);
			}
		}
	}