<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class eufy extends eqLogic {
  public static function deamonRunning() {
		return true;
	}

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
        if (@posix_getsid(trim(file_get_contents($pid_file)))) {
            $return['state'] = 'ok';
        } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
        }
    }
    $return['launchable'] = 'ok';
    $containerip = config::byKey('containerip', __CLASS__); // exemple si votre démon à besoin de la config user,
    $containerport = config::byKey('containerport', __CLASS__); // password,
    $clientId = config::byKey('clientId', __CLASS__); // et clientId
    if ($containerip == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('L\'IP du container n\'est pas configuré', __FILE__);
    } elseif ($containerport == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
    }
    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
        throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }

    $path = realpath(dirname(__FILE__) . '/../../resources/eufyd'); // répertoire du démon
    $cmd = 'python3 ' . $path . '/eufyd.py'; // nom du démon
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
    $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, '60600');
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/eufy/core/php/jeeeufy.php';
    $cmd .= ' --containerip "' . trim(str_replace('"', '\"', config::byKey('containerip', __CLASS__))) . '"'; // on rajoute les paramètres utiles à votre démon
    $cmd .= ' --containerport "' . trim(str_replace('"', '\"', config::byKey('containerport', __CLASS__))) . '"'; // second parametre
    $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__); // l'apikey pour authentifier les échanges suivants
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // et on précise le chemin vers le pid file (ne pas modifier)
    log::add(__CLASS__, 'info', 'Lancement démon');
    $result = exec($cmd . ' >> ' . log::getPathToLog('eufy_daemon') . ' 2>&1 &'); // nommer votre log en commençant par le pluginid pour que le fichier apparaisse dans la page de config
    $i = 0;
    while ($i < 20) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            break;
        }
        sleep(1);
        $i++;
    }
    if ($i >= 30) {
        log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
        return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');

    // Instanciate the current station mapping
    //$params = array('command' => 'getDevices');
    //eufy::sendToDaemon($params);

    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // ne pas modifier
    if (file_exists($pid_file)) {
        $pid = intval(trim(file_get_contents($pid_file)));
        system::kill($pid);
    }
    system::kill('eufyd.py');
    sleep(1);
  }

  public static function sendToDaemon($params) {
    $deamon_info = self::deamon_info();
		if ($deamon_info['state'] != 'ok') {
			throw new Exception("Le démon n'est pas démarré");
		}
		$params['apikey'] = jeedom::getApiKey(__CLASS__);
		$payLoad = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '60600'));
		socket_write($socket, $payLoad, strlen($payLoad));
		socket_close($socket);
  }


  public static function syncDevices($message){
    $message = str_replace(": True", ": \"True\"", $message);
    $message = str_replace(": False", ": \"False\"", $message);
    $message = str_replace("'", "\"", $message);
    
    log::add(__CLASS__, 'info', 'Sync devices');
    log::add(__CLASS__, 'debug', $message);
    
    $jsonObjArray = json_decode($message, true);
    $jsonObj = json_decode($message);

    $deviceId = 0;

    $serialStations = [];
    foreach($jsonObj as $device)
    {
      $eqLogic = eqLogic::byLogicalId($device->serialNumber, __CLASS__);
      
      if (!is_object($eqLogic)) {
        log::add(__CLASS__, 'info', 'Creating (' . $device->name . '-' . $device->serialNumber . ')');
        $eqLogic = new self();
        $eqLogic->setLogicalId($device->serialNumber);
       	$eqLogic->setName($device->name);
        $eqLogic->setConfiguration('type', 'Device');
     	  $eqLogic->setEqType_name(__CLASS__);
     	  $eqLogic->setIsEnable(1);
        $eqLogic->setCategory('security', 1);
        
        $eqLogic->setConfiguration('eufyName', $device->name);
        $eqLogic->setConfiguration('model', $device->model);
        $eqLogic->setConfiguration('serialNumber', $device->serialNumber);
        $eqLogic->setConfiguration('stationSerialNumber', $device->stationSerialNumber);
        $eqLogic->setConfiguration('hardwareVersion', $device->hardwareVersion);
		    $eqLogic->save();

        try{
          $commandsConfig = eufy::getCommandsFileContent(__DIR__ . '/../config/' . $device->model . '.json');
          $eqLogic->createCommandsFromConfig($commandsConfig, $jsonObjArray[$deviceId]);
        }
        catch(Exception $e) {
          log::add(__CLASS__, 'warning', $e);

        }

        if(!in_array($device->stationSerialNumber, $serialStations))
          $serialStations[] = $device->stationSerialNumber;

      }
      else{
        log::add(__CLASS__, 'debug', 'Already exist (' . $device->name . '-' . $device->serialNumber . ')');
      }

      $deviceId = $deviceId + 1; 
    }

    eufy::InitGuardModeForStations($serialStations);
  }

  public static function InitGuardModeForStations($stationSerialNumbers){
    foreach ($stationSerialNumbers as $stationSerialNumber) {      
      log::add(__CLASS__, 'DEBUG', 'Init guard mode for station ' . $stationSerialNumber);
      $params = array('command' => 'station.get_properties', 'serialNumber' => $stationSerialNumber);
      eufy::sendToDaemon($params);
    }
  }
  public static function updateDeviceInfo($serialNumber, $property, $value){
    $eqLogic = eqLogic::byLogicalId($serialNumber, __CLASS__);
    if (isset($eqLogic)){
      $cmd = $eqLogic->getCmd('info', $property);
      
      if (eufy::sendEvent($cmd, $value))
        log::add(__CLASS__, 'debug', 'device info updated');
      else
        log::add(__CLASS__, 'debug', 'device info updated');
    }
    else
      log::add(__CLASS__, 'debug', 'eqLogic is null');
  }

  public static function updateDeviceInfoForProperties($stationSerialNumber, $property, $value){

    foreach (self::byType('eufy', true) as $eqLogic) {
      
      if($eqLogic->getConfiguration('stationSerialNumber') == $stationSerialNumber){
        $cmd = $eqLogic->getCmd('info', $property);
        
        if (eufy::sendEvent($cmd, $value))
          log::add(__CLASS__, 'debug', 'device info updated');
      }
    }
  }

  public static function sendEvent($cmd, $value){
    if ($cmd->execCmd() != $cmd->formatValue($value)) {
      $cmd->event($value, null);;
      return true;
     }

     return false;
  }

  /* helper */
  private static function getCommandsFileContent(string $filePath) {
		if (!file_exists($filePath)) {
			throw new RuntimeException("Fichier de configuration non trouvé:{$filePath}");
		}
		$content = file_get_contents($filePath);
		if (!is_json($content)) {
			throw new RuntimeException("Fichier de configuration incorrecte:{$filePath}");
		}
		return json_decode($content, true);
	}

	public function createCommandsFromConfigFile(string $filePath, string $commandsKey) {
		$commands = self::getCommandsFileContent($filePath);
		$this->createCommandsFromConfig($commands[$commandsKey], null);
	}

	public function createCommandsFromConfig(array $commands, $values) {
		$link_cmds = array();
		foreach ($commands as $cmdDef){
			$cmd = $this->getCmd(null, $cmdDef["logicalId"]);
			if (!is_object($cmd)) {
				log::add(__CLASS__, 'debug', 'create:'.$cmdDef["logicalId"].'/'.$cmdDef["name"]);
				$cmd = new cmd();
				$cmd->setLogicalId($cmdDef["logicalId"]);
				$cmd->setEqLogic_id($this->getId());
				$cmd->setName(__($cmdDef["name"], __FILE__));
				if(isset($cmdDef["isHistorized"])) {
					$cmd->setIsHistorized($cmdDef["isHistorized"]);
				}
				if(isset($cmdDef["isVisible"])) {
					$cmd->setIsVisible($cmdDef["isVisible"]);
				}
				if (isset($cmdDef['template'])) {
					foreach ($cmdDef['template'] as $key => $value) {
						$cmd->setTemplate($key, $value);
					}
				}
			}
			$cmd->setType($cmdDef["type"]);
			$cmd->setSubType($cmdDef["subtype"]);
			if(isset($cmdDef["generic_type"])) {
				$cmd->setGeneric_type($cmdDef["generic_type"]);
			}
			if (isset($cmdDef['display'])) {
				foreach ($cmdDef['display'] as $key => $value) {
					if ($key=='title_placeholder' || $key=='message_placeholder') {
						$value = __($value, __FILE__);
					}
					$cmd->setDisplay($key, $value);
				}
			}
			if(isset($cmdDef["unite"])) {
				$cmd->setUnite($cmdDef["unite"]);
			}

			if (isset($cmdDef['configuration'])) {
				foreach ($cmdDef['configuration'] as $key => $value) {
					$cmd->setConfiguration($key, $value);
				}
			}

			if (isset($cmdDef['value'])) {
				$link_cmds[$cmdDef["logicalId"]] = $cmdDef['value'];
			}

			$cmd->save();

      // Init value
      if(isset($values))
        eufy::sendEvent($cmd, $values[$cmdDef["logicalId"]]);
      elseif (isset($cmdDef['initialValue'])) {
		  		$cmdValue = $cmd->execCmd();
				if ($cmdValue=='') {
					$this->checkAndUpdateCmd($cmdDef["logicalId"], $cmdDef['initialValue']);
				}
			}
		}

		foreach ($link_cmds as $cmd_logicalId => $link_logicalId) {
			$cmd = $this->getCmd(null, $cmd_logicalId);
			$linkCmd = $this->getCmd(null, $link_logicalId);

			if (is_object($cmd) && is_object($linkCmd)) {
				$cmd->setValue($linkCmd->getId());
				$cmd->save();
			}
		}
	}

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*     * **********************Getteur Setteur*************************** */
}

class eufyCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
		$eqlogic = $this->getEqLogic();

    $serialNumber= $eqlogic->getConfiguration('serialNumber');
    $stationSerialNumber= $eqlogic->getConfiguration('stationSerialNumber');
    
    switch ($this->getLogicalId()) {
      case 'start_rtsp':
        $params = array('command' => 'device.set_property', 'serialNumber' => $serialNumber, 'name' => 'rtspStream', 'value' => 'True');
        eufy::sendToDaemon($params);

        $params = array('command' => 'device.start_rtsp_livestream', 'serialNumber' => $serialNumber);
        eufy::sendToDaemon($params);
        break;
      case 'stop_rtsp': 
        $params = array('command' => 'device.stop_rtsp_livestream', 'serialNumber' => $serialNumber);
        eufy::sendToDaemon($params);
        break;
      case 'away':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '0');
        eufy::sendToDaemon($params);
        break;
      case 'home':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '1');
        eufy::sendToDaemon($params);
        break;
      case 'scheduled':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '2');
        eufy::sendToDaemon($params);
        break;
      case 'custom1':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '3');
        eufy::sendToDaemon($params);
        break;
      case 'custom2':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '4');
        eufy::sendToDaemon($params);
        break;
      case 'custom3':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '5');
        eufy::sendToDaemon($params);
        break;
      case 'geolocalized':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '47');
        eufy::sendToDaemon($params);
        break;
      case 'disarmed':
        $params = array('command' => 'station.set_property', 'serialNumber' => $stationSerialNumber, 'name' => 'guardMode', 'value' => '63');
        eufy::sendToDaemon($params);
        break;
    }
  }
}