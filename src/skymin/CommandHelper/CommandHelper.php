<?php

declare(strict_types=1);

namespace skymin\CommandHelper;

use pocketmine\command\Command;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\Permission;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use ReflectionClass;
use skymin\CommandHelper\parameter\CommandParameters;
use function count;
use function trim;

final class CommandHelper{

	private static bool $isRegistered = false;

	/**
	 * @var CommandParameter[][]
	 * @phpstan-var array<string, CommandParameter[]>
	 */
	private static array $overloads = [];

	/**
	 * @var null|string|Permission[][]
	 * @phpstan-var array<string, null|string|Permission>
	 */
	private static array $permissions = [];

	public static function isRegistered() : bool{
		return self::$isRegistered;
	}

	public static function registerHandler(Plugin $plugin) : void{
		if(self::$isRegistered) return;
		Server::getInstance()->getPluginManager()->registerEvent(DataPacketSendEvent::class, static function(DataPacketSendEvent $ev) : void{
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
				if(!isset(self::$overloads[$name])) continue;
				$newOverloads = [];
				foreach(self::$overloads[$name] as $index => $overload){
					$permission = self::$permissions[$name][$index];
					if($permission !== null && !$player->hasPermission($permission)) continue;
					$newOverloads[] = $overload;
				}
				$commandData->overloads = $newOverloads;
			}
		}, EventPriority::MONITOR, $plugin);
	}

	public static function registerCommand(string $fallbackPrefix, Command $command, ?string $label = null) : bool{
		if($label === null){
			$label = $command->getLabel();
		}
		$label = trim($label);
		$result = Server::getInstance()->getCommandMap()->register($fallbackPrefix, $command, $label);
		if($result){
			$ref = new ReflectionClass($command);
			foreach($ref->getAttributes() as $attribute){
				if($attribute->getName() !== CommandParameters::class) continue;
				/** @var CommandParameters $parameters */
				$parameters = $attribute->newInstance();
				self::$overloads[$label][] = $parameters->encode();
				self::$permissions[$label][] = $parameters->getPermission();
			}
		}
		return  $result;
	}

	/** @param Command[] $commands */
	public static function registerCommands(string $fallbackPrefix, array $commands) : void{
		foreach($commands as $command){
			self::registerCommand($fallbackPrefix, $command);
		}
	}
}