<?php

declare(strict_types=1);

namespace skymin\CommandHelper;

use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use ReflectionClass;
use skymin\CommandHelper\parameter\CommandParameters;
use function count;
use function var_dump;

final class CommandHelper{

	private static bool $isRegistered = false;

	public static function isRegistered() : bool{
		return self::$isRegistered;
	}

	public static function registerHandler(Plugin $plugin) : void{
		if(self::$isRegistered) return;
		$server = Server::getInstance();
		$commandMap = $server->getCommandMap();
		$server->getPluginManager()->registerEvent(DataPacketSendEvent::class, static function(DataPacketSendEvent $ev) use(&$commandMap) : void{
			$packets = $ev->getPackets();
			$targets = $ev->getTargets();
			if(
				count($packets) !== 1 ||
				!($packet = $packets[0]) instanceof AvailableCommandsPacket ||
				count($targets) !== 1
			) return;
			$player = $targets[0]->getPlayer();
			if($player === null) return;
			/** @var AvailableCommandsPacket $packet */
			foreach($packet->commandData as $name => $commandData){
				$cmd = $commandMap->getCommand($name);
				if(!$cmd instanceof DetailedCommand) continue;
				$ref = new ReflectionClass($cmd);
				$newOverloads = [];
				foreach($ref->getAttributes() as $attribute){
					if($attribute->getName() !== CommandParameters::class) continue;
					/** @var CommandParameters $parameters */
					$parameters = $attribute->newInstance();
					$parameters->checkPermission($player);
					$newOverloads[] = $parameters->encode();
				}
				$commandData->overloads = $newOverloads;
			}
		}, EventPriority::MONITOR, $plugin);
	}
}