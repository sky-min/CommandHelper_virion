<?php

declare(strict_types=1);

namespace skymin\CommandHelper\parameter;

use Attribute;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use function count;


#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class CommandParameters{

	/** @var Parameter[] */
	private readonly array $parameters;

	public function __construct(
		private readonly null|string|Permission $permission = null,
		Parameter ...$parameters
	){
		if(count($parameters) === 0){
			$parameters = [new Parameter('', '')];
		}
		$this->parameters = $parameters;
	}

	public function checkPermission(Player $player) : bool{
		if($this->permission === null) return true;
		return $player->hasPermission($this->permission);
	}

	/** @return CommandParameter[] */
	public function encode() : array{
		$overload = [];
		foreach($this->parameters as $parameter){
			$overload[] = $parameter->encode();
		}
		return $overload;
	}
}