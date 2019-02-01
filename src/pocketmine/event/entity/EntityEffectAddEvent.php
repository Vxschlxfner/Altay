<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\event\entity;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Entity;

/**
 * Called when an effect is added to an Entity.
 */
class EntityEffectAddEvent extends EntityEffectEvent{
	/** @var EffectInstance|null */
	private $oldEffect;

	/**
	 * @param Entity         $entity
	 * @param EffectInstance $effect
	 * @param EffectInstance $oldEffect
	 */
	public function __construct(Entity $entity, EffectInstance $effect, EffectInstance $oldEffect = null){
		parent::__construct($entity, $effect);
		$this->oldEffect = $oldEffect;
	}

	/**
	 * Returns whether the effect addition will replace an existing effect already applied to the entity.
	 *
	 * @return bool
	 */
	public function willModify() : bool{
		return $this->hasOldEffect();
	}

	/**
	 * @return bool
	 */
	public function hasOldEffect() : bool{
		return $this->oldEffect instanceof EffectInstance;
	}

	/**
	 * @return EffectInstance|null
	 */
	public function getOldEffect() : ?EffectInstance{
		return $this->oldEffect;
	}
}