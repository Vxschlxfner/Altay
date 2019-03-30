<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use pocketmine\entity\passive\AbstractHorse;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\maps\MapData;
use pocketmine\maps\MapManager;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\network\mcpe\protocol\BlockPickRequestPacket;
use pocketmine\network\mcpe\protocol\BookEditPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\ClientboundMapItemDataPacket;
use pocketmine\network\mcpe\protocol\ClientToServerHandshakePacket;
use pocketmine\network\mcpe\protocol\CommandBlockUpdatePacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\CraftingEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\EntityFallPacket;
use pocketmine\network\mcpe\protocol\EntityPickRequestPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacketV1;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MapInfoRequestPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\MoveEntityAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerHotbarPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\RiderJumpPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\SetEntityMotionPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\ShowCreditsPacket;
use pocketmine\network\mcpe\protocol\SpawnExperienceOrbPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ItemFrameDropItemPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use function bin2hex;
use function implode;
use function json_decode;
use function json_last_error_msg;
use function preg_match;
use function preg_split;
use function strlen;
use function substr;
use function trim;

class PlayerNetworkSessionAdapter extends NetworkSession{

	/** @var Server */
	private $server;
	/** @var Player */
	private $player;

	public function __construct(Server $server, Player $player){
		$this->server = $server;
		$this->player = $player;
	}

	public function handleDataPacket(DataPacket $packet){
		if(!$this->player->isConnected()){
			return;
		}

		$timings = Timings::getReceiveDataPacketTimings($packet);
		$timings->startTiming();

		$packet->decode();
		if(!$packet->feof() and !$packet->mayHaveUnreadBytes()){
			$remains = substr($packet->buffer, $packet->offset);
			$this->server->getLogger()->debug("Still " . strlen($remains) . " bytes unread in " . $packet->getName() . ": 0x" . bin2hex($remains));
		}

		$ev = new DataPacketReceiveEvent($this->player, $packet);
		$ev->call();
		if(!$ev->isCancelled() and !$packet->handle($this)){
			$this->server->getLogger()->debug("Unhandled " . $packet->getName() . " received from " . $this->player->getName() . ": 0x" . bin2hex($packet->buffer));
		}

		$timings->stopTiming();
	}

	public function handleLogin(LoginPacket $packet) : bool{
		return $this->player->handleLogin($packet);
	}

	public function handleClientToServerHandshake(ClientToServerHandshakePacket $packet) : bool{
		return false; //TODO
	}

	public function handleResourcePackClientResponse(ResourcePackClientResponsePacket $packet) : bool{
		return $this->player->handleResourcePackClientResponse($packet);
	}

	public function handleText(TextPacket $packet) : bool{
		if($packet->type === TextPacket::TYPE_CHAT){
			return $this->player->chat($packet->message);
		}

		return false;
	}

	public function handleMovePlayer(MovePlayerPacket $packet) : bool{
		return $this->player->handleMovePlayer($packet);
	}

	public function handleLevelSoundEventPacketV1(LevelSoundEventPacketV1 $packet) : bool{
		return true; //useless leftover from 1.8
	}

	public function handleEntityEvent(EntityEventPacket $packet) : bool{
		return $this->player->handleEntityEvent($packet);
	}

	public function handleInventoryTransaction(InventoryTransactionPacket $packet) : bool{
		return $this->player->handleInventoryTransaction($packet);
	}

	public function handleMobEquipment(MobEquipmentPacket $packet) : bool{
		return $this->player->handleMobEquipment($packet);
	}

	public function handleMobArmorEquipment(MobArmorEquipmentPacket $packet) : bool{
		return true; //Not used
	}

	public function handleInteract(InteractPacket $packet) : bool{
		return $this->player->handleInteract($packet);
	}

	public function handleBlockPickRequest(BlockPickRequestPacket $packet) : bool{
		return $this->player->handleBlockPickRequest($packet);
	}

	public function handleEntityPickRequest(EntityPickRequestPacket $packet) : bool{
		return false; //TODO
	}

	public function handlePlayerAction(PlayerActionPacket $packet) : bool{
		return $this->player->handlePlayerAction($packet);
	}

	public function handleEntityFall(EntityFallPacket $packet) : bool{
		return true; //Not used
	}

	public function handleAnimate(AnimatePacket $packet) : bool{
		return $this->player->handleAnimate($packet);
	}

	public function handleContainerClose(ContainerClosePacket $packet) : bool{
		return $this->player->handleContainerClose($packet);
	}

	public function handlePlayerHotbar(PlayerHotbarPacket $packet) : bool{
		return true; //this packet is useless
	}

	public function handleCraftingEvent(CraftingEventPacket $packet) : bool{
		return true; //this is a broken useless packet, so we don't use it
	}

	public function handleAdventureSettings(AdventureSettingsPacket $packet) : bool{
		return $this->player->handleAdventureSettings($packet);
	}

	public function handleBlockEntityData(BlockEntityDataPacket $packet) : bool{
		return $this->player->handleBlockEntityData($packet);
	}

