<?php

namespace solo\smarket;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;

abstract class Process
{

	/** @var Player */
	protected $player;

	public function __construct(Player $player)
	{
		$this->player = $player;
	}

	abstract public function getName();

	public function handlePlayerInteract(PlayerInteractEvent $event)
	{

	}
}
