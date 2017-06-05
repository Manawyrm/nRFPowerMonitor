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
		$this->configure("rf/datarate", "2000");
		$this->configure("rf/channel", $this->channel);
		$this->configure("crc", "2");
		$this->configure("tx_address", "89674523" . $this->address);
		$this->configure("pipe0/address", "89674523" . $this->address);
		$this->configure("pipe0/dynamicpayload", "0");
		$this->configure("pipe0/payloadwidth", "32");
		//$this->configure("pipe0/autoack", "1");

		/*$this->configure("pipe1/dynamicpayload", "0");
		$this->configure("pipe1/address", "89674523" . $this->address);
		$this->configure("pipe1/payloadwidth", "32");
		$this->configure("pipe1/autoack", "0");*/
	}

	public function enableRelay()
	{
		$this->writePacket(hex2bin("aa0567" . $this->address . "00180000000000000000000000000000000000000000000000000000"));
	}

	public function disableRelay()
	{
		$this->writePacket(hex2bin("aa0567" . $this->address . "ff170000000000000000000000000000000000000000000000000000"));
	}

	public function readData($activeRequest = true)
	{
		$this->flushQueue(); 

		$data = false;
		$start = microtime(true);

		while ($start + 5 > microtime(true))
		{
			if ($start + 1 < microtime(true))
			{
				echo "Sensor hasn't replied in 1 second!\n";
				// Sensor hasn't replied in 1 second.
				// Maybe it was restarted and hasn't been initialized yet.

				// aa040201b1000000000000000000000000000000000000000000000000000000
				
				//$this->writePacket(hex2bin("aa0402" . $this->address . "b2000000000000000000000000000000000000000000000000000000"));
				//$this->writePacket(hex2bin("aa04ff" . $this->address . "af000000000000000000000000000000000000000000000000000000"));
				//$this->writePacket(hex2bin("aa04ff" . $this->address . "af1200000000000000000000040002310f6a6fbf0f5f0f5f96000000"));
											  aa040204                    b4000000000000000000000000000000000000000000000000000000
				  $this->writePacket(hex2bin("aa04ff" . $this->address . "b1000000000000000000000000000000000000000000000000000000"));
				  $this->writePacket(hex2bin("aa04ff" . $this->address . "ae000000000000000000000000000000000000000000000000000000"));
				  $this->writePacket(hex2bin("aa04ff" . $this->address . "ae1200000000000000000000040002310f6a6fbf0f5f0f5feb000000"));

				usleep ( 100 * 1000 );
				$this->flushQueue(); 
			}
			
			if ($activeRequest)
			{
				// 02 - $this->writePacket(hex2bin("aa0401" . $this->address . "b1180000000000000000000000000000000000000000000000000000"));
				$this->writePacket(hex2bin("aa0401" . $this->address . "b0000000000000000000000000000000000000000000000000000000"));
			}
			usleep ( 10 * 1000 );

			$packet = $this->readPacket();
			if ($this->debug)
			{
				echo "Received packet! - Data: " . bin2hex($packet) . "\n";
			}
			//var_dump(bin2hex($packet));
			if (bin2hex($packet[1]) == "1c" && bin2hex($packet[2]) == "01")
			{
				//echo bin2hex($packet) . "\n";

				$data = unpack("Cpreamble/CrequestType/Cunknown1/Caddress/nvoltage/ncurrent/npower/npower", $packet);

				$return = [
					"requestType" => $data['requestType'],
					"address"     => $data['address'],
					"unknown1"    => $data['unknown1'],
					"voltage"     => $data['voltage'] / 100,
					"current"     => $data['current'] / 100,
					"power"       => $data['power'] / 1000,
				]; 
				return $return;
			}

		}

		return false; 
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
		$hndl = fopen("/dev/nrf24l01", "wb");
		fwrite($hndl, $data);
		fclose($hndl);
	}

}
?>
