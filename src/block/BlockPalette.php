<?php
declare(strict_types=1);

namespace customiesdevs\customies\block;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\R12ToCurrentBlockMapEntry;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\SingletonTrait;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use function array_keys;
use function count;
use function defined;
use function sort;

final class BlockPalette {
	use SingletonTrait;

	/** @var CompoundTag[][] */
	private array $states;
	/** @var R12ToCurrentBlockMapEntry[] */
	private array $customStates = [];

	private RuntimeBlockMapping $runtimeBlockMapping;
	private ReflectionProperty $bedrockKnownStates;

	public function __construct() {
		$this->runtimeBlockMapping = $instance = RuntimeBlockMapping::getInstance();
		$runtimeBlockMapping = new ReflectionClass($instance);
		$this->bedrockKnownStates = $bedrockKnownStates = $runtimeBlockMapping->getProperty("bedrockKnownStates");
		$bedrockKnownStates->setAccessible(true);
		$protocolStates = $bedrockKnownStates->getValue($instance);
		foreach($protocolStates as $protocolId => $states){
			if($states instanceof CompoundTag){
				$this->states[ProtocolInfo::CURRENT_PROTOCOL] = $protocolStates;
				return;
			}
			if($protocolId <= ProtocolInfo::PROTOCOL_1_18_10){
				continue;
			}
			$this->states[$protocolId] = $states;
		}
	}

	/**
	 * @return CompoundTag[]
	 */
	public function getStates(int $protocolId): array {
		return $this->states[$protocolId];
	}

	/**
	 * @return R12ToCurrentBlockMapEntry[]
	 */
	public function getCustomStates(): array {
		return $this->customStates;
	}

	/**
	 * Inserts the provided state in to the correct position of the palette.
	 */
	public function insertState(CompoundTag $state, int $meta = 0): void {
		if($state->getString("name") === "") {
			throw new RuntimeException("Block state must contain a StringTag called 'name'");
		}
		if($state->getCompoundTag("states") === null) {
			throw new RuntimeException("Block state must contain a CompoundTag called 'states'");
		}
		$this->sortWith($state);
		$this->customStates[] = new R12ToCurrentBlockMapEntry($state->getString("name"), $meta, $state);
	}

	/**
	 * Sorts the palette's block states in the correct order, also adding the provided state to the array.
	 */
	private function sortWith(CompoundTag $newState): void {
		$knownStates = $this->bedrockKnownStates->getValue($this->runtimeBlockMapping);
		foreach($this->states as $protocolId => $tags){
			// To sort the block palette we first have to split the palette up in to groups of states. We only want to sort
			// using the name of the block, and keeping the order of the existing states.
			$states = [];
			foreach($tags as $state){
				$states[$state->getString("name")][] = $state;
			}
			// Append the new state we are sorting with at the end to preserve existing order.
			$states[$newState->getString("name")][] = $newState;

			$names = array_keys($states);
			if(!defined(ProtocolInfo::class . "::PROTOCOL_1_18_30") || $protocolId >= ProtocolInfo::PROTOCOL_1_18_30){
				// As of 1.18.30, blocks are sorted using a fnv164 hash of their names.
				usort($names, static fn(string $a, string $b) => strcmp(hash("fnv164", $a), hash("fnv164", $b)));
			}else{
				sort($names);
			}
			$sortedStates = [];
			foreach($names as $name){
				// With the sorted list of names, we can now go back and add all the states for each block in the correct order.
				foreach($states[$name] as $state){
					$sortedStates[] = $state;
				}
			}
			$this->states[$protocolId] = $sortedStates;
			count($this->states) === 1 ? $knownStates = $sortedStates : $knownStates[$protocolId] = $sortedStates;
		}
		$this->bedrockKnownStates->setValue($this->runtimeBlockMapping, $knownStates);
	}
}
