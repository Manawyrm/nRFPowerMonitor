<?php
/**
* @brief       nRFPowerMonitor - Control Wireless Power Monitors remotely
* @date        2017-06-05
* @author      Tobias Mädel (t.maedel@alfeld.de)
*/

class nRFPowerMonitor
{
	private $nrfPath = "/sys/class/nrf24/nrf24l01";
	private $channel = "4";
	private $address = "02";
	private $debug   = false; 

	function __construct($channelN = "4", $addressN = "02", $debugN = false)
	{
		if (!file_exists("/dev/nrf24l01"))
		{
			throw new Exception("nRF24L01 kernel module not loaded!", 1);
			return;
		}

		$this->channel = $channelN;
		$this->address = $addressN;
		$this->debug   = $debugN;

		$this->configureRadio();
	}

	public function initializeSensor()
	{
		// Send the init commands to the sensor module
		$this->writePacket($this->calculateChecksum("aa04ff" . $this->address . "00", 4));
		usleep ( 100 * 1000 );
		$this->flushQueue(); 
	}

	public function configureRadio()
	{
		$this->configure("rf/datarate", "2000");
		$this->configure("rf/channel", $this->channel);
		$this->configure("crc", "2");
		$this->configure("tx_address", "89674523" . $this->address);
		$this->configure("pipe0/address", "89674523" . $this->address);
		$this->configure("pipe0/dynamicpayload", "0");
		$this->configure("pipe0/payloadwidth", "32");
	}

	public function calculateChecksum($data, $offset, $hex = true)
	{
		$data = hex2bin($data);
		$checksum = 0;
		for ($i=0; $i < $offset; $i++)
		{ 
			$checksum += ord($data[$i]);
		}
		$checksum = $checksum & 0xFF; 

		$data[$offset] = chr($checksum);

		if (!$hex)
			$data = bin2hex($data);

		return $data;
	}

	public function enableRelay()
	{
		$this->configureRadio();
		usleep(1 * 1000);
		$this->writePacket($this->calculateChecksum("aa0567" . $this->address . "0000", 5));
	}

	public function disableRelay()
	{
		$this->configureRadio();
		usleep(1 * 1000);
		$this->writePacket($this->calculateChecksum("aa0567" . $this->address . "ff00", 5));
	}

	public function readData($activeRequest = true)
	{
		$this->configureRadio();
		usleep(1 * 1000);

		$this->flushQueue(); 

		$data = false;
		$start = microtime(true);

		while ($start + 5 > microtime(true))
		{
			if ($start + 2 < microtime(true))
			{
				if ($this->debug)
					echo "Sensor hasn't replied in 1 second!\n";
				
				// Sensor hasn't replied in 1 second.
				// Maybe it was restarted and hasn't been initialized yet.
				$this->initializeSensor();
			}
			
			if ($activeRequest)
			{
				$this->writePacket($this->calculateChecksum("aa0401" . $this->address . "00", 4));
			}
			usleep ( 10 * 1000 );

			$packet = $this->readPacket();
			if ($this->debug)
			{
				echo "Received packet! - Data: " . bin2hex($packet) . "\n";
			}

			if (bin2hex($packet[1]) == "1c" && bin2hex($packet[2]) == "01")
			{
				$data = unpack("Cpreamble/CrequestType/Cunknown1/Caddress/nvoltage/ncurrent/Npower/Namphour/Nwatthour", $packet);

				$return = [
					"requestType" => $data['requestType'],
					"address"     => $data['address'],
					"unknown1"    => $data['unknown1'],
					"voltage"     => $data['voltage'] / 100,
					"current"     => $data['current'] / 100,
					"power"       => $data['power'] / 1000,
                                        "amphour"     => $data['amphour'] / 1000,
                                        "watthour"    => $data['watthour'] / 1000,
 				]; 
				return $return;
			}

		}

		return false; 
	}

	private function hexPad($data)
	{
		return sprintf('%02x', $data);
	}
	public function configure($name, $value)
	{
		file_put_contents($this->nrfPath . "/" . $name, $value);
	}

	public function flushQueue()
	{
		while ($this->readPacket(0) !== false) {};
	}

	public function readPacket($timeout = 1)
	{
		$hndl = fopen("/dev/nrf24l01", "rb");
		//echo "read!";
		stream_set_blocking($hndl, false);
		$data = "";
		$start = microtime(true);
		while (true)
		{
			$data = fread($hndl, 40);

			if ($data !== "")
			{
				fclose($hndl);
				return $data;
			}

			if ($start + $timeout < microtime(true))
			{
				fclose($hndl);
				return false;
			}
		}
		fclose($hndl);
	}

	public function writePacket($data)
	{
		$data = str_pad($data, 32, "\x00");

		if ($this->debug)
			echo date("H:i:s") . " - Sent packet: " . bin2hex($data) . "\n";
		
		$hndl = fopen("/dev/nrf24l01", "wb");
		fwrite($hndl, $data);
		fclose($hndl);
	}

}
?>