	public function handlePlayerInput(PlayerInputPacket $packet) : bool{
		if($this->player->isRiding()){
			$entity = $this->player->getRidingEntity();
			if($entity !== null and $entity->isAlive()){
				// TODO: Remove this, this is not right
				$entity->onRidingUpdate($this->player, $packet->motionX, $packet->motionY, $packet->jumping, $packet->sneaking);
			}
		}
		return true;
	}

	public function handleRiderJump(RiderJumpPacket $packet) : bool{
		if($this->player->isRiding()){
			$horse = $this->player->getRidingEntity();
			if($horse instanceof AbstractHorse){
				// This is useless for now, only may usable for plugins
				$horse->setJumpPower($packet->jumpStrength);
				return true;
			}
		}
		return false;
	}

	public function handleSetPlayerGameType(SetPlayerGameTypePacket $packet) : bool{
		return $this->player->handleSetPlayerGameType($packet);
	}

	public function handleSpawnExperienceOrb(SpawnExperienceOrbPacket $packet) : bool{
		return false; //TODO
	}

	public function handleMapInfoRequest(MapInfoRequestPacket $packet) : bool{
		$data = MapManager::getMapDataById($packet->mapId);
		if($data instanceof MapData){
			// this is for first appearance
			$pk = new ClientboundMapItemDataPacket();
			$pk->height = $pk->width = 128;
			$pk->dimensionId = $data->getDimension();
			$pk->scale = $data->getScale();
			$pk->colors = $data->getColors();
			$pk->mapId = $data->getId();
			$pk->decorations = $data->getDecorations();
			$pk->trackedEntities = $data->getTrackedObjects();

			$this->player->sendDataPacket($pk);

			return true;
		}
		return false;
	}

	public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet) : bool{
		$this->player->setViewDistance($packet->radius);

		return true;
	}

	public function handleItemFrameDropItem(ItemFrameDropItemPacket $packet) : bool{
		return $this->player->handleItemFrameDropItem($packet);
	}

	public function handleBossEvent(BossEventPacket $packet) : bool{
		return false; //TODO
	}

	public function handleShowCredits(ShowCreditsPacket $packet) : bool{
		return false; //TODO: handle resume
	}

	public function handleCommandRequest(CommandRequestPacket $packet) : bool{
		return $this->player->handleCommandRequest($packet);
	}

	public function handleCommandBlockUpdate(CommandBlockUpdatePacket $packet) : bool{
		return false; //TODO
	}

	public function handleResourcePackChunkRequest(ResourcePackChunkRequestPacket $packet) : bool{
		return $this->player->handleResourcePackChunkRequest($packet);
	}

	public function handlePlayerSkin(PlayerSkinPacket $packet) : bool{
		return $this->player->changeSkin($packet->skin, $packet->newSkinName, $packet->oldSkinName);
	}

	public function handleBookEdit(BookEditPacket $packet) : bool{
		return $this->player->handleBookEdit($packet);
	}

	public function handleModalFormResponse(ModalFormResponsePacket $packet) : bool{
		return $this->player->onFormSubmit($packet->formId, self::stupid_json_decode($packet->formData, true));
	}

	/**
	 * Hack to work around a stupid bug in Minecraft W10 which causes empty strings to be sent unquoted in form responses.
	 *
	 * @param string $json
	 * @param bool   $assoc
	 *
	 * @return mixed
	 */
	private static function stupid_json_decode(string $json, bool $assoc = false){
		if(preg_match('/^\[(.+)\]$/s', $json, $matches) > 0){
			$parts = preg_split('/(?:"(?:\\"|[^"])*"|)\K(,)/', $matches[1]); //Splits on commas not inside quotes, ignoring escaped quotes
			foreach($parts as $k => $part){
				$part = trim($part);
				if($part === ""){
					$part = "\"\"";
				}
				$parts[$k] = $part;
			}

			$fixed = "[" . implode(",", $parts) . "]";
			if(($ret = json_decode($fixed, $assoc)) === null){
				throw new \InvalidArgumentException("Failed to fix JSON: " . json_last_error_msg() . "(original: $json, modified: $fixed)");
			}

			return $ret;
		}

		return json_decode($json, $assoc);
	}

	public function handleServerSettingsRequest(ServerSettingsRequestPacket $packet) : bool{
		return false; //TODO: GUI stuff
	}

	public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet) : bool{
		$this->player->doFirstSpawn();
		return true;
	}

	public function handleLevelSoundEvent(LevelSoundEventPacket $packet) : bool{
		return $this->player->handleLevelSoundEvent($packet);
	}

	public function handleMoveEntityAbsolute(MoveEntityAbsolutePacket $packet) : bool{
		// TODO: remove this
		$target = $this->player->getServer()->findEntity($packet->entityRuntimeId);
		if($target !== null){
			if($this->player->isRiding() and $this->player->getRidingEntity() !== null and $this->player->getRidingEntity()->getId() === $target->getId()){
				$target->setPositionAndRotation($packet->position, $packet->yRot, $packet->xRot);

				return true;
			}
		}

		return false;
	}

	public function handleSetEntityMotion(SetEntityMotionPacket $packet) : bool{
		return true; // WTF, spam??
	}

	public function handleNetworkStackLatency(NetworkStackLatencyPacket $packet) : bool{
		return true; //TODO: implement this properly - this is here to silence debug spam from MCPE dev builds
	}
}